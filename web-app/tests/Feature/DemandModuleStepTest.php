<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Demand;
use App\Models\DemandModuleDefinition;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemandModuleStepTest extends TestCase
{
    use RefreshDatabase;

    public function test_custom_module_steps_are_copied_to_each_new_demand_and_old_steps_stay_unchanged(): void
    {
        [$organization, $manager, $professional] = $this->workspace();
        $module = $this->module($organization, $manager);
        $oldDemand = $this->createDemand($manager, $professional, $module);

        $this->assertSame(['briefing', 'revisao'], $oldDemand->moduleSteps()->pluck('key')->all());
        $this->put(route('approval-modules.fields', $module), [
            'workflow_steps' => [
                ['key' => 'roteiro', 'label' => 'Aprovar roteiro'],
            ],
        ])->assertRedirect(route('approval-modules.index'))->assertSessionHasNoErrors();
        $newDemand = $this->createDemand($manager, $professional, $module);

        $this->assertSame(1, $oldDemand->fresh()->module_version);
        $this->assertSame(['briefing', 'revisao'], $oldDemand->moduleSteps()->pluck('key')->all());
        $this->assertSame(2, $newDemand->module_version);
        $this->assertSame([['key' => 'roteiro', 'label' => 'Aprovar roteiro']], $newDemand->moduleSteps()->get(['key', 'label'])->map(fn ($step) => ['key' => $step->key, 'label' => $step->label])->all());
    }

    public function test_management_can_complete_and_reopen_module_steps_with_audited_author_and_time(): void
    {
        [$organization, $manager, $professional] = $this->workspace();
        $demand = $this->createDemand($manager, $professional, $this->module($organization, $manager));
        $step = $demand->moduleSteps()->firstOrFail();

        $this->actingAs($manager)->patch(route('demands.module-steps.update', [$demand, $step->key]), ['completed' => '1'])
            ->assertRedirect(route('demands.show', $demand))->assertSessionHasNoErrors();
        $step->refresh();
        $this->assertSame($manager->id, $step->completed_by);
        $this->assertNotNull($step->completed_at);
        $this->assertDatabaseHas('demand_events', ['demand_id' => $demand->id, 'actor_id' => $manager->id, 'event_type' => 'module_step_completed']);

        $this->actingAs($manager)->patch(route('demands.module-steps.update', [$demand, $step->key]), ['completed' => '0'])
            ->assertRedirect(route('demands.show', $demand))->assertSessionHasNoErrors();
        $step->refresh();
        $this->assertNull($step->completed_by);
        $this->assertNull($step->completed_at);
        $this->assertDatabaseHas('demand_events', ['demand_id' => $demand->id, 'actor_id' => $manager->id, 'event_type' => 'module_step_reopened']);
    }

    public function test_module_steps_are_internal_and_only_management_can_change_them(): void
    {
        [$organization, $manager, $professional, $client] = $this->workspace();
        $demand = $this->createDemand($manager, $professional, $this->module($organization, $manager), $client);
        $step = $demand->moduleSteps()->firstOrFail();

        $this->actingAs($professional)->patch(route('demands.module-steps.update', [$demand, $step->key]), ['completed' => true])->assertForbidden();
        $this->actingAs($client)->get(route('demands.show', $demand))->assertOk()->assertDontSee('Etapas de Apresentações')->assertDontSee('Revisar briefing');
        $this->app['auth']->forgetGuards();
        $this->withToken($client->createToken('module-step-client')->plainTextToken)->getJson('/api/v1/demands/'.$demand->id)
            ->assertOk()->assertJsonMissingPath('data.module_steps');
    }

    public function test_internal_api_exposes_step_progress_and_management_can_update_it(): void
    {
        [$organization, $manager, $professional] = $this->workspace();
        $demand = $this->createDemand($manager, $professional, $this->module($organization, $manager));
        $step = $demand->moduleSteps()->firstOrFail();
        $token = $manager->createToken('module-step-manager')->plainTextToken;

        $this->withToken($token)->getJson('/api/v1/approval-modules')
            ->assertOk()->assertJsonPath('data.2.workflow_steps.0.label', 'Revisar briefing');
        $this->withToken($token)->getJson('/api/v1/demands/'.$demand->id)
            ->assertOk()->assertJsonPath('data.module_steps.0.completed', false)->assertJsonPath('data.module_steps.0.label', 'Revisar briefing');
        $this->withToken($token)->patchJson('/api/v1/demands/'.$demand->id.'/module-steps/'.$step->key, ['completed' => true])
            ->assertOk()->assertJsonPath('data.completed', true)->assertJsonPath('data.completed_by.id', $manager->id);
    }

    public function test_invalid_or_foreign_module_step_cannot_be_updated(): void
    {
        [$organization, $manager, $professional] = $this->workspace();
        $demand = $this->createDemand($manager, $professional, $this->module($organization, $manager));
        $this->actingAs($manager)->patch(route('demands.module-steps.update', [$demand, 'not_a_step']), ['completed' => true])->assertNotFound();
        $this->actingAs($professional)->patch(route('demands.module-steps.update', [$demand, 'briefing']), ['completed' => true])->assertForbidden();
        $this->assertDatabaseCount('demand_module_steps', 2);
    }

    public function test_module_step_configuration_rejects_duplicate_keys_and_more_than_twenty_steps(): void
    {
        [$organization, $manager] = $this->workspace();
        $module = $this->module($organization, $manager);
        $this->actingAs($manager)->put(route('approval-modules.fields', $module), [
            'workflow_steps' => [
                ['key' => 'revisao', 'label' => 'Revisão um'],
                ['key' => 'revisao', 'label' => 'Revisão dois'],
            ],
        ])->assertSessionHasErrors('workflow_steps.1.key');

        $tooMany = collect(range(1, 21))->map(fn (int $n): array => ['key' => 'etapa_'.$n, 'label' => 'Etapa '.$n])->all();
        $this->put(route('approval-modules.fields', $module), ['workflow_steps' => $tooMany])->assertSessionHasErrors('workflow_steps');
        $this->assertSame(['briefing', 'revisao'], array_column($module->fresh()->workflow_steps, 'key'));
    }

    private function workspace(): array
    {
        $organization = Organization::create(['name' => 'Mix7', 'slug' => 'mix7-module-steps']);
        $manager = User::factory()->create(['organization_id' => $organization->id, 'role' => UserRole::AgencyOwner, 'is_active' => true]);
        $professional = User::factory()->create(['organization_id' => $organization->id, 'role' => UserRole::Professional, 'is_active' => true]);
        $client = User::factory()->create(['organization_id' => $organization->id, 'role' => UserRole::Client, 'is_active' => true]);

        return [$organization, $manager, $professional, $client];
    }

    private function module(Organization $organization, User $manager): DemandModuleDefinition
    {
        return DemandModuleDefinition::create([
            'organization_id' => $organization->id,
            'created_by' => $manager->id,
            'key' => 'apresentacoes',
            'label' => 'Apresentações',
            'description' => 'Revisão de apresentações de clientes.',
            'config_version' => 1,
            'fields' => [],
            'workflow_steps' => [
                ['key' => 'briefing', 'label' => 'Revisar briefing'],
                ['key' => 'revisao', 'label' => 'Conferir apresentação'],
            ],
            'is_active' => true,
        ]);
    }

    private function createDemand(User $manager, User $professional, DemandModuleDefinition $module, ?User $client = null): Demand
    {
        $this->actingAs($manager)->post(route('demands.store'), [
            'title' => 'Demanda de apresentação',
            'brief' => 'Preparar apresentação fictícia.',
            'module_key' => $module->key,
            'client_user_id' => $client?->id,
            'tasks' => [['title' => 'Revisar slides', 'assignee_id' => $professional->id]],
        ])->assertRedirect()->assertSessionHasNoErrors();

        return Demand::query()->latest('id')->firstOrFail();
    }
}
