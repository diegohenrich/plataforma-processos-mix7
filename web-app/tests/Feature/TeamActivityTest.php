<?php

namespace Tests\Feature;

use App\Enums\DemandStatus;
use App\Enums\TaskStatus;
use App\Enums\UserRole;
use App\Models\Demand;
use App\Models\DemandTask;
use App\Models\Organization;
use App\Models\PerformanceReview;
use App\Models\PerformanceReviewResponse;
use App\Models\TaskTimeEntry;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeamActivityTest extends TestCase
{
    use RefreshDatabase;

    public function test_management_sees_only_organization_activity_with_defined_measures(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-09-26 12:00:00'));
        [$organization, $owner, $manager, $professional, $colleague] = $this->workspace();
        $demand = $this->demand($organization, $owner);
        $this->task($demand, $professional, 'Preparar página', TaskStatus::Todo, 60);
        $this->task($demand, $professional, 'Revisar conteúdo', TaskStatus::Blocked, 30);
        $completed = $this->task($demand, $professional, 'Publicar versão', TaskStatus::Completed, 45);
        $completed->update(['completed_at' => CarbonImmutable::now()->subDays(4)]);
        $this->task($demand, $colleague, 'Tarefa da colega', TaskStatus::InProgress, 120);
        TaskTimeEntry::create([
            'organization_id' => $organization->id,
            'task_id' => $completed->id,
            'user_id' => $professional->id,
            'started_at' => CarbonImmutable::now()->subMinutes(45),
            'ended_at' => CarbonImmutable::now()->subMinutes(15),
        ]);
        [$outsideOrganization] = $this->workspace('outside');
        $outsideProfessional = User::factory()->create(['organization_id' => $outsideOrganization->id, 'role' => UserRole::Professional, 'is_active' => true]);
        $outsideDemand = $this->demand($outsideOrganization, $outsideProfessional);
        $this->task($outsideDemand, $outsideProfessional, 'Demanda de outra organização', TaskStatus::InProgress);

        $this->actingAs($manager)->get(route('team.activity'))
            ->assertOk()->assertSee('Produção da equipe')->assertSee($professional->name)
            ->assertSee('2,0 h')->assertSee('0,5 h')->assertSee('concluídas em 30 dias')
            ->assertSee('não são nota, ranking')->assertSee($colleague->name)->assertDontSee('Tarefa da colega')->assertDontSee($outsideProfessional->name);
        $this->get(route('dashboard'))->assertOk()->assertSee('Produção da equipe');
        $this->actingAs($owner)->get(route('team.activity'))->assertOk();
        $this->actingAs($manager)->get(route('team.index'))->assertForbidden();
    }

    public function test_professional_sees_own_tasks_and_timer_controls_but_not_colleagues(): void
    {
        [$organization, $owner, , $professional, $colleague] = $this->workspace();
        $demand = $this->demand($organization, $owner);
        $this->task($demand, $professional, 'Minha tarefa', TaskStatus::Todo);
        $this->task($demand, $colleague, 'Tarefa privada da colega', TaskStatus::InProgress);

        $this->actingAs($professional)->get(route('team.activity'))
            ->assertOk()->assertSee('Meu trabalho')->assertSee('Minha tarefa')->assertSee('Iniciar tempo')->assertDontSee('Tarefa privada da colega');
        $this->get(route('dashboard'))->assertOk()->assertSee('Meu trabalho')->assertSee('Abrir minhas tarefas');
        $this->actingAs($professional)->get(route('team.index'))->assertForbidden();
    }

    public function test_activity_api_keeps_team_aggregates_separate_from_personal_task_details(): void
    {
        [$organization, $owner, $manager, $professional, $colleague, $client] = $this->workspace();
        $demand = $this->demand($organization, $owner);
        $ownTask = $this->task($demand, $professional, 'Minha tarefa privada', TaskStatus::InProgress, 45);
        $this->task($demand, $colleague, 'Tarefa privada da colega', TaskStatus::Todo, 90);
        TaskTimeEntry::create([
            'organization_id' => $organization->id,
            'task_id' => $ownTask->id,
            'user_id' => $professional->id,
            'started_at' => CarbonImmutable::now()->subMinutes(10),
            'ended_at' => null,
        ]);

        $managementResponse = $this->actingAs($manager)->getJson('/api/v1/team/activity')
            ->assertOk()
            ->assertJsonPath('data.personal', false)
            ->assertJsonPath('data.professionals.0.id', $professional->id)
            ->assertJsonPath('data.professionals.0.tasks.in_progress', 1)
            ->assertJsonPath('data.professionals.0.estimate_minutes', 45)
            ->assertJsonPath('data.my_tasks', [])
            ->assertJsonPath('data.active_timer', null);
        $this->assertStringNotContainsString('Minha tarefa privada', $managementResponse->getContent());
        $this->assertStringNotContainsString('Tarefa privada da colega', $managementResponse->getContent());

        $personalResponse = $this->actingAs($professional)->getJson('/api/v1/team/activity')
            ->assertOk()
            ->assertJsonPath('data.personal', true)
            ->assertJsonPath('data.professionals.0.id', $professional->id)
            ->assertJsonPath('data.my_tasks.0.id', $ownTask->id)
            ->assertJsonPath('data.my_tasks.0.title', 'Minha tarefa privada')
            ->assertJsonPath('data.active_timer.task_id', $ownTask->id);
        $this->assertStringNotContainsString('Tarefa privada da colega', $personalResponse->getContent());

        $this->actingAs($client)->getJson('/api/v1/team/activity')->assertForbidden();
    }

    public function test_client_cannot_access_activity_dashboard(): void
    {
        [$organization, , , , , $client] = $this->workspace();

        $this->actingAs($client)->get(route('team.activity'))->assertForbidden();
    }

    public function test_management_can_record_task_review_and_professional_can_respond_with_history(): void
    {
        [$organization, $owner, $manager, $professional] = $this->workspace();
        $demand = $this->demand($organization, $owner);
        $task = $this->task($demand, $professional, 'Finalizar site', TaskStatus::Completed);

        $this->actingAs($manager)->get(route('performance-reviews.index'))
            ->assertOk()->assertSee('Registrar avaliação de tarefa concluída')->assertSee('gerência peso 1');
        $this->post(route('performance-reviews.store'), [
            'task_id' => $task->id,
            'deadline_assessment' => 'Prazo combinado cumprido após revisão do escopo.',
            'quality_assessment' => 'Entrega conferida com critérios do briefing e sem erros visíveis.',
            'evidence' => 'Checklist de aceite conferido pela gerência.',
            'external_factors' => 'O cliente enviou os textos dois dias depois do previsto.',
        ])->assertRedirect(route('performance-reviews.index'));

        $review = PerformanceReview::firstOrFail();
        $this->assertSame($professional->id, $review->professional_id);
        $this->assertSame($manager->id, $review->reviewer_id);
        $this->assertSame(1, $review->reviewer_weight);

        $this->actingAs($professional)->get(route('performance-reviews.index'))
            ->assertOk()->assertSee('Prazo combinado cumprido')->assertSee('Bloqueios ou mudanças externas')
            ->assertSee('Adicionar ao histórico')->assertDontSee('Registrar avaliação de tarefa concluída');
        $this->post(route('performance-reviews.respond', $review), ['response' => 'Concordo com os critérios e acrescento este contexto.'])
            ->assertRedirect(route('performance-reviews.index'));
        $this->assertDatabaseHas('performance_review_responses', [
            'performance_review_id' => $review->id,
            'user_id' => $professional->id,
            'response' => 'Concordo com os critérios e acrescento este contexto.',
        ]);
        $this->assertSame(1, PerformanceReviewResponse::count());
    }

    public function test_management_can_review_by_api_and_only_the_assigned_professional_can_respond(): void
    {
        [$organization, $owner, $manager, $professional, $colleague, $client] = $this->workspace();
        $demand = $this->demand($organization, $owner);
        $task = $this->task($demand, $professional, 'Concluir campanha', TaskStatus::Completed);
        $payload = [
            'task_id' => $task->id,
            'deadline_assessment' => 'O prazo foi acompanhado com contexto e evidência suficientes.',
            'quality_assessment' => 'A entrega foi revisada conforme os critérios definidos para a campanha.',
            'evidence' => 'Checklist da campanha conferido.',
            'external_factors' => 'Aprovação do texto chegou após o prazo inicial.',
        ];

        $managerReviewResponse = $this->actingAs($manager)->postJson('/api/v1/team/performance-reviews', $payload)
            ->assertCreated()
            ->assertJsonPath('data.reviewer_id', $manager->id)
            ->assertJsonPath('data.professional_id', $professional->id)
            ->assertJsonPath('data.reviewer_weight', 1);
        $managerReview = PerformanceReview::findOrFail($managerReviewResponse->json('data.id'));

        $this->actingAs($owner)->postJson('/api/v1/team/performance-reviews', $payload)
            ->assertCreated()
            ->assertJsonPath('data.reviewer_weight', 2);
        $this->actingAs($manager)->postJson('/api/v1/team/performance-reviews', $payload)
            ->assertStatus(409);

        $this->actingAs($colleague)->postJson('/api/v1/team/performance-reviews/'.$managerReview->id.'/responses', [
            'response' => 'Não sou a pessoa avaliada nesta tarefa.',
        ])->assertForbidden();
        $this->actingAs($client)->postJson('/api/v1/team/performance-reviews/'.$managerReview->id.'/responses', [
            'response' => 'O cliente não pode responder à avaliação interna.',
        ])->assertForbidden();

        $this->actingAs($professional)->postJson('/api/v1/team/performance-reviews/'.$managerReview->id.'/responses', [
            'response' => 'Considero a evidência correta e contextualizo o prazo da campanha.',
        ])->assertCreated()
            ->assertJsonPath('data.performance_review_id', $managerReview->id)
            ->assertJsonPath('data.user_id', $professional->id);
        $this->assertDatabaseHas('performance_review_responses', [
            'performance_review_id' => $managerReview->id,
            'user_id' => $professional->id,
            'response' => 'Considero a evidência correta e contextualizo o prazo da campanha.',
        ]);
    }

    public function test_owner_weight_is_two_and_duplicate_or_unrelated_reviews_are_rejected(): void
    {
        [$organization, $owner, , $professional] = $this->workspace();
        $demand = $this->demand($organization, $owner);
        $task = $this->task($demand, $professional, 'Publicar site', TaskStatus::Completed);
        $payload = [
            'task_id' => $task->id,
            'deadline_assessment' => 'O prazo previsto foi cumprido conforme combinado.',
            'quality_assessment' => 'A qualidade atende os critérios registrados para a entrega.',
        ];

        $this->actingAs($owner)->post(route('performance-reviews.store'), $payload)->assertRedirect(route('performance-reviews.index'));
        $this->assertDatabaseHas('performance_reviews', ['task_id' => $task->id, 'reviewer_weight' => 2]);
        $this->post(route('performance-reviews.store'), $payload)->assertSessionHasErrors('task_id');

        [$otherOrganization, $otherOwner, , $otherProfessional] = $this->workspace('outside-review');
        $otherDemand = $this->demand($otherOrganization, $otherOwner);
        $otherTask = $this->task($otherDemand, $otherProfessional, 'Outra entrega', TaskStatus::Completed);
        $this->post(route('performance-reviews.store'), [...$payload, 'task_id' => $otherTask->id])->assertSessionHasErrors('task_id');
    }

    public function test_professional_cannot_review_or_respond_to_another_professionals_review(): void
    {
        [$organization, $owner, , $professional, $colleague] = $this->workspace();
        $demand = $this->demand($organization, $owner);
        $colleagueTask = $this->task($demand, $colleague, 'Tarefa da colega', TaskStatus::Completed);
        $this->actingAs($professional)->post(route('performance-reviews.store'), [
            'task_id' => $colleagueTask->id,
            'deadline_assessment' => 'Descrição suficiente para passar na validação.',
            'quality_assessment' => 'Descrição suficiente para passar na validação.',
        ])->assertForbidden();

        $this->actingAs($owner)->post(route('performance-reviews.store'), [
            'task_id' => $colleagueTask->id,
            'deadline_assessment' => 'O prazo foi acompanhado e está descrito com evidência.',
            'quality_assessment' => 'A qualidade foi conferida com os critérios de aceite.',
        ])->assertRedirect(route('performance-reviews.index'));
        $review = PerformanceReview::firstOrFail();
        $this->actingAs($professional)->post(route('performance-reviews.respond', $review), ['response' => 'Tentativa de resposta fora do perfil.'])->assertForbidden();
        $this->actingAs($colleague)->post(route('performance-reviews.respond', $review), ['response' => 'Registro meu contexto nesta tarefa.'])->assertRedirect(route('performance-reviews.index'));
        $this->actingAs($owner)->get(route('performance-reviews.index'))->assertOk()->assertSee('Registro meu contexto nesta tarefa.');
    }

    public function test_manager_and_owner_can_view_reviews_but_client_cannot(): void
    {
        [$organization, $owner, $manager, $professional, , $client] = $this->workspace();
        $demand = $this->demand($organization, $owner);
        $task = $this->task($demand, $professional, 'Ajustar campanha', TaskStatus::Completed);
        $this->actingAs($owner)->post(route('performance-reviews.store'), [
            'task_id' => $task->id,
            'deadline_assessment' => 'O prazo final respeitou a mudança aprovada.',
            'quality_assessment' => 'A qualidade corresponde ao material validado.',
        ])->assertRedirect(route('performance-reviews.index'));

        $this->actingAs($manager)->get(route('performance-reviews.index'))->assertOk()->assertSee('Ajustar campanha');
        $this->actingAs($client)->get(route('performance-reviews.index'))->assertForbidden();
    }

    private function workspace(string $slug = 'mix7'): array
    {
        $organization = Organization::create(['name' => ucfirst($slug), 'slug' => $slug]);
        $owner = User::factory()->create(['organization_id' => $organization->id, 'role' => UserRole::AgencyOwner, 'is_active' => true]);
        $manager = User::factory()->create(['organization_id' => $organization->id, 'role' => UserRole::MarketingManager, 'is_active' => true]);
        $professional = User::factory()->create(['organization_id' => $organization->id, 'role' => UserRole::Professional, 'is_active' => true]);
        $colleague = User::factory()->create(['organization_id' => $organization->id, 'role' => UserRole::Professional, 'is_active' => true]);
        $client = User::factory()->create(['organization_id' => $organization->id, 'role' => UserRole::Client, 'is_active' => true]);

        return [$organization, $owner, $manager, $professional, $colleague, $client];
    }

    private function demand(Organization $organization, User $creator): Demand
    {
        return Demand::create([
            'organization_id' => $organization->id,
            'created_by' => $creator->id,
            'title' => 'Site institucional',
            'brief' => 'Briefing sintético.',
            'status' => DemandStatus::InProgress,
        ]);
    }

    private function task(Demand $demand, User $assignee, string $title, TaskStatus $status, ?int $estimateMinutes = null): DemandTask
    {
        return $demand->tasks()->create([
            'organization_id' => $demand->organization_id,
            'created_by' => $demand->created_by,
            'assigned_to' => $assignee->id,
            'title' => $title,
            'status' => $status,
            'estimate_minutes' => $estimateMinutes,
        ]);
    }
}
