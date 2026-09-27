<?php

namespace Tests\Feature;

use App\Enums\DemandStatus;
use App\Enums\TaskStatus;
use App\Enums\UserRole;
use App\Models\Demand;
use App\Models\DemandEvent;
use App\Models\DemandTask;
use App\Models\Organization;
use App\Models\TaskTimeEntry;
use App\Models\TeamInvitation;
use App\Models\User;
use App\Notifications\TeamInvitationNotification;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;
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
                'intake_source' => 'WhatsApp',
                'brief_author_id' => $professional->id,
                'module_key' => 'website_review',
                'tasks' => [
                    ['title' => 'Organizar referências', 'assignee_id' => $professional->id, 'estimate_minutes' => '90'],
                    ['title' => 'Rascunhar páginas', 'assignee_id' => $professional->id, 'estimate_minutes' => ''],
                ],
            ]);
        $response->assertRedirect();

        $demand = Demand::firstOrFail();
        $this->assertSame($organization->id, $demand->organization_id);
        $this->assertSame($manager->id, $demand->created_by);
        $this->assertSame('WhatsApp', $demand->intake_source);
        $this->assertSame($professional->id, $demand->brief_author_id);
        $this->assertSame(DemandStatus::Received, $demand->status);
        $this->assertSame(2, $demand->tasks()->count());
        $this->assertSame(3, $demand->events()->count());
        $this->assertDatabaseHas('demand_events', ['demand_id' => $demand->id, 'actor_id' => $manager->id, 'event_type' => 'task_assigned']);
        $response = $this->get(route('demands.show', $demand))->assertOk()->assertSee('Tipo: Revisão de site')->assertSee('configuração v1')->assertSee('Como o pedido chegou')->assertSee('WhatsApp')->assertSee($professional->name)->assertSee('Quem preparou o briefing');
        $response->assertSee('Atribuída por '.$manager->name);
        $response->assertSee('<details class="panel history-panel">', false)
            ->assertSee('3 registros')
            ->assertSee($demand->events()->firstOrFail()->summary)
            ->assertDontSee('<details class="panel history-panel" open>', false);
    }

    public function test_manager_sees_all_demand_stages_in_kanban_and_can_switch_to_paginated_list(): void
    {
        [$organization, $manager, $professional] = $this->team();
        $demand = $this->demand($organization, $manager);
        $this->task($demand, $professional, $manager, 'Produzir a página');

        $this->actingAs($manager)->get(route('demands.index'))
            ->assertOk()
            ->assertSee('Quadro')
            ->assertSee('Lista')
            ->assertSee('Quadro de demandas por etapa')
            ->assertSee('Demanda recebida')
            ->assertSee('Aprovação do cliente')
            ->assertSee('1 tarefa')
            ->assertSee('kanban-move', false)
            ->assertSee('Mover para')
            ->assertSee('<details class="kanban-move-panel">', false)
            ->assertSee('<summary>Mover demanda</summary>', false)
            ->assertDontSee('<details class="kanban-move-panel" open>', false);

        $this->get(route('demands.index', ['view' => 'list']))
            ->assertOk()
            ->assertSee('Lista de demandas')
            ->assertSee('Site institucional')
            ->assertDontSee('Quadro de demandas por etapa');
    }

    public function test_large_kanban_columns_keep_all_demands_accessible_without_stacking_them_by_default(): void
    {
        [$organization, $manager] = $this->team();
        foreach (range(1, 8) as $number) {
            $demand = $this->demand($organization, $manager);
            $createdAt = now()->subMinutes($number);
            $demand->update(['title' => sprintf('Volume de demonstração %02d', $number), 'created_at' => $createdAt, 'updated_at' => $createdAt]);
        }

        $response = $this->actingAs($manager)->get(route('demands.index'))->assertOk();
        $html = $response->getContent();
        $morePosition = strpos($html, '<details class="kanban-more">');

        $this->assertNotFalse($morePosition);
        $this->assertSame(8, substr_count($html, '<article class="kanban-card"'));
        $this->assertSame(8, substr_count($html, 'aria-label="Abrir demanda:'));
        $this->assertLessThan($morePosition, strpos($html, 'Volume de demonstração 06'));
        $this->assertGreaterThan($morePosition, strpos($html, 'Volume de demonstração 07'));
        $response->assertSee('<summary>Mostrar 2 demandas</summary>', false);
    }

    public function test_kanban_explains_that_open_tasks_block_internal_review(): void
    {
        [$organization, $manager, $professional] = $this->team();
        $demand = $this->demand($organization, $manager);
        $demand->update(['status' => DemandStatus::InProgress]);
        $this->task($demand, $professional, $manager, 'Finalizar a entrega');

        $this->actingAs($manager)->get(route('demands.index'))
            ->assertOk()
            ->assertSee('A revisão interna será liberada após concluir todas as tarefas.')
            ->assertDontSee('Sem próxima etapa');
    }

    public function test_task_board_shows_shared_work_by_status_and_manager_can_move_tasks(): void
    {
        [$organization, $manager, $professional] = $this->team();
        $demand = $this->demand($organization, $manager);
        $task = $this->task($demand, $professional, $manager, 'Criar página inicial');

        $this->actingAs($manager)->get(route('demand-tasks.board'))
            ->assertOk()
            ->assertSee('Quadro de tarefas')
            ->assertSee('Criar página inicial')
            ->assertSee($demand->title)
            ->assertSee('A fazer')
            ->assertSee('Mover para')
            ->assertSee('data-task-column', false);

        $this->patch(route('demand-tasks.status', $task), ['status' => TaskStatus::InProgress->value])
            ->assertRedirect()
            ->assertSessionHasNoErrors();
        $this->assertSame(TaskStatus::InProgress, $task->fresh()->status);
        $this->assertDatabaseHas('demand_events', [
            'task_id' => $task->id,
            'actor_id' => $manager->id,
            'event_type' => 'task_status_changed',
            'to_status' => TaskStatus::InProgress->value,
        ]);
    }

    public function test_professional_task_board_contains_only_assigned_tasks_and_can_update_own_work(): void
    {
        [$organization, $manager, $professional, $colleague] = $this->team();
        $demand = $this->demand($organization, $manager);
        $ownTask = $this->task($demand, $professional, $manager, 'Minha entrega');
        $this->task($demand, $colleague, $manager, 'Entrega da colega');

        $this->actingAs($professional)->get(route('demand-tasks.board'))
            ->assertOk()
            ->assertSee('Minha entrega')
            ->assertDontSee('Entrega da colega')
            ->assertSee('Mover para');

        $this->patch(route('demand-tasks.status', $ownTask), ['status' => TaskStatus::InProgress->value])
            ->assertRedirect()
            ->assertSessionHasNoErrors();
    }

    public function test_client_cannot_open_internal_task_board(): void
    {
        [$organization, $manager] = $this->team();
        $client = User::factory()->create([
            'organization_id' => $organization->id,
            'role' => UserRole::Client,
            'is_active' => true,
        ]);

        $this->actingAs($client)->get(route('demand-tasks.board'))->assertForbidden();
    }

    public function test_professional_kanban_only_shows_assigned_or_created_demands_and_has_no_stage_controls(): void
    {
        [$organization, $manager, $professional, $colleague] = $this->team();
        $visible = $this->demand($organization, $manager);
        $visible->update(['title' => 'Demanda da minha tarefa']);
        $this->task($visible, $professional, $manager, 'Minha tarefa');
        $hidden = $this->demand($organization, $manager);
        $hidden->update(['title' => 'Demanda da colega']);
        $this->task($hidden, $colleague, $manager, 'Tarefa da colega');

        $this->actingAs($professional)->get(route('demands.index'))
            ->assertOk()
            ->assertSee('Quadro de demandas por etapa')
            ->assertSee('Demanda da minha tarefa')
            ->assertDontSee('Demanda da colega')
            ->assertDontSee('data-kanban-move', false)
            ->assertSee('draggable="false"', false);
    }

    public function test_task_cannot_be_assigned_to_a_user_from_another_organization(): void
    {
        [$organization, $manager] = $this->team();
        [, , $outsideProfessional] = $this->team('outside');

        $this->actingAs($manager)->from(route('demands.create'))
            ->post(route('demands.store'), [
                'title' => 'Site institucional',
                'brief' => 'Briefing válido.',
                'module_key' => 'website_review',
                'tasks' => [['title' => 'Planejar página', 'assignee_id' => $outsideProfessional->id]],
            ])
            ->assertSessionHasErrors('tasks.0.assignee_id');

        $this->assertDatabaseCount('demands', 0);
        $this->assertSame(2, User::where('organization_id', $organization->id)->where('role', UserRole::Professional->value)->count());
    }

    public function test_new_demand_form_offers_only_the_two_confirmed_module_types(): void
    {
        [, $manager] = $this->team();

        $this->actingAs($manager)->get(route('demands.create'))
            ->assertOk()
            ->assertSee('Tipo de aprovação')
            ->assertSee('Criativo para redes sociais')
            ->assertSee('Revisão de site')
            ->assertSee('name="module_key"', false);
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

    public function test_manager_can_transfer_an_inactive_professionals_open_task_with_audit_history(): void
    {
        [$organization, $manager, $previousAssignee, $nextAssignee] = $this->team();
        $reassigner = User::factory()->create(['organization_id' => $organization->id, 'role' => UserRole::MarketingManager, 'is_active' => true]);
        $demand = $this->demand($organization, $manager);
        $task = $this->task($demand, $previousAssignee, $manager, 'Finalizar página inicial');
        $task->update(['status' => TaskStatus::Paused]);
        DemandEvent::create([
            'organization_id' => $organization->id,
            'demand_id' => $demand->id,
            'task_id' => $task->id,
            'actor_id' => $manager->id,
            'event_type' => 'task_assigned',
            'summary' => 'Tarefa "Finalizar página inicial" atribuída a '.$previousAssignee->name,
        ]);
        $previousAssignee->update(['is_active' => false]);

        $this->actingAs($manager)->get(route('demands.show', $demand))
            ->assertOk()
            ->assertSee('Acesso desativado')
            ->assertSee('Transferir tarefa')
            ->assertSee($nextAssignee->name);

        $this->actingAs($reassigner)->patchJson(route('demand-tasks.assignee', $task), [
            'assignee_id' => $nextAssignee->id,
        ])->assertOk()
            ->assertJsonPath('data.assignee.id', $nextAssignee->id)
            ->assertJsonPath('data.assigned_by.id', $reassigner->id)
            ->assertJsonPath('data.assigned_by.name', $reassigner->name);

        $this->assertSame($nextAssignee->id, $task->fresh()->assigned_to);
        $this->assertSame(TaskStatus::Paused, $task->fresh()->status);
        $this->assertDatabaseHas('demand_events', [
            'task_id' => $task->id,
            'actor_id' => $reassigner->id,
            'event_type' => 'task_reassigned',
            'summary' => $reassigner->name.' reatribuiu "Finalizar página inicial" de '.$previousAssignee->name.' para '.$nextAssignee->name,
        ]);

        $this->assertDatabaseHas('demand_events', [
            'task_id' => $task->id,
            'event_type' => 'task_assigned',
            'summary' => 'Tarefa "Finalizar página inicial" atribuída a '.$previousAssignee->name,
        ]);
        $this->actingAs($reassigner)->get(route('demands.show', $demand))
            ->assertOk()
            ->assertSee('Atribuída a')
            ->assertSee($nextAssignee->name)
            ->assertSee('Atribuída por '.$reassigner->name)
            ->assertSee('Reatribuir tarefa');
    }

    public function test_reassigning_an_in_progress_task_closes_timer_and_pauses_it(): void
    {
        [$organization, $manager, $previousAssignee, $nextAssignee] = $this->team();
        $demand = $this->demand($organization, $manager);
        $task = $this->task($demand, $previousAssignee, $manager, 'Preparar arte final');
        $task->update(['status' => TaskStatus::InProgress]);
        $entry = TaskTimeEntry::create([
            'organization_id' => $organization->id,
            'task_id' => $task->id,
            'user_id' => $previousAssignee->id,
            'started_at' => now()->subMinutes(12),
        ]);

        $this->actingAs($manager)->patch(route('demand-tasks.assignee', $task), [
            'assignee_id' => $nextAssignee->id,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame($nextAssignee->id, $task->fresh()->assigned_to);
        $this->assertSame(TaskStatus::Paused, $task->fresh()->status);
        $this->assertNotNull($entry->fresh()->ended_at);
        $this->assertDatabaseHas('demand_events', [
            'task_id' => $task->id,
            'actor_id' => $manager->id,
            'event_type' => 'timer_paused',
        ]);
        $this->assertDatabaseHas('demand_events', [
            'task_id' => $task->id,
            'actor_id' => $manager->id,
            'event_type' => 'task_status_changed',
            'from_status' => TaskStatus::InProgress->value,
            'to_status' => TaskStatus::Paused->value,
        ]);
    }

    public function test_reassignment_rejects_professional_cross_organization_inactive_and_completed_tasks(): void
    {
        [$organization, $manager, $professional, $colleague] = $this->team();
        [, , $outsideProfessional] = $this->team('outside');
        $demand = $this->demand($organization, $manager);
        $task = $this->task($demand, $professional, $manager, 'Revisar formulário');
        $inactive = User::factory()->create([
            'organization_id' => $organization->id,
            'role' => UserRole::Professional,
            'is_active' => false,
        ]);

        $this->actingAs($professional)->patch(route('demand-tasks.assignee', $task), [
            'assignee_id' => $colleague->id,
        ])->assertForbidden();

        $this->actingAs($manager)->patch(route('demand-tasks.assignee', $task), [
            'assignee_id' => $outsideProfessional->id,
        ])->assertSessionHasErrors('assignee_id');
        $this->patch(route('demand-tasks.assignee', $task), [
            'assignee_id' => $inactive->id,
        ])->assertSessionHasErrors('assignee_id');
        $this->assertSame($professional->id, $task->fresh()->assigned_to);

        $task->update(['status' => TaskStatus::Completed]);
        $this->patch(route('demand-tasks.assignee', $task), [
            'assignee_id' => $colleague->id,
        ])->assertSessionHasErrors('assignee_id');
        $this->assertSame($professional->id, $task->fresh()->assigned_to);
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

        $this->actingAs($professional)->get(route('demands.index', ['view' => 'list']))
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

    public function test_management_cannot_record_client_decision_or_skip_review_link(): void
    {
        [$organization, $manager] = $this->team();
        $demand = $this->demand($organization, $manager);
        $demand->update(['status' => DemandStatus::ClientApproval]);

        $this->actingAs($manager)->get(route('demands.index'))
            ->assertOk()
            ->assertSee('Aguardando decisão do cliente pelo link.')
            ->assertDontSee('Mover demanda');

        foreach ([DemandStatus::Delivery, DemandStatus::Adjustments] as $target) {
            $this->patch(route('demands.status', $demand), ['status' => $target->value])
                ->assertSessionHasErrors(['status' => 'A etapa só avança depois que o cliente registra uma decisão pelo link de revisão.']);
        }

        $this->assertSame(DemandStatus::ClientApproval, $demand->fresh()->status);
        $this->assertDatabaseMissing('demand_events', ['demand_id' => $demand->id, 'event_type' => 'demand_status_changed']);
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

    public function test_professional_can_recover_abandoned_timer_and_task_is_paused(): void
    {
        Date::setTestNow(CarbonImmutable::parse('2026-09-26 12:00:00'));
        [$organization, $manager, $professional] = $this->team();
        $demand = $this->demand($organization, $manager);
        $task = $this->task($demand, $professional, $manager, 'Retomar timer interrompido');
        $task->update(['status' => TaskStatus::InProgress]);
        $entry = TaskTimeEntry::create([
            'organization_id' => $organization->id,
            'task_id' => $task->id,
            'user_id' => $professional->id,
            'started_at' => CarbonImmutable::now()->subHours(9),
        ]);

        $this->actingAs($professional)->post(route('demand-tasks.timer.recover'))
            ->assertRedirect()->assertSessionHas('success');

        $this->assertDatabaseHas('task_time_entries', [
            'id' => $entry->id,
            'ended_at' => CarbonImmutable::now()->toDateTimeString(),
        ]);
        $this->assertDatabaseHas('demand_tasks', ['id' => $task->id, 'status' => TaskStatus::Paused->value]);
        $this->assertDatabaseHas('demand_events', [
            'demand_id' => $demand->id,
            'task_id' => $task->id,
            'actor_id' => $professional->id,
            'event_type' => 'timer_recovered',
        ]);

        $this->get(route('team.activity'))->assertOk()->assertDontSee('Cronômetro em andamento');
        Date::setTestNow();
    }

    public function test_stale_browser_heartbeat_closes_timer_at_last_confirmed_signal(): void
    {
        Date::setTestNow(CarbonImmutable::parse('2026-09-26 12:00:00'));
        [$organization, $owner, $professional, $colleague] = $this->team();
        $demand = $this->demand($organization, $owner);
        $task = $this->task($demand, $professional, $owner, 'Tarefa após fechamento inesperado');
        $colleagueTask = $this->task($demand, $colleague, $owner, 'Tarefa ainda ativa');
        $this->actingAs($professional)->post(route('demand-tasks.timer.start', $task))->assertRedirect();
        $this->actingAs($colleague)->post(route('demand-tasks.timer.start', $colleagueTask))->assertRedirect();
        $entry = TaskTimeEntry::query()->firstOrFail();
        $lastHeartbeat = $entry->last_heartbeat_at;

        $this->travel(120)->seconds();
        $this->actingAs($colleague)->post(route('demand-tasks.timer.heartbeat'))->assertOk()->assertJsonPath('data.active', true);
        $activeEntry = TaskTimeEntry::query()->where('task_id', $colleagueTask->id)->firstOrFail();
        $this->travel(61)->seconds();
        $this->artisan('mix7:timers:expire-stale')->assertExitCode(0);

        $this->assertSame($lastHeartbeat->toDateTimeString(), $entry->fresh()->ended_at->toDateTimeString());
        $this->assertSame(TaskStatus::Paused, $task->fresh()->status);
        $this->assertNull($activeEntry->fresh()->ended_at);
        $this->assertSame(TaskStatus::InProgress, $colleagueTask->fresh()->status);
        $this->assertDatabaseHas('demand_events', [
            'demand_id' => $demand->id,
            'task_id' => $task->id,
            'actor_id' => null,
            'event_type' => 'timer_auto_paused',
        ]);
        Date::setTestNow();
    }

    public function test_active_timer_page_renders_heartbeat_endpoint_and_interval(): void
    {
        [$organization, $owner, $professional] = $this->team();
        $demand = $this->demand($organization, $owner);
        $task = $this->task($demand, $professional, $owner, 'Heartbeat visível');
        $this->actingAs($professional)->post(route('demand-tasks.timer.start', $task))->assertRedirect();

        $this->get(route('demands.show', $demand))
            ->assertOk()
            ->assertSee(str_replace('/', '\\/', route('demand-tasks.timer.heartbeat')), false)
            ->assertSee('heartbeatInterval = 20000', false)
            ->assertSee('setInterval(sendHeartbeat, heartbeatInterval)', false)
            ->assertSee('data-task-tray-timer', false)
            ->assertSee(route('demand-tasks.timer.pause', $task), false);
    }

    public function test_professional_task_tray_lists_only_assigned_open_tasks_and_controls_timer(): void
    {
        [$organization, $manager, $professional, $colleague] = $this->team();
        $demand = $this->demand($organization, $manager);
        $ownTask = $this->task($demand, $professional, $manager, 'Minha tarefa do tray');
        $blockedTask = $this->task($demand, $professional, $manager, 'Outra tarefa minha');
        $dependency = $this->task($demand, $professional, $manager, 'Pré-requisito');
        $blockedTask->dependencies()->attach($dependency->id);
        $this->task($demand, $colleague, $manager, 'Tarefa privada do colega');

        $this->actingAs($professional)->get(route('demands.index'))
            ->assertOk()
            ->assertSee('Minhas tarefas')
            ->assertSee('Minha tarefa do tray')
            ->assertSee('Outra tarefa minha')
            ->assertSee(route('demand-tasks.timer.start', $ownTask), false)
            ->assertSee('Bloqueada')
            ->assertDontSee('Tarefa privada do colega');

        $this->post(route('demand-tasks.timer.start', $ownTask))->assertRedirect();
        $this->get(route('demands.index'))
            ->assertOk()
            ->assertSee('data-task-tray-timer', false)
            ->assertSee('Pausar cronômetro de Minha tarefa do tray')
            ->assertSee(route('demand-tasks.timer.pause', $ownTask), false)
            ->assertSee('title="Pause a tarefa atual antes de iniciar outra"', false)
            ->assertSee('Outra tarefa minha');

        $this->actingAs($manager)->get(route('demands.index'))
            ->assertOk()->assertDontSee('Minhas tarefas');
    }

    public function test_professional_can_complete_active_task_from_tray_and_timer_stops(): void
    {
        [$organization, $manager, $professional] = $this->team();
        $demand = $this->demand($organization, $manager);
        $task = $this->task($demand, $professional, $manager, 'Concluir pelo painel fixo');
        $this->actingAs($professional)->post(route('demand-tasks.timer.start', $task))->assertRedirect();

        $this->get(route('demands.index'))
            ->assertOk()
            ->assertSee('aria-label="Concluir tarefa Concluir pelo painel fixo"', false)
            ->assertSee(route('demand-tasks.status', $task), false);

        $this->travel(30)->seconds();
        $this->patch(route('demand-tasks.status', $task), ['status' => TaskStatus::Completed->value])->assertRedirect();

        $this->assertSame(TaskStatus::Completed, $task->fresh()->status);
        $this->assertNotNull($task->fresh()->completed_at);
        $this->assertNotNull(TaskTimeEntry::query()->where('task_id', $task->id)->firstOrFail()->ended_at);
    }

    public function test_non_professional_cannot_recover_another_persons_timer(): void
    {
        [, $owner, $professional] = $this->team();

        $this->actingAs($owner)->post(route('demand-tasks.timer.recover'))->assertForbidden();
        $this->actingAs($professional)->post(route('demand-tasks.timer.recover'))->assertSessionHasErrors('timer');
        Date::setTestNow();
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
