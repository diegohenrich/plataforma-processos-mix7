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
        $client = User::factory()->create(['organization_id' => $organization->id, 'role' => UserRole::Client, 'is_active' => true]);

        $this->actingAs($manager)->post(route('approval-modules.store'), [
            'label' => 'Apresentações',
            'description' => 'Revisão de apresentações comerciais.',
            'fields' => [
                ['key' => 'formato', 'label' => 'Formato', 'type' => 'select', 'required' => '1', 'options' => "PDF\nPPTX"],
                ['key' => 'link_referencia', 'label' => 'Link de referência', 'type' => 'url', 'options' => ''],
            ],
        ])->assertRedirect(route('approval-modules.index'))->assertSessionHasNoErrors();

        $module = DemandModuleDefinition::query()->where('organization_id', $organization->id)->firstOrFail();
        $this->assertSame('apresentacoes', $module->key);
        $this->assertSame($manager->id, $module->created_by);
        $this->assertSame(1, $module->config_version);
        $this->assertSame(['PDF', 'PPTX'], $module->fields[0]['options']);
        $this->get(route('demands.create'))->assertOk()->assertSee('Apresentações');
        $this->withToken($manager->createToken('module-list')->plainTextToken)->getJson('/api/v1/approval-modules')
            ->assertOk()->assertJsonPath('data.2.key', 'apresentacoes')->assertJsonPath('data.2.label', 'Apresentações')
            ->assertJsonPath('data.2.version', 1)->assertJsonPath('data.2.fields.0.key', 'formato');

        $this->post(route('demands.store'), [
            'title' => 'Apresentação institucional',
            'brief' => 'Revisar a apresentação para o time comercial.',
            'module_key' => $module->key,
            'client_user_id' => $client->id,
            'module_fields_data' => ['formato' => 'PDF', 'link_referencia' => 'https://mix7.com.br/referencia'],
            'tasks' => [['title' => 'Revisar conteúdo', 'assignee_id' => $professional->id]],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $demand = Demand::query()->firstOrFail();
        $this->assertSame($module->key, $demand->module_key);
        $this->assertSame('Apresentações', $demand->moduleDisplayLabel());
        $this->assertSame(1, $demand->module_version);
        $this->assertSame('PDF', $demand->module_fields_data['formato']);
        $this->get(route('demands.show', $demand))->assertOk()->assertSee('Tipo: Apresentações')->assertSee('Informações de Apresentações')->assertSee('mix7.com.br/referencia');
        $this->actingAs($client)->get(route('demands.show', $demand))->assertOk()->assertDontSee('Informações de Apresentações')->assertDontSee('mix7.com.br/referencia');
        $this->withToken($client->createToken('module-client')->plainTextToken)->getJson('/api/v1/demands/'.$demand->id)
            ->assertForbidden();
        $this->actingAs($manager);

        $this->put(route('approval-modules.fields', $module), [
            'fields' => [['key' => 'campanha', 'label' => 'Campanha', 'type' => 'text', 'required' => '1', 'options' => '']],
        ])->assertRedirect(route('approval-modules.index'))->assertSessionHasNoErrors();
        $this->assertSame(2, $module->fresh()->config_version);
        $this->assertSame(1, $demand->fresh()->module_version);
        $this->assertSame('formato', $demand->fresh()->module_fields_schema[0]['key']);
        $this->get(route('demands.create'))->assertOk()->assertSee('campanha', false);
        $this->post(route('demands.store'), [
            'title' => 'Apresentação de nova campanha',
            'brief' => 'Preparar apresentação para a campanha atual.',
            'module_key' => $module->key,
            'module_fields_data' => ['campanha' => 'Institucional'],
            'tasks' => [['title' => 'Revisar material', 'assignee_id' => $professional->id]],
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(2, Demand::query()->latest('id')->firstOrFail()->module_version);

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
            'config_version' => 1,
            'fields' => [['key' => 'formato', 'label' => 'Formato', 'type' => 'select', 'required' => true, 'options' => ['PDF', 'PPTX']]],
            'is_active' => true,
        ]);
        [, $outsideManager] = $this->workspace('outra-agencia');
        $professional = User::factory()->create(['organization_id' => $organization->id, 'role' => UserRole::Professional, 'is_active' => true]);

        $this->actingAs($outsideManager)->get(route('approval-modules.index'))->assertOk()->assertDontSee('key="apresentacoes"', false);
        $this->withToken($outsideManager->createToken('module-list')->plainTextToken)->getJson('/api/v1/approval-modules')
            ->assertOk()->assertJsonMissing(['key' => 'apresentacoes']);
        $this->patch(route('approval-modules.toggle', $module))->assertNotFound();
        $this->put(route('approval-modules.fields', $module), ['fields' => []])->assertNotFound();
        $this->actingAs($professional)->get(route('approval-modules.index'))->assertForbidden();
        $this->put(route('approval-modules.fields', $module), ['fields' => []])->assertForbidden();
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

    public function test_module_fields_are_validated_and_cannot_be_injected_from_another_module(): void
    {
        [$organization, $manager, $professional] = $this->workspace();
        DemandModuleDefinition::create([
            'organization_id' => $organization->id,
            'created_by' => $manager->id,
            'key' => 'apresentacoes',
            'label' => 'Apresentações',
            'description' => 'Revisão de apresentações comerciais.',
            'config_version' => 1,
            'fields' => [['key' => 'formato', 'label' => 'Formato', 'type' => 'select', 'required' => true, 'options' => ['PDF', 'PPTX']]],
            'is_active' => true,
        ]);

        $this->actingAs($manager)->from(route('demands.create'))->post(route('demands.store'), [
            'title' => 'Apresentação sem formato',
            'brief' => 'O campo obrigatório precisa ser enviado.',
            'module_key' => 'apresentacoes',
            'tasks' => [['title' => 'Revisar arquivo', 'assignee_id' => $professional->id]],
        ])->assertRedirect(route('demands.create'))->assertSessionHasErrors('module_fields_data.formato');

        $this->from(route('demands.create'))->post(route('demands.store'), [
            'title' => 'Apresentação com tipo inválido',
            'brief' => 'A opção deve fazer parte do módulo.',
            'module_key' => 'apresentacoes',
            'module_fields_data' => ['formato' => 'DOCX'],
            'tasks' => [['title' => 'Revisar arquivo', 'assignee_id' => $professional->id]],
        ])->assertRedirect(route('demands.create'))->assertSessionHasErrors('module_fields_data.formato');

        $this->from(route('demands.create'))->post(route('demands.store'), [
            'title' => 'Injetar campo desconhecido',
            'brief' => 'Campos de outros módulos não devem ser aceitos.',
            'module_key' => 'apresentacoes',
            'module_fields_data' => ['formato' => 'PDF', 'senha' => 'secreto'],
            'tasks' => [['title' => 'Revisar arquivo', 'assignee_id' => $professional->id]],
        ])->assertRedirect(route('demands.create'))->assertSessionHasErrors('module_fields_data');

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

        $this->from(route('approval-modules.index'))->post(route('approval-modules.store'), [
            'label' => 'Tipo com lista vazia',
            'description' => 'Uma lista precisa ter opções selecionáveis.',
            'fields' => [['key' => 'categoria', 'label' => 'Categoria', 'type' => 'select', 'required' => '1', 'options' => "\n  \n"]],
        ])->assertSessionHasErrors('fields.0.options');

        $this->assertSame(1, DemandModuleDefinition::query()->count());
    }

    public function test_marketing_manager_can_manage_their_organizations_module_catalog(): void
    {
        [$organization] = $this->workspace();
        $marketingManager = User::factory()->create([
            'organization_id' => $organization->id,
            'role' => UserRole::MarketingManager,
            'is_active' => true,
        ]);

        $this->actingAs($marketingManager)->post(route('approval-modules.store'), [
            'label' => 'Materiais comerciais',
            'description' => 'Revisão de materiais comerciais.',
        ])->assertRedirect(route('approval-modules.index'))->assertSessionHasNoErrors();

        $this->assertDatabaseHas('demand_module_definitions', [
            'organization_id' => $organization->id,
            'created_by' => $marketingManager->id,
            'key' => 'materiais_comerciais',
        ]);
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
            'config_version' => 1,
            'fields' => [['key' => 'formato', 'label' => 'Formato', 'type' => 'select', 'required' => true, 'options' => ['PDF', 'PPTX']]],
            'is_active' => true,
        ]);

        $response = $this->withToken($manager->createToken('module-test')->plainTextToken)->postJson('/api/v1/demands', [
            'title' => 'Apresentação de teste',
            'brief' => 'Revisar material comercial.',
            'module_key' => 'apresentacoes',
            'module_fields_data' => ['formato' => 'PDF'],
            'tasks' => [['title' => 'Revisar slides', 'assignee_id' => $professional->id]],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.module.key', 'apresentacoes')
            ->assertJsonPath('data.module.label', 'Apresentações')
            ->assertJsonPath('data.module.version', 1)
            ->assertJsonPath('data.module_fields.data.formato', 'PDF');
        $this->withToken($manager->createToken('module-demand-read')->plainTextToken)->getJson('/api/v1/demands/'.$response->json('data.id'))
            ->assertOk()->assertJsonPath('data.module_fields.data.formato', 'PDF');
    }

    private function workspace(string $slug = 'mix7-modules'): array
    {
        $organization = Organization::create(['name' => ucfirst($slug), 'slug' => $slug]);
        $manager = User::factory()->create(['organization_id' => $organization->id, 'role' => UserRole::AgencyOwner, 'is_active' => true]);
        $professional = User::factory()->create(['organization_id' => $organization->id, 'role' => UserRole::Professional, 'is_active' => true]);

        return [$organization, $manager, $professional];
    }
}
