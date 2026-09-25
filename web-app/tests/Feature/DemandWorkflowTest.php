<?php

namespace Tests\Feature;

use App\Enums\DemandStatus;
use App\Enums\TaskStatus;
use App\Enums\UserRole;
use App\Models\Demand;
use App\Models\DemandTask;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DemandWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_creates_demand_tasks_and_authored_history_atomically(): void
    {
        [$organization, $manager, $professional] = $this->team();
        $this->actingAs($manager);
        $this->assertAuthenticatedAs($manager);
        $this->assertTrue($manager->is_active);
        $response = $this
            ->post(route('demands.store'), [
                'title' => 'Site institucional',
                'brief' => 'Apresentar serviços para novos clientes.',
                'tasks' => [
                    ['title' => 'Organizar referências', 'assignee_id' => $professional->id, 'estimate_minutes' => '90'],
                    ['title' => 'Rascunhar páginas', 'assignee_id' => $professional->id, 'estimate_minutes' => ''],
                ],
            ]);
        $response->assertRedirect();

        $demand = Demand::firstOrFail();
        $this->assertSame($organization->id, $demand->organization_id);
        $this->assertSame($manager->id, $demand->created_by);
        $this->assertSame(DemandStatus::Received, $demand->status);
        $this->assertSame(2, $demand->tasks()->count());
        $this->assertSame(3, $demand->events()->count());
        $this->assertDatabaseHas('demand_events', ['demand_id' => $demand->id, 'actor_id' => $manager->id, 'event_type' => 'task_assigned']);
    }

    public function test_task_cannot_be_assigned_to_a_user_from_another_organization(): void
    {
        [$organization, $manager] = $this->team();
        [, , $outsideProfessional] = $this->team('outside');

        $this->actingAs($manager)->from(route('demands.create'))
            ->post(route('demands.store'), [
                'title' => 'Site institucional',
                'brief' => 'Briefing válido.',
                'tasks' => [['title' => 'Planejar página', 'assignee_id' => $outsideProfessional->id]],
            ])
            ->assertSessionHasErrors('tasks.0.assignee_id');

        $this->assertDatabaseCount('demands', 0);
        $this->assertSame(2, User::where('organization_id', $organization->id)->where('role', UserRole::Professional->value)->count());
    }

    public function test_professional_only_sees_demands_and_tasks_assigned_to_them(): void
    {
        [$organization, $manager, $professional, $colleague] = $this->team();
        $colleague->update(['name' => 'Colega privado']);
        $demand = $this->demand($organization, $manager);
        $ownTask = $this->task($demand, $professional, $manager, 'Fazer wireframe');
        $this->task($demand, $colleague, $manager, 'Revisar conteúdo');

        $this->actingAs($professional)->get(route('demands.index'))
            ->assertOk()
            ->assertSee('<strong>1</strong>', false)
            ->assertSee('0 concluídas');

        $response = $this->actingAs($professional)->get(route('demands.show', $demand))->assertOk();
        $response->assertSee('Fazer wireframe')->assertDontSee('Revisar conteúdo')->assertDontSee('Colega privado');
        $this->patch(route('demand-tasks.status', $ownTask), ['status' => TaskStatus::InProgress->value])->assertRedirect();
        $this->assertDatabaseHas('demand_tasks', ['id' => $ownTask->id, 'status' => TaskStatus::InProgress->value]);

        $colleagueTask = $demand->tasks()->where('assigned_to', $colleague->id)->firstOrFail();
        $this->patch(route('demand-tasks.status', $colleagueTask), ['status' => TaskStatus::InProgress->value])->assertForbidden();
        $this->get(route('demands.show', $this->demand($organization, $manager)))->assertForbidden();
    }

    public function test_demand_cannot_enter_internal_review_with_open_tasks_or_skip_steps(): void
    {
        [$organization, $manager, $professional] = $this->team();
        $demand = $this->demand($organization, $manager);
        $this->task($demand, $professional, $manager, 'Fazer wireframe');
        $this->actingAs($manager)->patch(route('demands.status', $demand), ['status' => DemandStatus::Planning->value])->assertRedirect();
        $this->patch(route('demands.status', $demand), ['status' => DemandStatus::ClientApproval->value])->assertSessionHasErrors('status');
        $this->patch(route('demands.status', $demand), ['status' => DemandStatus::InProgress->value])->assertRedirect();
        $this->patch(route('demands.status', $demand), ['status' => DemandStatus::InternalReview->value])->assertSessionHasErrors('status');
    }

    public function test_demands_api_requires_token_and_filters_tasks_for_professional(): void
    {
        [$organization, $manager, $professional, $colleague] = $this->team();
        $demand = $this->demand($organization, $manager);
        $this->task($demand, $professional, $manager, 'Executar página');
        $this->task($demand, $colleague, $manager, 'Revisar texto');

        $this->getJson('/api/v1/demands')->assertUnauthorized();
        $token = $professional->createToken('desktop-check')->plainTextToken;
        $this->withToken($token)->getJson('/api/v1/demands')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonCount(1, 'data.0.tasks')
            ->assertJsonPath('data.0.tasks.0.title', 'Executar página');
    }

    public function test_agency_owner_can_create_professional_account_inside_their_organization(): void
    {
        [$organization, $owner] = $this->team();

        $this->actingAs($owner)->post(route('team.store'), [
            'name' => 'Nova Profissional',
            'email' => 'NOVA@EXEMPLO.COM',
            'password' => 'senha-segura-123',
        ])->assertRedirect(route('team.index'));

        $professional = User::where('email', 'nova@exemplo.com')->firstOrFail();
        $this->assertSame($organization->id, $professional->organization_id);
        $this->assertSame(UserRole::Professional, $professional->role);
        $this->assertTrue($professional->is_active);
        $this->assertTrue(Hash::check('senha-segura-123', $professional->password));
        $this->actingAs($professional)->get(route('team.index'))->assertForbidden();

        $manager = User::factory()->create([
            'organization_id' => $organization->id,
            'role' => UserRole::MarketingManager,
            'is_active' => true,
        ]);
        $this->actingAs($manager)->get(route('team.index'))->assertForbidden();
        $this->post(route('team.store'), [
            'name' => 'Conta bloqueada',
            'email' => 'bloqueada@example.test',
            'password' => 'senha-segura-123',
        ])->assertForbidden();
    }

    public function test_owner_cannot_add_tasks_during_client_approval_or_after_delivery(): void
    {
        [$organization, $owner] = $this->team();
        $demand = $this->demand($organization, $owner);
        $demand->update(['status' => DemandStatus::ClientApproval]);

        $this->actingAs($owner)->post(route('demand-tasks.store', $demand), [
            'title' => 'Alteração fora de hora',
            'assignee_id' => User::where('organization_id', $organization->id)->where('role', UserRole::Professional->value)->value('id'),
        ])->assertSessionHasErrors('title');

        $this->assertDatabaseCount('demand_tasks', 0);
    }

    /** @return array{Organization, User, User, User} */
    private function team(string $slug = 'mix7'): array
    {
        $organization = Organization::create(['name' => ucfirst($slug), 'slug' => $slug]);
        $manager = User::factory()->create(['organization_id' => $organization->id, 'role' => UserRole::AgencyOwner, 'is_active' => true]);
        $professional = User::factory()->create(['organization_id' => $organization->id, 'role' => UserRole::Professional, 'is_active' => true]);
        $colleague = User::factory()->create(['organization_id' => $organization->id, 'role' => UserRole::Professional, 'is_active' => true]);

        return [$organization, $manager, $professional, $colleague];
    }

    private function demand(Organization $organization, User $creator): Demand
    {
        return Demand::create([
            'organization_id' => $organization->id,
            'created_by' => $creator->id,
            'title' => 'Site institucional',
            'brief' => 'Briefing sintético de teste.',
            'status' => DemandStatus::Received,
        ]);
    }

    private function task(Demand $demand, User $assignee, User $creator, string $title): DemandTask
    {
        return $demand->tasks()->create([
            'organization_id' => $demand->organization_id,
            'created_by' => $creator->id,
            'assigned_to' => $assignee->id,
            'title' => $title,
            'status' => TaskStatus::Todo,
        ]);
    }
}
