<?php

namespace Tests\Feature;

use App\Enums\DemandStatus;
use App\Enums\TaskStatus;
use App\Enums\UserRole;
use App\Models\Demand;
use App\Models\DemandTask;
use App\Models\Organization;
use App\Models\TaskTimeEntry;
use App\Models\TeamInvitation;
use App\Models\User;
use App\Notifications\TeamInvitationNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
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

    public function test_manager_can_set_and_clear_task_schedule_and_history_records_actor(): void
    {
        [$organization, $manager, $professional] = $this->team();
        $demand = $this->demand($organization, $manager);
        $task = $this->task($demand, $professional, $manager, 'Produzir páginas');

        $this->actingAs($manager)->patch(route('demand-tasks.schedule', $task), [
            'planned_start_on' => '2026-10-02',
            'planned_due_on' => '2026-10-07',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame('2026-10-02', $task->fresh()->planned_start_on->format('Y-m-d'));
        $this->assertSame('2026-10-07', $task->fresh()->planned_due_on->format('Y-m-d'));
        $this->get(route('demands.show', $demand))
            ->assertOk()
            ->assertSee('Cronograma')
            ->assertSee('Período')
            ->assertSee('02/10/2026')
            ->assertSee('07/10/2026');
        $this->assertDatabaseHas('demand_events', [
            'task_id' => $task->id,
            'actor_id' => $manager->id,
            'event_type' => 'task_schedule_updated',
            'summary' => $manager->name.' atualizou as datas de "Produzir páginas": 02/10/2026 a 07/10/2026',
        ]);

        $this->patch(route('demand-tasks.schedule', $task), [
            'planned_start_on' => '',
            'planned_due_on' => '',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertNull($task->fresh()->planned_start_on);
        $this->assertNull($task->fresh()->planned_due_on);
    }

    public function test_invalid_schedule_and_professional_schedule_edits_are_rejected(): void
    {
        [$organization, $manager, $professional] = $this->team();
        $demand = $this->demand($organization, $manager);
        $task = $this->task($demand, $professional, $manager, 'Editar conteúdo');

        $this->actingAs($manager)->patch(route('demand-tasks.schedule', $task), [
            'planned_start_on' => '2026-10-08',
            'planned_due_on' => '2026-10-07',
        ])->assertSessionHasErrors('planned_due_on');
        $this->assertNull($task->fresh()->planned_start_on);

        $this->actingAs($professional)->patch(route('demand-tasks.schedule', $task), [
            'planned_start_on' => '2026-10-02',
            'planned_due_on' => '2026-10-07',
        ])->assertForbidden();
        $this->assertNull($task->fresh()->planned_start_on);
    }

    public function test_demand_cronograma_only_contains_the_professionals_own_tasks(): void
    {
        [$organization, $manager, $professional, $colleague] = $this->team();
        $demand = $this->demand($organization, $manager);
        $ownTask = $this->task($demand, $professional, $manager, 'Minha página');
        $privateTask = $this->task($demand, $colleague, $manager, 'Página privada da colega');
        $ownTask->update(['planned_start_on' => '2026-10-02']);
        $privateTask->update(['planned_start_on' => '2026-10-03']);

        $this->actingAs($professional)->get(route('demands.show', $demand))
            ->assertOk()
            ->assertSee('Minha página')
            ->assertDontSee('Página privada da colega')
            ->assertDontSee('task_schedule_updated');
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

        Notification::fake();
        $this->actingAs($owner)->post(route('team-invitations.store'), [
            'name' => 'Nova Profissional',
            'email' => 'NOVA@EXEMPLO.COM',
            'role' => UserRole::Professional->value,
        ])->assertRedirect(route('team.index'));

        $invitation = TeamInvitation::where('email', 'nova@exemplo.com')->firstOrFail();
        $this->assertNotSame('senha-segura-123', $invitation->token_hash);
        $this->assertDatabaseMissing('users', ['email' => 'nova@exemplo.com']);
        $token = null;
        Notification::assertSentOnDemand(TeamInvitationNotification::class, function (TeamInvitationNotification $notification) use (&$token): bool {
            $token = $notification->token;

            return true;
        });
        $this->post(route('team-invitations.accept', $token), [
            'password' => 'senha-segura-123',
            'password_confirmation' => 'senha-segura-123',
        ])->assertRedirect(route('dashboard'));
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
        $this->post(route('team-invitations.store'), [
            'name' => 'Conta bloqueada',
            'email' => 'bloqueada@example.test',
            'role' => UserRole::Professional->value,
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

    public function test_professional_can_start_pause_and_resume_persisted_task_time(): void
    {
        [$organization, $manager, $professional] = $this->team();
        $demand = $this->demand($organization, $manager);
        $task = $this->task($demand, $professional, $manager, 'Construir a página');

        $this->actingAs($professional)->post(route('demand-tasks.timer.start', $task))->assertRedirect();
        $task->refresh();
        $this->assertSame(TaskStatus::InProgress, $task->status);
        $this->assertDatabaseHas('task_time_entries', ['task_id' => $task->id, 'user_id' => $professional->id, 'ended_at' => null]);
        $this->get(route('demands.show', $demand))->assertOk()->assertSee('Cronômetro ativo na tarefa Construir a página');

        $this->travel(75)->seconds();
        $this->post(route('demand-tasks.timer.pause', $task))->assertRedirect();
        $entry = TaskTimeEntry::query()->where('task_id', $task->id)->firstOrFail();
        $this->assertNotNull($entry->ended_at);
        $this->assertSame(TaskStatus::Paused, $task->fresh()->status);

        $this->travel(30)->seconds();
        $this->post(route('demand-tasks.timer.start', $task))->assertRedirect();
        $this->assertDatabaseCount('task_time_entries', 2);
        $this->assertDatabaseHas('task_time_entries', ['task_id' => $task->id, 'user_id' => $professional->id, 'ended_at' => null]);
    }

    public function test_professional_cannot_run_two_timers_or_track_a_colleagues_task(): void
    {
        [$organization, $manager, $professional, $colleague] = $this->team();
        $demand = $this->demand($organization, $manager);
        $ownTask = $this->task($demand, $professional, $manager, 'Minha tarefa');
        $otherTask = $this->task($demand, $professional, $manager, 'Outra tarefa');
        $colleagueTask = $this->task($demand, $colleague, $manager, 'Tarefa do colega');

        $this->actingAs($professional)->post(route('demand-tasks.timer.start', $ownTask))->assertRedirect();
        $this->post(route('demand-tasks.timer.start', $otherTask))->assertSessionHasErrors('timer');
        $this->post(route('demand-tasks.timer.start', $colleagueTask))->assertForbidden();
        $this->assertDatabaseCount('task_time_entries', 1);
    }

    public function test_completing_task_closes_its_active_time_interval(): void
    {
        [$organization, $manager, $professional] = $this->team();
        $demand = $this->demand($organization, $manager);
        $task = $this->task($demand, $professional, $manager, 'Concluir a página');
        $this->actingAs($professional)->post(route('demand-tasks.timer.start', $task));

        $this->travel(45)->seconds();
        $this->patch(route('demand-tasks.status', $task), ['status' => TaskStatus::Completed->value])->assertRedirect();

        $this->assertNotNull(TaskTimeEntry::query()->firstOrFail()->ended_at);
        $this->assertSame(TaskStatus::Completed, $task->fresh()->status);
        $this->assertNotNull($task->fresh()->completed_at);
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
