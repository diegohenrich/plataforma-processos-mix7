<?php

namespace Tests\Feature;

use App\Enums\DemandStatus;
use App\Enums\TaskStatus;
use App\Enums\UserRole;
use App\Models\AiPlanningRun;
use App\Models\Demand;
use App\Models\DemandReviewLink;
use App\Models\DemandReviewResponse;
use App\Models\DemandTask;
use App\Models\Organization;
use App\Models\TeamCapacitySnapshot;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiPlanningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.ai_gateway.key' => 'test-key',
            'services.ai_gateway.base_url' => 'https://ai-gateway.vercel.sh/v1',
            'services.ai_gateway.model' => 'test-provider/test-model',
        ]);
    }

    public function test_manager_generates_proposal_without_creating_tasks(): void
    {
        [$organization, $manager, $professional, $demand] = $this->workspace();
        Http::fake(['https://ai-gateway.vercel.sh/v1/chat/completions' => Http::response($this->providerResponse(), 200)]);

        $this->actingAs($manager)->post(route('ai-planning.propose', $demand))->assertRedirect();
        $this->get(route('demands.show', $demand))->assertOk()->assertSee('Resumo curto para o quadro')->assertSee('name="summary"', false);

        $run = AiPlanningRun::firstOrFail();
        $this->assertSame($organization->id, $run->organization_id);
        $this->assertSame($manager->id, $run->requested_by);
        $this->assertSame('pending', $run->status);
        $this->assertSame(2, count($run->proposal['tasks']));
        $this->assertSame(0, $run->proposal['tasks'][1]['depends_on'][0]);
        $this->assertSame(0, DemandTask::count());
        $this->assertNull($demand->fresh()->ai_summary);
        $this->assertSame(64, strlen($run->input_hash));
        $this->assertDatabaseHas('demand_events', ['demand_id' => $demand->id, 'actor_id' => $manager->id, 'event_type' => 'ai_planning_proposed']);
        $input = json_decode(Http::recorded()->first()[0]['messages'][1]['content'], true, 32, JSON_THROW_ON_ERROR);
        $this->assertSame([], $input['team_capacity']);
        $this->assertSame([], $input['assignment_candidates']);
        Http::assertSent(fn ($request) => $request['messages'][1]['content'] !== ''
            && str_contains($request['messages'][1]['content'], 'Briefing sintético')
            && $request['response_format']['type'] === 'json_schema');
    }

    public function test_review_suggests_one_professional_by_exact_local_specialty_match_without_sending_roster_to_ai(): void
    {
        [, $manager, $professional, $demand] = $this->workspace();
        $professional->update(['specialties' => ['design']]);
        Http::fake(['https://ai-gateway.vercel.sh/v1/chat/completions' => Http::response($this->providerResponse(), 200)]);

        $this->actingAs($manager)->post(route('ai-planning.propose', $demand))->assertRedirect();
        $requestBody = Http::recorded()->first()[0]->data();
        $this->assertStringNotContainsString($professional->name, $requestBody['messages'][1]['content']);
        $page = $this->get(route('demands.show', $demand))->assertOk();
        $this->assertStringContainsString('Responsável sugerido — confirme', $page->getContent());
        $this->assertStringContainsString('Sugestão local pela correspondência exata da especialidade cadastrada.', $page->getContent());
        $this->assertSame(1, preg_match('/<option\b(?=[^>]*value="'.$professional->id.'")(?=[^>]*selected)[^>]*>[^<]*especialidade compatível/s', $page->getContent()));
        $this->assertFalse($professional->matchesSpecialty('Atendimento'));
        $this->assertTrue($professional->matchesSpecialty('DESIGN'));
        $this->assertTrue($professional->matchesSpecialty('dèsign'));
        $this->assertSame(0, DemandTask::count());
    }

    public function test_management_can_opt_in_to_an_anonymous_ai_assignee_suggestion_and_override_it(): void
    {
        [$organization, $manager, $professional, $demand] = $this->workspace();
        $professional->update(['specialties' => ['Design']]);
        $secondProfessional = User::factory()->create([
            'organization_id' => $demand->organization_id,
            'role' => UserRole::Professional,
            'is_active' => true,
            'specialties' => ['Atendimento'],
        ]);
        $inactiveProfessional = User::factory()->create([
            'organization_id' => $organization->id,
            'role' => UserRole::Professional,
            'is_active' => false,
            'specialties' => ['Design'],
        ]);
        $foreignOrganization = Organization::query()->create([
            'name' => 'Outra agência',
            'slug' => 'outra-agencia',
        ]);
        $foreignProfessional = User::factory()->create([
            'organization_id' => $foreignOrganization->id,
            'role' => UserRole::Professional,
            'is_active' => true,
            'specialties' => ['Atendimento'],
        ]);
        $suggestedReference = null;
        Http::fake(['https://ai-gateway.vercel.sh/v1/chat/completions' => function ($request) use (&$suggestedReference, $professional, $secondProfessional, $inactiveProfessional, $foreignProfessional) {
            $input = json_decode($request['messages'][1]['content'], true, 32, JSON_THROW_ON_ERROR);
            $this->assertCount(2, $input['assignment_candidates']);
            $this->assertSame(['candidate_ref', 'specialties'], array_keys($input['assignment_candidates'][0]));
            $suggestedReference = collect($input['assignment_candidates'])
                ->first(fn (array $candidate): bool => $candidate['specialties'] === ['Atendimento'])['candidate_ref'];
            $prompt = $request['messages'][1]['content'];
            $this->assertStringNotContainsString($professional->name, $prompt);
            $this->assertStringNotContainsString($secondProfessional->name, $prompt);
            $this->assertStringNotContainsString($professional->email, $prompt);
            $this->assertStringNotContainsString($inactiveProfessional->name, $prompt);
            $this->assertStringNotContainsString($inactiveProfessional->email, $prompt);
            $this->assertStringNotContainsString($foreignProfessional->name, $prompt);
            $this->assertStringNotContainsString($foreignProfessional->email, $prompt);
            $this->assertArrayNotHasKey('id', $input['assignment_candidates'][0]);

            return Http::response($this->providerResponse([], '', [$suggestedReference, null]), 200);
        }]);

        $page = $this->actingAs($manager)->get(route('demands.show', $demand))->assertOk()
            ->assertSee('Permitir que a IA sugira uma pessoa da equipe para cada tarefa');
        preg_match('/<input[^>]*name="include_assignment_candidates"[^>]*>/', $page->getContent(), $checkbox);
        $this->assertNotEmpty($checkbox);
        $this->assertStringNotContainsString('checked', $checkbox[0]);
        $this->post(route('ai-planning.propose', $demand), ['include_assignment_candidates' => 1])
            ->assertRedirect()->assertSessionHasNoErrors();

        $run = AiPlanningRun::firstOrFail();
        $this->assertTrue($run->proposal['_source']['assignment_candidates_included']);
        $this->assertSame($secondProfessional->id, $run->proposal['_source']['assignment_candidates'][$suggestedReference]['user_id']);
        $this->assertSame($suggestedReference, $run->proposal['tasks'][0]['suggested_assignee_ref']);
        $this->assertSame(0, DemandTask::count());

        $page = $this->get(route('demands.show', $demand))->assertOk();
        $this->assertStringContainsString('Responsável sugerido pela IA — confirme', $page->getContent());
        $this->assertSame(1, preg_match('/<select name="tasks\[0\]\[assignee_id\]"[^>]*>.*?<option value="'.$secondProfessional->id.'"[^>]*selected/s', $page->getContent()));

        $this->post(route('ai-planning.approve', [$demand, $run]), [
            'summary' => 'Resumo revisado pela gestão.',
            'tasks' => [
                ['include' => 1, 'title' => 'Organizar briefing', 'responsibility_profile' => 'Atendimento', 'estimate_minutes' => 30, 'assignee_id' => $professional->id],
                ['include' => 0],
            ],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $task = $demand->tasks()->firstOrFail();
        $this->assertSame($professional->id, $task->assigned_to);
        $this->assertStringContainsString('A IA sugere a pessoa de atendimento.', $task->description);
        $this->assertSame($suggestedReference, $run->fresh()->reviewed_tasks['tasks'][0]['suggested_assignee_ref']);
        $this->assertSame($professional->id, $run->fresh()->reviewed_tasks['tasks'][0]['assignee_id']);
    }

    public function test_planner_rejects_a_person_reference_that_was_not_sent(): void
    {
        [, $manager, $professional, $demand] = $this->workspace();
        $professional->update(['specialties' => ['Design']]);
        Http::fake(['https://ai-gateway.vercel.sh/v1/chat/completions' => Http::response(
            $this->providerResponse([], '', ['invented-reference']),
            200,
        )]);

        $this->actingAs($manager)->from(route('demands.show', $demand))
            ->post(route('ai-planning.propose', $demand), ['include_assignment_candidates' => 1])
            ->assertSessionHasErrors('ai');

        $this->assertSame(0, AiPlanningRun::count());
        $this->assertSame(0, DemandTask::count());
    }

    public function test_opt_in_without_declared_specialties_falls_back_to_manual_assignment(): void
    {
        [, $manager, , $demand] = $this->workspace();
        Http::fake(['https://ai-gateway.vercel.sh/v1/chat/completions' => Http::response($this->providerResponse(), 200)]);

        $this->actingAs($manager)->post(route('ai-planning.propose', $demand), ['include_assignment_candidates' => 1])
            ->assertRedirect()->assertSessionHasNoErrors();

        $run = AiPlanningRun::firstOrFail();
        $this->assertTrue($run->proposal['_source']['assignment_suggestions_requested']);
        $this->assertFalse($run->proposal['_source']['assignment_candidates_included']);
        $this->assertSame([], $run->proposal['_source']['assignment_candidates']);
        $this->get(route('demands.show', $demand))->assertOk()
            ->assertSee('Nenhuma pessoa profissional ativa tem especialidades cadastradas para esta sugestão. Escolha os responsáveis manualmente.');
    }

    public function test_review_does_not_preselect_when_multiple_professionals_match(): void
    {
        [, $manager, $professional, $demand] = $this->workspace();
        $professional->update(['specialties' => ['Design']]);
        User::factory()->create([
            'organization_id' => $demand->organization_id,
            'role' => UserRole::Professional,
            'is_active' => true,
            'specialties' => ['design'],
        ]);
        Http::fake(['https://ai-gateway.vercel.sh/v1/chat/completions' => Http::response($this->providerResponse(), 200)]);

        $this->actingAs($manager)->post(route('ai-planning.propose', $demand))->assertRedirect();
        $page = $this->get(route('demands.show', $demand))->assertOk();
        $this->assertStringContainsString('Mais de uma pessoa informou esta especialidade; escolha quem executará.', $page->getContent());
        $this->assertSame(0, preg_match('/<option\b(?=[^>]*value="'.$professional->id.'")(?=[^>]*selected)[^>]*>/', $page->getContent()));
        $this->assertSame(0, DemandTask::count());
    }

    public function test_manager_can_explicitly_include_only_this_demands_versioned_client_feedback(): void
    {
        [, $manager, $professional, $demand] = $this->workspace();
        [, $otherManager, , $otherDemand] = $this->workspace('other');
        $link = $this->reviewLink($demand, $manager, 2);
        $feedback = DemandReviewResponse::create([
            'demand_review_link_id' => $link->id,
            'reviewer_name' => 'Nome privado do cliente',
            'type' => 'changes_requested',
            'comment' => 'Ajustar o título principal conforme combinado.',
            'anchor_type' => 'text',
            'anchor_data' => ['text' => 'Título inicial', 'url' => 'https://preview.example.test/privado'],
            'created_at' => now(),
        ]);
        $otherLink = $this->reviewLink($otherDemand, $otherManager, 1);
        DemandReviewResponse::create([
            'demand_review_link_id' => $otherLink->id,
            'reviewer_name' => 'Outro cliente',
            'type' => 'annotation',
            'comment' => 'Não deve sair da outra demanda.',
            'created_at' => now(),
        ]);
        Http::fake(['https://ai-gateway.vercel.sh/v1/chat/completions' => Http::response($this->providerResponse([$feedback->id]), 200)]);

        $page = $this->actingAs($manager)->get(route('demands.show', $demand))
            ->assertOk()->assertSee('Incluir até 20 comentários e anotações do cliente');
        preg_match('/<input[^>]*name="include_client_feedback"[^>]*>/', $page->getContent(), $checkbox);
        $this->assertNotEmpty($checkbox);
        $this->assertStringNotContainsString('checked', $checkbox[0]);

        $this->actingAs($manager)->post(route('ai-planning.propose', $demand), ['include_client_feedback' => 1])
            ->assertRedirect()->assertSessionHasNoErrors();

        $requestBody = Http::recorded()->first()[0]->data();
        $input = json_decode($requestBody['messages'][1]['content'], true, 32, JSON_THROW_ON_ERROR);
        $this->assertCount(1, $input['client_feedback']);
        $this->assertSame($feedback->id, $input['client_feedback'][0]['response_id']);
        $this->assertSame(2, $input['client_feedback'][0]['version']);
        $this->assertSame('Ajustar o título principal conforme combinado.', $input['client_feedback'][0]['comment']);
        $this->assertSame(['text' => 'Título inicial'], $input['client_feedback'][0]['anchor']);
        $this->assertArrayNotHasKey('reviewer_name', $input['client_feedback'][0]);
        $this->assertArrayNotHasKey('url', $input['client_feedback'][0]['anchor']);
        $this->assertStringNotContainsString('Nome privado do cliente', $requestBody['messages'][1]['content']);
        $this->assertStringNotContainsString('Não deve sair da outra demanda.', $requestBody['messages'][1]['content']);
        $run = AiPlanningRun::firstOrFail();
        $this->assertTrue($run->proposal['_source']['client_feedback_included']);
        $this->assertSame([$feedback->id], $run->proposal['_source']['feedback_response_ids']);
        $this->assertSame(2, $run->proposal['_source']['feedback_versions'][$feedback->id]);
        $this->assertSame([$feedback->id], $run->proposal['tasks'][0]['feedback_refs']);
        $proposalPage = $this->get(route('demands.show', $demand))->assertOk();
        $proposalPage->assertViewHas('feedbackById', fn (array $sources) => isset($sources[$feedback->id]));
        $this->assertStringContainsString('Feedback ligado a esta tarefa:', $proposalPage->getContent());
        $this->assertStringContainsString('versão 2', $proposalPage->getContent());
        $this->assertStringContainsString('Ajustar o título principal conforme combinado.', $proposalPage->getContent());
        Http::assertSent(fn ($request) => in_array('feedback_refs', $request['response_format']['json_schema']['schema']['properties']['tasks']['items']['required'], true));
        $this->assertSame(0, DemandTask::count());

        $this->post(route('ai-planning.approve', [$demand, $run]), [
            'summary' => 'Resumo revisado com origem registrada.',
            'tasks' => [
                ['include' => 1, 'title' => 'Ajustar título principal', 'responsibility_profile' => 'Design', 'estimate_minutes' => 20, 'assignee_id' => $professional->id],
                ['include' => 0],
            ],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $task = $demand->tasks()->firstOrFail();
        $this->assertStringContainsString('Feedback do cliente: versão 2, resposta #'.$feedback->id, $task->description);
        $this->assertSame([$feedback->id], $run->fresh()->reviewed_tasks['tasks'][0]['feedback_refs']);
    }

    public function test_manager_can_opt_in_to_send_only_aggregated_weekly_capacity_to_planning_agent(): void
    {
        [$organization, $manager, $professional, $demand] = $this->workspace();
        $secondProfessional = User::factory()->create([
            'organization_id' => $organization->id,
            'role' => UserRole::Professional,
            'is_active' => true,
        ]);
        $week = now()->format('o-\\WW');
        [$year, $weekNumber] = array_map('intval', explode('-W', $week));
        $weekStart = CarbonImmutable::now()->setISODate($year, $weekNumber)->startOfWeek()->startOfDay();
        TeamCapacitySnapshot::create([
            'organization_id' => $organization->id,
            'professional_id' => $professional->id,
            'recorded_by' => $manager->id,
            'week_start' => $weekStart->toDateString(),
            'scheduled_minutes' => 2400,
            'absences' => [['id' => 'absence-1', 'date' => $weekStart->toDateString(), 'minutes' => 60]],
            'change_type' => 'schedule_updated',
        ]);
        $otherDemand = Demand::create([
            'organization_id' => $organization->id,
            'created_by' => $manager->id,
            'title' => 'Demanda reservada de outro cliente',
            'brief' => 'Não enviar detalhes desta demanda.',
            'status' => DemandStatus::InProgress,
        ]);
        $otherDemand->tasks()->create([
            'organization_id' => $organization->id,
            'created_by' => $manager->id,
            'assigned_to' => $professional->id,
            'title' => 'Título confidencial de tarefa alheia',
            'status' => TaskStatus::Todo,
            'estimate_minutes' => 90,
            'planned_due_on' => $weekStart->addDays(2)->toDateString(),
        ]);
        $otherDemand->tasks()->create([
            'organization_id' => $organization->id,
            'created_by' => $manager->id,
            'assigned_to' => $secondProfessional->id,
            'title' => 'Tarefa sem prazo',
            'status' => TaskStatus::Todo,
            'estimate_minutes' => 30,
        ]);

        Http::fake(['https://ai-gateway.vercel.sh/v1/chat/completions' => Http::response($this->providerResponse([], 'Os dados de disponibilidade estão incompletos; revise antes de assumir a carga total.'), 200)]);
        $page = $this->actingAs($manager)->get(route('demands.show', $demand))->assertOk();
        $page->assertSee('Considerar a capacidade semanal registrada pela gestão');
        preg_match('/<input[^>]*name="include_team_capacity"[^>]*>/', $page->getContent(), $checkbox);
        $this->assertNotEmpty($checkbox);
        $this->assertStringNotContainsString('checked', $checkbox[0]);

        $this->post(route('ai-planning.propose', $demand), [
            'include_team_capacity' => 1,
            'capacity_week' => $week,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $requestBody = Http::recorded()->first()[0]->data();
        $input = json_decode($requestBody['messages'][1]['content'], true, 32, JSON_THROW_ON_ERROR);
        $this->assertSame([
            'week' => $week,
            'active_professionals' => 2,
            'professionals_with_recorded_capacity' => 1,
            'professionals_without_recorded_capacity' => 1,
            'recorded_available_minutes' => 2340,
            'recorded_absence_minutes' => 60,
            'open_estimate_minutes_due_this_week' => 90,
            'dated_open_tasks_due_this_week' => 1,
            'dated_tasks_missing_estimate' => 0,
            'open_tasks_without_due_date' => 1,
        ], $input['team_capacity']);
        $this->assertStringNotContainsString('Título confidencial de tarefa alheia', $requestBody['messages'][1]['content']);
        $this->assertStringNotContainsString($professional->name, $requestBody['messages'][1]['content']);
        $this->assertStringNotContainsString($secondProfessional->name, $requestBody['messages'][1]['content']);
        $run = AiPlanningRun::firstOrFail();
        $this->assertTrue($run->proposal['_source']['team_capacity_included']);
        $this->assertSame($week, $run->proposal['_source']['team_capacity']['week']);
        $this->assertSame('Os dados de disponibilidade estão incompletos; revise antes de assumir a carga total.', $run->proposal['capacity_observation']);
        $this->get(route('demands.show', $demand))
            ->assertOk()
            ->assertSee('Ver totais de capacidade enviados')
            ->assertSee('1 de 2 profissionais com capacidade informada')
            ->assertSee('39,00 h após 1,00 h de ausências')
            ->assertSee('Leitura preliminar da IA:')
            ->assertSee('Os dados de disponibilidade estão incompletos; revise antes de assumir a carga total.');
        $this->assertSame(0, DemandTask::query()->where('demand_id', $demand->id)->count());
    }

    public function test_invalid_capacity_week_is_rejected_before_calling_provider(): void
    {
        [, $manager, , $demand] = $this->workspace();
        Http::fake();

        $this->actingAs($manager)->from(route('demands.show', $demand))
            ->post(route('ai-planning.propose', $demand), [
                'include_team_capacity' => 1,
                'capacity_week' => '2026-W99',
            ])->assertSessionHasErrors('capacity_week');

        Http::assertNothingSent();
        $this->assertSame(0, AiPlanningRun::count());
    }

    public function test_planner_cannot_claim_capacity_context_when_management_did_not_send_it(): void
    {
        [, $manager, , $demand] = $this->workspace();
        Http::fake(['https://ai-gateway.vercel.sh/v1/chat/completions' => Http::response($this->providerResponse([], 'A equipe está sobrecarregada.'), 200)]);

        $this->actingAs($manager)->from(route('demands.show', $demand))
            ->post(route('ai-planning.propose', $demand))->assertSessionHasErrors('ai');

        $this->assertSame(0, AiPlanningRun::count());
        $this->assertSame(0, DemandTask::count());
    }

    public function test_manager_edits_and_approves_proposal_with_real_dependency_and_audit(): void
    {
        [, $manager, $professional, $demand] = $this->workspace();
        Http::fake(['https://ai-gateway.vercel.sh/v1/chat/completions' => Http::response($this->providerResponse(), 200)]);
        $this->actingAs($manager)->post(route('ai-planning.propose', $demand));
        $run = AiPlanningRun::firstOrFail();

        $this->post(route('ai-planning.approve', [$demand, $run]), [
            'summary' => 'Resumo revisto pela gestão: site dividido em briefing e estrutura.',
            'tasks' => [
                ['include' => 1, 'title' => 'Briefing revisado', 'responsibility_profile' => 'Atendimento', 'estimate_minutes' => 25, 'assignee_id' => $professional->id],
                ['include' => 1, 'title' => 'Mapa do site revisado', 'responsibility_profile' => 'Design', 'estimate_minutes' => 65, 'assignee_id' => $professional->id],
            ],
            'questions' => ['Qual prazo?'],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $tasks = $demand->tasks()->get();
        $this->assertCount(2, $tasks);
        $this->assertSame('Briefing revisado', $tasks[0]->title);
        $this->assertSame($professional->id, $tasks[0]->assigned_to);
        $this->assertSame([$tasks[0]->id], $tasks[1]->dependencies()->pluck('demand_tasks.id')->all());
        $this->assertSame('approved', $run->fresh()->status);
        $this->assertSame($manager->id, $run->fresh()->reviewed_by);
        $this->assertSame('Resumo revisto pela gestão: site dividido em briefing e estrutura.', $demand->fresh()->ai_summary);
        $this->assertSame('Mapa do site revisado', $run->fresh()->reviewed_tasks['tasks'][1]['title']);
        $this->assertDatabaseHas('demand_events', ['demand_id' => $demand->id, 'actor_id' => $manager->id, 'event_type' => 'ai_planning_approved']);
        $this->actingAs($manager)->get(route('demands.index'))->assertSee('Resumo revisto pela gestão: site dividido em briefing e estrutura.');
        $token = $manager->createToken('summary-api')->plainTextToken;
        $this->withToken($token)->getJson('/api/v1/demands')->assertOk()->assertJsonPath('data.0.summary', 'Resumo revisto pela gestão: site dividido em briefing e estrutura.');

        $this->actingAs($professional)->post(route('demand-tasks.timer.start', $tasks[1]))->assertSessionHasErrors('timer');
        $tasks[0]->update(['status' => TaskStatus::Completed]);
        $this->post(route('demand-tasks.timer.start', $tasks[1]))->assertRedirect();
    }

    public function test_proposal_cannot_be_applied_twice_or_assigned_outside_organization(): void
    {
        [, $manager, , $demand] = $this->workspace();
        [, , $externalProfessional] = $this->workspace('other');
        Http::fake(['https://ai-gateway.vercel.sh/v1/chat/completions' => Http::response($this->providerResponse(), 200)]);
        $this->actingAs($manager)->post(route('ai-planning.propose', $demand));
        $run = AiPlanningRun::firstOrFail();
        $invalid = [
            'summary' => 'Resumo aprovado.',
            'tasks' => [
                ['include' => 1, 'title' => 'A', 'responsibility_profile' => 'Design', 'estimate_minutes' => 10, 'assignee_id' => $externalProfessional->id],
                ['include' => 1, 'title' => 'B', 'responsibility_profile' => 'Design', 'estimate_minutes' => 10, 'assignee_id' => $externalProfessional->id],
            ],
        ];
        $this->post(route('ai-planning.approve', [$demand, $run]), $invalid)->assertSessionHasErrors('tasks.0.assignee_id');
        $this->assertSame(0, DemandTask::count());

        $valid = $invalid;
        $insideProfessional = User::query()->where('organization_id', $demand->organization_id)->where('role', UserRole::Professional->value)->firstOrFail();
        foreach ($valid['tasks'] as &$task) {
            $task['assignee_id'] = $insideProfessional->id;
        }
        unset($task);
        $this->post(route('ai-planning.approve', [$demand, $run]), $valid)->assertRedirect()->assertSessionHasNoErrors();
        $this->post(route('ai-planning.approve', [$demand, $run]), $valid)->assertSessionHasErrors('ai');
        $this->assertSame(2, DemandTask::count());
        $this->assertSame('Resumo aprovado.', $demand->fresh()->ai_summary);
    }

    public function test_missing_provider_configuration_and_wrong_roles_do_not_create_runs(): void
    {
        [, $manager, $professional, $demand] = $this->workspace();
        config(['services.ai_gateway.key' => '']);
        config(['services.ai_gateway.oidc_token' => '']);
        $this->actingAs($manager)->from(route('demands.show', $demand))
            ->post(route('ai-planning.propose', $demand))->assertSessionHasErrors('ai');
        config(['services.ai_gateway.key' => 'test-key']);
        config(['services.ai_gateway.oidc_token' => '']);
        $this->actingAs($professional)->post(route('ai-planning.propose', $demand))->assertForbidden();
        $this->assertSame(0, AiPlanningRun::count());
        $this->assertSame(0, DemandTask::count());
    }

    public function test_runtime_oidc_token_authenticates_gateway_when_api_key_is_not_set(): void
    {
        [, $manager, , $demand] = $this->workspace();
        config(['services.ai_gateway.key' => '', 'services.ai_gateway.oidc_token' => 'short-lived-vercel-token']);
        Http::fake(['https://ai-gateway.vercel.sh/v1/chat/completions' => Http::response($this->providerResponse(), 200)]);

        $this->actingAs($manager)->post(route('ai-planning.propose', $demand))->assertRedirect();

        Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer short-lived-vercel-token'));
        $this->assertSame('pending', AiPlanningRun::firstOrFail()->status);
        $this->assertSame(0, DemandTask::count());
    }

    public function test_openai_compatible_local_model_can_generate_a_proposal_without_api_key(): void
    {
        [, $manager, , $demand] = $this->workspace();
        config([
            'services.ai_gateway.provider' => 'openai-compatible',
            'services.ai_gateway.key' => '',
            'services.ai_gateway.oidc_token' => '',
            'services.ai_gateway.base_url' => 'http://127.0.0.1:11434/v1',
            'services.ai_gateway.model' => 'qwen2.5:3b',
            'services.ai_gateway.allow_unauthenticated' => true,
        ]);
        Http::fake(['http://127.0.0.1:11434/v1/chat/completions' => Http::response($this->providerResponse(), 200)]);

        $this->actingAs($manager)->post(route('ai-planning.propose', $demand))->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame('openai-compatible', AiPlanningRun::firstOrFail()->provider);
        Http::assertSent(fn ($request) => $request->url() === 'http://127.0.0.1:11434/v1/chat/completions'
            && $request['model'] === 'qwen2.5:3b'
            && ! $request->hasHeader('Authorization'));
    }

    public function test_discard_keeps_audit_and_creates_no_tasks(): void
    {
        [, $manager, , $demand] = $this->workspace();
        Http::fake(['https://ai-gateway.vercel.sh/v1/chat/completions' => Http::response($this->providerResponse(), 200)]);
        $this->actingAs($manager)->post(route('ai-planning.propose', $demand));
        $run = AiPlanningRun::firstOrFail();
        $this->delete(route('ai-planning.discard', [$demand, $run]))->assertRedirect();
        $this->assertSame('discarded', $run->fresh()->status);
        $this->assertSame($manager->id, $run->fresh()->reviewed_by);
        $this->assertSame(0, DemandTask::count());
        $this->assertDatabaseHas('demand_events', ['event_type' => 'ai_planning_discarded', 'actor_id' => $manager->id]);
    }

    public function test_invalid_provider_dependency_response_is_rejected_without_persistence(): void
    {
        [, $manager, , $demand] = $this->workspace();
        $response = $this->providerResponse();
        $proposal = json_decode($response['choices'][0]['message']['content'], true, 32, JSON_THROW_ON_ERROR);
        $proposal['tasks'][1]['depends_on'] = [1];
        $response['choices'][0]['message']['content'] = json_encode($proposal, JSON_THROW_ON_ERROR);
        Http::fake(['https://ai-gateway.vercel.sh/v1/chat/completions' => Http::response($response, 200)]);

        $this->actingAs($manager)->from(route('demands.show', $demand))
            ->post(route('ai-planning.propose', $demand))->assertSessionHasErrors('ai');
        $this->assertSame(0, AiPlanningRun::count());
        $this->assertSame(0, DemandTask::count());
    }

    public function test_planning_rejects_feedback_references_that_were_not_included(): void
    {
        [, $manager, , $demand] = $this->workspace();
        Http::fake(['https://ai-gateway.vercel.sh/v1/chat/completions' => Http::response($this->providerResponse([999999]), 200)]);

        $this->actingAs($manager)->from(route('demands.show', $demand))
            ->post(route('ai-planning.propose', $demand))->assertSessionHasErrors('ai');

        $this->assertSame(0, AiPlanningRun::count());
        $this->assertSame(0, DemandTask::count());
    }

    public function test_manager_can_reject_one_suggestion_and_remaining_dependencies_are_recalculated(): void
    {
        [, $manager, $professional, $demand] = $this->workspace();
        Http::fake(['https://ai-gateway.vercel.sh/v1/chat/completions' => Http::response($this->providerResponse(), 200)]);
        $this->actingAs($manager)->post(route('ai-planning.propose', $demand));
        $run = AiPlanningRun::firstOrFail();

        $this->post(route('ai-planning.approve', [$demand, $run]), [
            'summary' => 'Planejamento do site revisado.',
            'tasks' => [
                ['include' => 0],
                ['include' => 1, 'title' => 'Mapear páginas', 'responsibility_profile' => 'Design', 'estimate_minutes' => 75, 'assignee_id' => $professional->id],
            ],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame(1, $demand->tasks()->count());
        $task = $demand->tasks()->firstOrFail();
        $this->assertSame('Mapear páginas', $task->title);
        $this->assertSame(0, $task->dependencies()->count());
        $reviewed = $run->fresh()->reviewed_tasks['tasks'];
        $this->assertFalse($reviewed[0]['include']);
        $this->assertTrue($reviewed[1]['include']);
    }

    public function test_manager_can_remove_ai_summary_and_keep_the_original_brief_excerpt_on_the_board(): void
    {
        [, $manager, $professional, $demand] = $this->workspace();
        Http::fake(['https://ai-gateway.vercel.sh/v1/chat/completions' => Http::response($this->providerResponse(), 200)]);
        $this->actingAs($manager)->post(route('ai-planning.propose', $demand));
        $run = AiPlanningRun::firstOrFail();

        $this->post(route('ai-planning.approve', [$demand, $run]), [
            'summary' => '',
            'tasks' => [
                ['include' => 1, 'title' => 'Organizar briefing', 'responsibility_profile' => 'Atendimento', 'estimate_minutes' => 30, 'assignee_id' => $professional->id],
                ['include' => 0],
            ],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertNull($demand->fresh()->ai_summary);
        $this->actingAs($manager)->get(route('demands.index'))
            ->assertOk()
            ->assertSee('Briefing sintético de teste.')
            ->assertDontSee('Planejar site em duas etapas.');
    }

    public function test_non_manager_does_not_see_ai_planning_controls(): void
    {
        [, $manager, $professional, $demand] = $this->workspace();
        $demand->tasks()->create([
            'organization_id' => $demand->organization_id,
            'created_by' => $manager->id,
            'assigned_to' => $professional->id,
            'title' => 'Tarefa visível ao profissional',
            'status' => TaskStatus::Todo,
        ]);
        $this->actingAs($professional)->get(route('demands.show', $demand))
            ->assertOk()->assertDontSee('Planejamento com IA')->assertDontSee('Gerar proposta a partir do briefing');
    }

    private function workspace(string $slug = 'mix7'): array
    {
        $organization = Organization::create(['name' => ucfirst($slug), 'slug' => $slug]);
        $manager = User::factory()->create(['organization_id' => $organization->id, 'role' => UserRole::AgencyOwner, 'is_active' => true]);
        $professional = User::factory()->create(['organization_id' => $organization->id, 'role' => UserRole::Professional, 'is_active' => true]);
        $demand = Demand::create([
            'organization_id' => $organization->id,
            'created_by' => $manager->id,
            'title' => 'Site institucional',
            'brief' => 'Briefing sintético de teste.',
            'status' => DemandStatus::Planning,
        ]);

        return [$organization, $manager, $professional, $demand];
    }

    private function providerResponse(array $feedbackRefs = [], string $capacityObservation = '', array $assignmentRefs = []): array
    {
        return [
            'choices' => [['message' => ['content' => json_encode([
                'summary' => 'Planejar site em duas etapas.',
                'capacity_observation' => $capacityObservation,
                'questions' => ['Qual prazo?'],
                'tasks' => [
                    ['title' => 'Organizar briefing', 'rationale' => 'Confirmar objetivo.', 'responsibility_profile' => 'Atendimento', 'estimate_minutes' => 30, 'depends_on' => [], 'feedback_refs' => $feedbackRefs, 'suggested_assignee_ref' => $assignmentRefs[0] ?? null, 'assignment_rationale' => isset($assignmentRefs[0]) ? 'A IA sugere a pessoa de atendimento.' : ''],
                    ['title' => 'Mapear páginas', 'rationale' => 'Preparar estrutura.', 'responsibility_profile' => 'Design', 'estimate_minutes' => 60, 'depends_on' => [0], 'feedback_refs' => [], 'suggested_assignee_ref' => $assignmentRefs[1] ?? null, 'assignment_rationale' => isset($assignmentRefs[1]) ? 'A IA sugere a pessoa de design.' : ''],
                ],
            ], JSON_THROW_ON_ERROR)]]],
            'usage' => ['prompt_tokens' => 123, 'completion_tokens' => 45],
        ];
    }

    private function reviewLink(Demand $demand, User $manager, int $version): DemandReviewLink
    {
        return DemandReviewLink::create([
            'organization_id' => $demand->organization_id,
            'demand_id' => $demand->id,
            'created_by' => $manager->id,
            'version' => $version,
            'token_hash' => hash('sha256', $demand->id.'-'.$version),
            'material_url' => 'https://preview.example.test/version-'.$version,
            'expires_at' => now()->addDays(7),
        ]);
    }
}
