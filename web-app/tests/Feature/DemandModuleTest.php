<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Demand;
use App\Models\DemandModuleDefinition;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemandModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_add_custom_module_and_use_shared_demand_flow(): void
    {
        [$organization, $manager, $professional] = $this->workspace();

        $this->actingAs($manager)->post(route('approval-modules.store'), [
            'label' => 'Apresentações',
            'description' => 'Revisão de apresentações comerciais.',
        ])->assertRedirect(route('approval-modules.index'))->assertSessionHasNoErrors();

        $module = DemandModuleDefinition::query()->where('organization_id', $organization->id)->firstOrFail();
        $this->assertSame('apresentacoes', $module->key);
        $this->assertSame($manager->id, $module->created_by);
        $this->get(route('demands.create'))->assertOk()->assertSee('Apresentações');
        $this->withToken($manager->createToken('module-list')->plainTextToken)->getJson('/api/v1/approval-modules')
            ->assertOk()->assertJsonPath('data.2.key', 'apresentacoes')->assertJsonPath('data.2.label', 'Apresentações');

        $this->post(route('demands.store'), [
            'title' => 'Apresentação institucional',
            'brief' => 'Revisar a apresentação para o time comercial.',
            'module_key' => $module->key,
            'tasks' => [['title' => 'Revisar conteúdo', 'assignee_id' => $professional->id]],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $demand = Demand::query()->firstOrFail();
        $this->assertSame($module->key, $demand->module_key);
        $this->assertSame('Apresentações', $demand->moduleDisplayLabel());
        $this->assertSame(1, $demand->module_version);
        $this->get(route('demands.show', $demand))->assertOk()->assertSee('Tipo: Apresentações');

        $this->patch(route('approval-modules.toggle', $module))->assertRedirect()->assertSessionHasNoErrors();
        $this->assertFalse($module->fresh()->is_active);
        $this->assertSame($manager->id, $module->fresh()->updated_by);
        $this->get(route('demands.create'))->assertOk()->assertDontSee('Apresentações');
        $this->get(route('demands.show', $demand))->assertOk()->assertSee('Tipo: Apresentações');
    }

    public function test_custom_module_isolation_and_management_permissions_are_enforced(): void
    {
        [$organization, $manager] = $this->workspace();
        $module = DemandModuleDefinition::create([
            'organization_id' => $organization->id,
            'created_by' => $manager->id,
            'key' => 'apresentacoes',
            'label' => 'Apresentações',
            'description' => 'Revisão de apresentações comerciais.',
            'is_active' => true,
        ]);
        [, $outsideManager] = $this->workspace('outra-agencia');
        $professional = User::factory()->create(['organization_id' => $organization->id, 'role' => UserRole::Professional, 'is_active' => true]);

        $this->actingAs($outsideManager)->get(route('approval-modules.index'))->assertOk()->assertDontSee('key="apresentacoes"', false);
        $this->withToken($outsideManager->createToken('module-list')->plainTextToken)->getJson('/api/v1/approval-modules')
            ->assertOk()->assertJsonMissing(['key' => 'apresentacoes']);
        $this->patch(route('approval-modules.toggle', $module))->assertNotFound();
        $this->actingAs($professional)->get(route('approval-modules.index'))->assertForbidden();
        $this->actingAs($professional)->post(route('approval-modules.store'), [
            'label' => 'Outro tipo',
            'description' => 'Descrição de teste.',
        ])->assertForbidden();
        $this->app['auth']->forgetGuards();
        $this->withToken($outsideManager->createToken('foreign-module')->plainTextToken)->postJson('/api/v1/demands', [
            'title' => 'Demanda de outra agência',
            'brief' => 'Teste de isolamento do catálogo.',
            'module_key' => 'apresentacoes',
            'tasks' => [['title' => 'Tarefa', 'assignee_id' => User::query()->where('organization_id', $organization->id)->where('role', UserRole::Professional->value)->value('id')]],
        ])->assertUnprocessable()->assertJsonValidationErrors('module_key');
        $this->assertSame(0, Demand::query()->count());
    }

    public function test_custom_module_key_cannot_collide_with_builtin_or_duplicate_keys(): void
    {
        [$organization, $manager] = $this->workspace();
        DemandModuleDefinition::create([
            'organization_id' => $organization->id,
            'created_by' => $manager->id,
            'key' => 'apresentacoes',
            'label' => 'Apresentações',
            'description' => 'Revisão de apresentações comerciais.',
            'is_active' => true,
        ]);

        $this->actingAs($manager)->from(route('approval-modules.index'))->post(route('approval-modules.store'), [
            'label' => 'Tipo duplicado',
            'description' => 'Descrição de teste.',
            'key' => 'apresentacoes',
        ])->assertSessionHasErrors('key');

        $this->from(route('approval-modules.index'))->post(route('approval-modules.store'), [
            'label' => 'Tipo reservado',
            'description' => 'Descrição de teste.',
            'key' => 'website_review',
        ])->assertSessionHasErrors('key');

        $this->assertSame(1, DemandModuleDefinition::query()->count());
    }

    public function test_api_demand_creation_uses_a_custom_module_with_its_saved_label(): void
    {
        [$organization, $manager, $professional] = $this->workspace();
        DemandModuleDefinition::create([
            'organization_id' => $organization->id,
            'created_by' => $manager->id,
            'key' => 'apresentacoes',
            'label' => 'Apresentações',
            'description' => 'Revisão de apresentações comerciais.',
            'is_active' => true,
        ]);

        $response = $this->withToken($manager->createToken('module-test')->plainTextToken)->postJson('/api/v1/demands', [
            'title' => 'Apresentação de teste',
            'brief' => 'Revisar material comercial.',
            'module_key' => 'apresentacoes',
            'tasks' => [['title' => 'Revisar slides', 'assignee_id' => $professional->id]],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.module.key', 'apresentacoes')
            ->assertJsonPath('data.module.label', 'Apresentações')
            ->assertJsonPath('data.module.version', 1);
    }

    private function workspace(string $slug = 'mix7-modules'): array
    {
        $organization = Organization::create(['name' => ucfirst($slug), 'slug' => $slug]);
        $manager = User::factory()->create(['organization_id' => $organization->id, 'role' => UserRole::AgencyOwner, 'is_active' => true]);
        $professional = User::factory()->create(['organization_id' => $organization->id, 'role' => UserRole::Professional, 'is_active' => true]);

        return [$organization, $manager, $professional];
    }
}
