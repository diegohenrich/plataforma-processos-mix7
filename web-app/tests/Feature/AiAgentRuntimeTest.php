<?php

namespace Tests\Feature;

use App\Enums\DemandStatus;
use App\Enums\TaskStatus;
use App\Enums\UserRole;
use App\Jobs\ProcessAiAgentRun;
use App\Models\AiAgentRun;
use App\Models\Demand;
use App\Models\DemandReviewLink;
use App\Models\DemandTask;
use App\Models\KnowledgeItem;
use App\Models\Organization;
use App\Models\TaskTimeEntry;
use App\Models\TeamCapacitySnapshot;
use App\Models\User;
use App\Services\AiAgentRuntime;
use App\Services\AiAgentTools;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class AiAgentRuntimeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.ai_gateway.key' => 'test-key',
            'services.ai_gateway.oidc_token' => '',
            'services.ai_gateway.base_url' => 'https://ai-gateway.vercel.sh/v1',
            'services.ai_gateway.model' => 'test-provider/test-model',
        ]);
    }

    public function test_ask_queues_encrypted_question_and_only_saves_input_fingerprint(): void
    {
        [, $manager, , $demand] = $this->workspace();
        Http::fake();
        Bus::fake();

        $this->actingAs($manager)->post(route('ai-agent.ask', $demand), ['question' => 'Quais tarefas faltam?'])
            ->assertRedirect()->assertSessionHasNoErrors();

        $run = AiAgentRun::firstOrFail();
        $this->assertSame('queued', $run->status);
        $this->assertSame(hash('sha256', 'Quais tarefas faltam?'), $run->input_hash);
        $this->assertSame(21, $run->input_characters);
        $this->assertDatabaseMissing('ai_agent_runs', ['answer' => 'Quais tarefas faltam?']);
        $this->assertDatabaseHas('demand_events', ['demand_id' => $demand->id, 'actor_id' => $manager->id, 'event_type' => 'ai_agent_requested']);
        Bus::assertDispatched(ProcessAiAgentRun::class);
        Http::assertNothingSent();
    }

    public function test_approval_specialist_is_scoped_to_demand_and_client_feedback(): void
    {
        [$organization, $manager, , $demand] = $this->workspace();
        Bus::fake();

        $this->actingAs($manager)->post(route('ai-agent.ask', $demand), [
            'specialist' => 'approval_assistant',
            'question' => 'Organize os comentários da revisão.',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $run = AiAgentRun::where('agent', 'approval_assistant')->firstOrFail();
        Bus::assertDispatched(ProcessAiAgentRun::class);

        $tools = collect(app(AiAgentTools::class)->definitions($manager, $demand, $run->agent))
            ->pluck('function.name')->all();
        $this->assertSame(['read_demand_context', 'list_client_feedback'], $tools);

        Http::fakeSequence()->push($this->toolResponse([
            ['id' => 'feedback', 'function' => ['name' => 'list_client_feedback', 'arguments' => '{}']],
        ]), 200)->push($this->answerResponse('O comentário da versão 1 pede ajuste.'), 200);
        $result = app(AiAgentRuntime::class)->run($demand, $manager, 'Organize o feedback.', $run->agent);

        $this->assertSame('O comentário da versão 1 pede ajuste.', $result['answer']);
        $this->assertSame(['list_client_feedback'], collect($result['tool_trace'])->pluck('tool')->all());
        Http::assertSent(fn ($request) => collect($request['tools'])->pluck('function.name')->all() === ['read_demand_context', 'list_client_feedback']
            && str_contains($request['messages'][0]['content'], 'proponha rascunhos de tarefas')
            && str_contains($request['messages'][0]['content'], 'não crie tarefas'));
    }

    public function test_organization_assistant_queues_audited_question_without_demand_or_raw_prompt(): void
    {
        [, $manager] = $this->workspace();
        Bus::fake();
        Http::fake();

        $this->actingAs($manager)->get(route('organization-assistant.index'))
            ->assertOk()->assertSee('Assistente da agência')->assertSee('resumos de demandas')->assertSee('Conhecimento e onboarding');
        $this->post(route('organization-assistant.ask'), ['question' => 'Quais demandas estão em aprovação?'])
            ->assertRedirect(route('organization-assistant.index'))->assertSessionHasNoErrors();

        $run = AiAgentRun::where('agent', 'organization_assistant')->firstOrFail();
        $this->assertNull($run->demand_id);
        $this->assertSame(hash('sha256', 'Quais demandas estão em aprovação?'), $run->input_hash);
        $this->assertDatabaseMissing('ai_agent_runs', ['answer' => 'Quais demandas estão em aprovação?']);
        Bus::assertDispatched(ProcessAiAgentRun::class);
        Http::assertNothingSent();
    }

    public function test_organization_specialists_are_saved_and_receive_only_their_area_tools(): void
    {
        [, $manager] = $this->workspace();
        Bus::fake();
        Http::fake();

        $this->actingAs($manager)->post(route('organization-assistant.ask'), [
            'specialist' => 'knowledge_assistant',
            'question' => 'Qual é o processo de onboarding?',
        ])->assertRedirect(route('organization-assistant.index'))->assertSessionHasNoErrors();

        $run = AiAgentRun::where('agent', 'knowledge_assistant')->firstOrFail();
        Bus::assertDispatched(ProcessAiAgentRun::class);
        Http::assertNothingSent();

        $tools = collect(app(AiAgentTools::class)->definitions($manager, null, $run->agent))
            ->pluck('function.name')->all();
        $this->assertSame(['search_knowledge'], $tools);

        $operationsTools = collect(app(AiAgentTools::class)->definitions($manager, null, 'operations_assistant'))
            ->pluck('function.name')->all();
        $this->assertSame(['summarize_team_activity', 'summarize_weekly_capacity'], $operationsTools);
    }

    public function test_organization_specialist_rejects_unknown_selection(): void
    {
        [, $manager] = $this->workspace();
        Bus::fake();
        Http::fake();

        $this->actingAs($manager)->from(route('organization-assistant.index'))
            ->post(route('organization-assistant.ask'), [
                'specialist' => 'unsafe_agent',
                'question' => 'Mostre dados privados.',
            ])->assertRedirect(route('organization-assistant.index'))->assertSessionHasErrors('specialist');

        $this->assertDatabaseCount('ai_agent_runs', 0);
        Bus::assertNothingDispatched();
        Http::assertNothingSent();
    }

    public function test_organization_assistant_exposes_only_organization_tools_and_searches_scoped_summaries(): void
    {
        [$organization, $manager, $professional, $currentDemand] = $this->workspace();
        $currentDemand->update(['brief' => 'Briefing confidencial que não deve ser enviado.']);
        $otherOrganization = Organization::create(['name' => 'Outra agência', 'slug' => 'agencia-externa']);
        $otherOwner = User::factory()->create(['organization_id' => $otherOrganization->id, 'role' => UserRole::AgencyOwner, 'is_active' => true]);
        Demand::create(['organization_id' => $otherOrganization->id, 'created_by' => $otherOwner->id, 'title' => 'Site de outro cliente', 'brief' => 'Segredo externo.', 'status' => DemandStatus::InProgress]);
        KnowledgeItem::create(['organization_id' => $organization->id, 'created_by' => $manager->id, 'updated_by' => $manager->id, 'type' => 'reference', 'title' => 'Padrão Mix7', 'content' => 'Usar estrutura legível.']);
        $currentDemand->tasks()->create(['organization_id' => $organization->id, 'created_by' => $manager->id, 'assigned_to' => $professional->id, 'title' => 'Montar página', 'status' => TaskStatus::Todo, 'estimate_minutes' => 45]);

        $definitions = collect(app(AiAgentTools::class)->definitions($manager, null));
        $toolNames = $definitions->pluck('function.name');
        $this->assertTrue($toolNames->contains('search_organization_demands'));
        $this->assertTrue($toolNames->contains('search_knowledge'));
        $this->assertTrue($toolNames->contains('summarize_team_activity'));
        $this->assertFalse($toolNames->contains('read_demand_context'));
        $this->assertFalse($toolNames->contains('list_client_feedback'));

        Http::fakeSequence()->push($this->toolResponse([
            ['id' => 'org-demands', 'function' => ['name' => 'search_organization_demands', 'arguments' => '{"query":"Site"}']],
            ['id' => 'org-knowledge', 'function' => ['name' => 'search_knowledge', 'arguments' => '{"query":"Padrão Mix7"}']],
        ]), 200)->push($this->answerResponse('Encontrei demandas da Mix7 e uma referência interna.'), 200);
        $result = app(AiAgentRuntime::class)->run(null, $manager, 'Quais sites estão ativos e que padrão interno usamos?');

        $this->assertSame('Encontrei demandas da Mix7 e uma referência interna.', $result['answer']);
        $this->assertSame(['search_organization_demands', 'search_knowledge'], collect($result['tool_trace'])->pluck('tool')->all());
        $messages = collect(Http::recorded())->flatMap(fn ($record) => $record[0]['messages'] ?? [])->pluck('content')->implode(' ');
        $this->assertStringNotContainsString('Briefing confidencial', $messages);
        $this->assertStringNotContainsString('Segredo externo', $messages);
        $this->assertStringNotContainsString('Site de outro cliente', $messages);
        $this->assertStringContainsString('Padrão Mix7', $messages);
    }

    public function test_organization_assistant_is_limited_to_management_and_run_owner(): void
    {
        [, $manager, $professional] = $this->workspace();
        $client = User::factory()->create(['organization_id' => $manager->organization_id, 'role' => UserRole::Client, 'is_active' => true]);
        Bus::fake();
        Http::fake();

        $this->actingAs($professional)->get(route('organization-assistant.index'))->assertForbidden();
        $this->actingAs($client)->get(route('organization-assistant.index'))->assertForbidden();
        $this->actingAs($professional)->post(route('organization-assistant.ask'), ['question' => 'Consulta não autorizada?'])->assertForbidden();
        $run = AiAgentRun::create(['organization_id' => $manager->organization_id, 'demand_id' => null, 'requested_by' => $manager->id, 'agent' => 'organization_assistant', 'provider' => 'vercel-ai-gateway', 'model' => 'test-provider/test-model', 'input_hash' => hash('sha256', 'question'), 'input_characters' => 8, 'status' => 'queued']);
        $this->actingAs($professional)->getJson(route('organization-assistant.status', $run))->assertNotFound();
        $this->actingAs($manager)->getJson(route('organization-assistant.status', $run))->assertOk()->assertJson(['status' => 'queued']);
        Http::assertNothingSent();
    }

    public function test_organization_assistant_job_records_answer_and_provider_usage(): void
    {
        [, $manager] = $this->workspace();
        Http::fake(['https://ai-gateway.vercel.sh/v1/chat/completions' => Http::response($this->answerResponse('Há uma referência vigente e três demandas abertas.'), 200)]);
        $run = AiAgentRun::create(['organization_id' => $manager->organization_id, 'demand_id' => null, 'requested_by' => $manager->id, 'agent' => 'organization_assistant', 'provider' => 'vercel-ai-gateway', 'model' => 'test-provider/test-model', 'input_hash' => hash('sha256', 'question'), 'input_characters' => 8, 'status' => 'queued']);

        (new ProcessAiAgentRun($run->id, Crypt::encryptString('Resuma a atividade da agência.')))->handle(app(AiAgentRuntime::class));

        $this->assertSame('completed', $run->fresh()->status);
        $this->assertSame('Há uma referência vigente e três demandas abertas.', $run->fresh()->answer);
        $this->assertSame(12, $run->fresh()->input_tokens);
        $this->assertSame(7, $run->fresh()->output_tokens);
        $this->assertNull($run->fresh()->provider_cost);
        $this->assertNull($run->fresh()->demand_id);
        $this->assertSame([], $run->fresh()->tool_trace);
    }

    public function test_runtime_reads_organization_context_and_knowledge_without_writing_tasks(): void
    {
        [$organization, $manager, , $demand] = $this->workspace();
        $visible = $demand->tasks()->create(['organization_id' => $organization->id, 'created_by' => $manager->id, 'assigned_to' => $manager->id, 'title' => 'Montar estrutura', 'status' => TaskStatus::Todo]);
        KnowledgeItem::create(['organization_id' => $organization->id, 'created_by' => $manager->id, 'updated_by' => $manager->id, 'type' => 'reference', 'title' => 'Padrão de site', 'content' => 'Usar navegação clara e responsiva.']);

        Http::fakeSequence()->push($this->toolResponse([
            ['id' => 'call-context', 'function' => ['name' => 'read_demand_context', 'arguments' => '{}']],
            ['id' => 'call-knowledge', 'function' => ['name' => 'search_knowledge', 'arguments' => '{"query":"padrão site"}']],
        ]), 200)->push($this->answerResponse('Falta montar a estrutura, seguindo o padrão de site interno.'), 200);

        $result = app(AiAgentRuntime::class)->run($demand, $manager, 'O que falta nesta demanda?');

        $this->assertSame('Falta montar a estrutura, seguindo o padrão de site interno.', $result['answer']);
        $this->assertCount(2, $result['tool_trace']);
        $this->assertSame('read_demand_context', $result['tool_trace'][0]['tool']);
        $this->assertSame('search_knowledge', $result['tool_trace'][1]['tool']);
        $this->assertSame(1, DemandTask::count());
        $this->assertDatabaseHas('demand_tasks', ['id' => $visible->id, 'title' => 'Montar estrutura']);
        $messages = collect(Http::recorded())->flatMap(fn ($record) => $record[0]['messages'] ?? [])->pluck('content')->implode(' ');
        $this->assertStringContainsString('Montar estrutura', $messages);
        $this->assertStringContainsString('Padrão de site', $messages);
    }

    public function test_manager_agent_can_read_factual_team_activity_without_cross_organization_data(): void
    {
        [$organization, $manager, $professional, $demand] = $this->workspace();
        $colleague = User::factory()->create(['organization_id' => $organization->id, 'role' => UserRole::Professional, 'is_active' => true, 'name' => 'Colega Mix7']);
        $task = $demand->tasks()->create(['organization_id' => $organization->id, 'created_by' => $manager->id, 'assigned_to' => $professional->id, 'title' => 'Montar a página', 'status' => TaskStatus::Todo, 'estimate_minutes' => 90]);
        $complete = $demand->tasks()->create(['organization_id' => $organization->id, 'created_by' => $manager->id, 'assigned_to' => $professional->id, 'title' => 'Finalizar texto', 'status' => TaskStatus::Completed, 'estimate_minutes' => 30, 'completed_at' => now()->subDays(2)]);
        TaskTimeEntry::create(['organization_id' => $organization->id, 'task_id' => $complete->id, 'user_id' => $professional->id, 'started_at' => now()->subMinutes(50), 'ended_at' => now()->subMinutes(20)]);
        $otherOrganization = Organization::create(['name' => 'Outra agência', 'slug' => 'outra-agencia']);
        $otherManager = User::factory()->create(['organization_id' => $otherOrganization->id, 'role' => UserRole::AgencyOwner, 'is_active' => true]);
        $otherProfessional = User::factory()->create(['organization_id' => $otherOrganization->id, 'role' => UserRole::Professional, 'is_active' => true, 'name' => 'Pessoa de outra agência']);
        $otherDemand = Demand::create(['organization_id' => $otherOrganization->id, 'created_by' => $otherManager->id, 'title' => 'Outro cliente', 'brief' => 'Privado', 'status' => DemandStatus::InProgress]);
        $otherDemand->tasks()->create(['organization_id' => $otherOrganization->id, 'created_by' => $otherManager->id, 'assigned_to' => $otherProfessional->id, 'title' => 'Tarefa externa', 'status' => TaskStatus::Todo]);

        $definitions = collect(app(AiAgentTools::class)->definitions($manager, $demand));
        $this->assertTrue($definitions->contains(fn ($tool) => $tool['function']['name'] === 'summarize_team_activity'));
        $result = app(AiAgentTools::class)->execute('summarize_team_activity', [], $manager, $demand);
        $person = collect($result['result']['professionals'])->firstWhere('professional', $professional->name);

        $this->assertSame(1, $person['open_tasks_by_status'][TaskStatus::Todo->value]);
        $this->assertSame(90, $person['open_estimate_minutes']);
        $this->assertSame(1, $person['completed_tasks_last_30_days']);
        $this->assertSame(1800, $person['recorded_seconds_last_30_days']);
        $this->assertSame(0, collect($result['result']['professionals'])->where('professional', 'Pessoa de outra agência')->count());
        $this->assertSame('São registros operacionais, não avaliação, ranking ou cálculo de disponibilidade/capacidade.', $result['result']['interpretation']);
        $this->assertDatabaseHas('demand_tasks', ['id' => $task->id, 'status' => TaskStatus::Todo->value]);
    }

    public function test_operations_specialist_reads_only_manual_current_week_capacity_facts(): void
    {
        [$organization, $manager, $professional, $demand] = $this->workspace();
        $colleague = User::factory()->create(['organization_id' => $organization->id, 'role' => UserRole::Professional, 'is_active' => true, 'name' => 'Colega sem previsão']);
        $weekStart = now()->startOfWeek()->toDateString();
        $dueThisWeek = now()->startOfWeek()->addDays(2)->toDateString();
        TeamCapacitySnapshot::create([
            'organization_id' => $organization->id,
            'professional_id' => $professional->id,
            'recorded_by' => $manager->id,
            'week_start' => $weekStart,
            'scheduled_minutes' => 2400,
            'absences' => [['id' => 'absence-1', 'date' => $dueThisWeek, 'minutes' => 240]],
            'change_type' => 'availability_set',
        ]);
        $dated = $demand->tasks()->create(['organization_id' => $organization->id, 'created_by' => $manager->id, 'assigned_to' => $professional->id, 'title' => 'Título confidencial de uma tarefa', 'status' => TaskStatus::Todo, 'estimate_minutes' => 90, 'planned_due_on' => $dueThisWeek]);
        $demand->tasks()->create(['organization_id' => $organization->id, 'created_by' => $manager->id, 'assigned_to' => $professional->id, 'title' => 'Sem estimativa', 'status' => TaskStatus::Todo, 'planned_due_on' => $dueThisWeek]);
        $demand->tasks()->create(['organization_id' => $organization->id, 'created_by' => $manager->id, 'assigned_to' => $professional->id, 'title' => 'Sem prazo', 'status' => TaskStatus::Todo, 'estimate_minutes' => 30]);
        $demand->tasks()->create(['organization_id' => $organization->id, 'created_by' => $manager->id, 'assigned_to' => $professional->id, 'title' => 'Já concluída', 'status' => TaskStatus::Completed, 'estimate_minutes' => 50, 'planned_due_on' => $dueThisWeek, 'completed_at' => now()]);
        $otherOrganization = Organization::create(['name' => 'Outra agência', 'slug' => 'capacidade-outra-agencia']);
        $otherOwner = User::factory()->create(['organization_id' => $otherOrganization->id, 'role' => UserRole::AgencyOwner, 'is_active' => true]);
        User::factory()->create(['organization_id' => $otherOrganization->id, 'role' => UserRole::Professional, 'is_active' => true, 'name' => 'Pessoa externa']);

        $definitions = collect(app(AiAgentTools::class)->definitions($manager, null, 'operations_assistant'));
        $this->assertTrue($definitions->contains(fn ($tool) => $tool['function']['name'] === 'summarize_weekly_capacity'));
        $this->assertFalse(collect(app(AiAgentTools::class)->definitions($manager, null, 'organization_assistant'))
            ->contains(fn ($tool) => $tool['function']['name'] === 'summarize_weekly_capacity'));

        $result = app(AiAgentTools::class)->execute('summarize_weekly_capacity', [], $manager, null);
        $people = collect($result['result']['professionals'])->keyBy('professional');
        $person = $people->get($professional->name);
        $this->assertSame(2160, $person['available_minutes_after_absences']);
        $this->assertSame(90, $person['open_estimate_minutes_due_this_week']);
        $this->assertSame(2, $person['dated_open_tasks_due_this_week']);
        $this->assertSame(1, $person['dated_tasks_missing_estimate']);
        $this->assertSame(1, $person['open_tasks_without_due_date']);
        $this->assertTrue($person['capacity_recorded']);
        $this->assertFalse($people->get($colleague->name)['capacity_recorded']);
        $this->assertFalse($people->has('Pessoa externa'));
        $this->assertStringNotContainsString('Título confidencial de uma tarefa', json_encode($result['result'], JSON_THROW_ON_ERROR));
        $this->assertSame('summarize_weekly_capacity', $result['receipt']['tool']);
        $this->assertDatabaseHas('demand_tasks', ['id' => $dated->id, 'title' => 'Título confidencial de uma tarefa']);
        $this->assertDatabaseHas('users', ['id' => $otherOwner->id, 'organization_id' => $otherOrganization->id]);
    }

    public function test_professional_agent_definitions_do_not_include_team_activity_tool(): void
    {
        [, $manager, $professional, $demand] = $this->workspace();
        $demand->tasks()->create(['organization_id' => $demand->organization_id, 'created_by' => $manager->id, 'assigned_to' => $professional->id, 'title' => 'Tarefa individual', 'status' => TaskStatus::Todo]);

        $definitions = collect(app(AiAgentTools::class)->definitions($professional, $demand));

        $this->assertFalse($definitions->contains(fn ($tool) => $tool['function']['name'] === 'summarize_team_activity'));
        $this->assertFalse($definitions->contains(fn ($tool) => $tool['function']['name'] === 'summarize_weekly_capacity'));
        $this->expectException(HttpException::class);
        app(AiAgentTools::class)->execute('summarize_team_activity', [], $professional, $demand);
    }

    public function test_professional_cannot_directly_execute_weekly_capacity_tool(): void
    {
        [, , $professional] = $this->workspace();

        $this->expectException(HttpException::class);
        app(AiAgentTools::class)->execute('summarize_weekly_capacity', [], $professional, null);
    }

    public function test_manager_agent_searches_only_organization_demand_titles_and_returns_minimal_summary(): void
    {
        [$organization, $manager, , $currentDemand] = $this->workspace();
        $matchingDemand = Demand::create(['organization_id' => $organization->id, 'created_by' => $manager->id, 'title' => 'Site de campanha', 'brief' => 'Briefing privado que não deve ser retornado.', 'status' => DemandStatus::ClientApproval]);
        $matchingDemand->tasks()->create(['organization_id' => $organization->id, 'created_by' => $manager->id, 'assigned_to' => $manager->id, 'title' => 'Ajustar chamada', 'status' => TaskStatus::Todo]);
        $otherOrganization = Organization::create(['name' => 'Outra agência', 'slug' => 'outra-agencia']);
        $otherManager = User::factory()->create(['organization_id' => $otherOrganization->id, 'role' => UserRole::AgencyOwner, 'is_active' => true]);
        Demand::create(['organization_id' => $otherOrganization->id, 'created_by' => $otherManager->id, 'title' => 'Site reservado', 'brief' => 'Informação de outra organização.', 'status' => DemandStatus::InProgress]);

        $definitions = collect(app(AiAgentTools::class)->definitions($manager, $currentDemand));
        $this->assertTrue($definitions->contains(fn ($tool) => $tool['function']['name'] === 'search_organization_demands'));

        $result = app(AiAgentTools::class)->execute('search_organization_demands', ['query' => 'Site'], $manager, $currentDemand);
        $this->assertEqualsCanonicalizing(['Site institucional', 'Site de campanha'], collect($result['result']['demands'])->pluck('title')->all());
        $campaign = collect($result['result']['demands'])->firstWhere('title', 'Site de campanha');
        $this->assertSame('Aprovação do cliente', $campaign['stage']);
        $this->assertSame(1, $campaign['tasks']);
        $this->assertArrayNotHasKey('brief', $campaign);
        $this->assertStringNotContainsString('Site reservado', json_encode($result['result'], JSON_THROW_ON_ERROR));
    }

    public function test_ai_client_feedback_is_combined_across_versions_in_chronological_order(): void
    {
        [$organization, $manager, , $demand] = $this->workspace();
        $versionTwo = DemandReviewLink::create([
            'organization_id' => $organization->id, 'demand_id' => $demand->id, 'created_by' => $manager->id,
            'version' => 2, 'token_hash' => str_repeat('b', 64), 'material_url' => 'https://preview.example.test/v2', 'expires_at' => now()->addDay(),
        ]);
        $versionOne = DemandReviewLink::create([
            'organization_id' => $organization->id, 'demand_id' => $demand->id, 'created_by' => $manager->id,
            'version' => 1, 'token_hash' => str_repeat('a', 64), 'material_url' => 'https://preview.example.test/v1', 'expires_at' => now()->addDay(),
        ]);
        $versionTwo->responses()->create(['reviewer_name' => 'Cliente', 'type' => 'comment', 'comment' => 'Feedback mais recente na versão 2.', 'created_at' => now()->subMinute()]);
        $versionOne->responses()->create(['reviewer_name' => 'Cliente', 'type' => 'comment', 'comment' => 'Feedback mais antigo na versão 1.', 'created_at' => now()->subMinutes(5)]);

        $result = app(AiAgentTools::class)->execute('list_client_feedback', [], $manager, $demand);
        $feedback = $result['result']['feedback'];

        $this->assertSame(['Feedback mais antigo na versão 1.', 'Feedback mais recente na versão 2.'], array_column($feedback, 'comment'));
        $this->assertSame([1, 2], array_column($feedback, 'version'));
        $this->assertLessThan($feedback[1]['created_at'], $feedback[0]['created_at']);
    }

    public function test_professional_cannot_run_assistant_and_cost_is_unknown_if_not_reported(): void
    {
        [, $manager, $professional, $demand] = $this->workspace();
        $demand->tasks()->create(['organization_id' => $demand->organization_id, 'created_by' => $manager->id, 'assigned_to' => $professional->id, 'title' => 'Tarefa do profissional', 'status' => TaskStatus::Todo]);
        Http::fake(['https://ai-gateway.vercel.sh/v1/chat/completions' => Http::response($this->answerResponse('Sem consultas necessárias.'), 200)]);

        try {
            app(AiAgentRuntime::class)->run($demand, $professional, 'Resuma a demanda.');
            $this->fail('O profissional não deve usar o agente nesta primeira versão.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
        Http::assertNothingSent();
        $result = app(AiAgentRuntime::class)->run($demand, $manager, 'Resuma a demanda.');

        $this->assertNull($result['provider_cost']);
        Http::assertSent(fn ($request) => collect($request['tools'])->contains(fn ($tool) => $tool['function']['name'] === 'list_client_feedback'));
        config(['services.ai_gateway.key' => '', 'services.ai_gateway.oidc_token' => '']);
        $this->actingAs($manager)->get(route('demands.show', $demand))->assertOk()->assertSee('Assistente da demanda')->assertSee('Assistente ainda não configurado');
    }

    public function test_unconfigured_gateway_and_client_cannot_start_assistant(): void
    {
        [, $manager, , $demand] = $this->workspace();
        config(['services.ai_gateway.key' => '', 'services.ai_gateway.oidc_token' => '']);
        $this->actingAs($manager)->from(route('demands.show', $demand))->post(route('ai-agent.ask', $demand), ['question' => 'Dúvida válida?'])->assertSessionHasErrors('assistant');
        $client = User::factory()->create(['organization_id' => $demand->organization_id, 'role' => UserRole::Client, 'is_active' => true]);
        config(['services.ai_gateway.key' => 'test-key']);
        $this->actingAs($client)->post(route('ai-agent.ask', $demand), ['question' => 'Dúvida válida?'])->assertForbidden();
        $this->assertSame(0, AiAgentRun::count());
        Http::assertNothingSent();
    }

    public function test_assistant_can_call_a_local_openai_compatible_model_without_credentials(): void
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
        Http::fake(['http://127.0.0.1:11434/v1/chat/completions' => Http::response($this->answerResponse('Resposta local.'), 200)]);

        $result = app(AiAgentRuntime::class)->run($demand, $manager, 'Resuma a demanda.');

        $this->assertSame('Resposta local.', $result['answer']);
        Http::assertSent(fn ($request) => $request->url() === 'http://127.0.0.1:11434/v1/chat/completions'
            && $request['model'] === 'qwen2.5:3b'
            && ! $request->hasHeader('Authorization'));
    }

    public function test_local_provider_without_key_enables_both_ai_flows_and_queues_assistant(): void
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
        Bus::fake();
        Http::fake();

        $this->actingAs($manager)->get(route('demands.show', $demand))
            ->assertOk()
            ->assertSee('Perguntar ao assistente')
            ->assertSee('Gerar proposta a partir do briefing')
            ->assertDontSee('Assistente ainda não configurado');
        $this->post(route('ai-agent.ask', $demand), ['question' => 'Quais tarefas faltam?'])
            ->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame('openai-compatible', AiAgentRun::firstOrFail()->provider);
        Bus::assertDispatched(ProcessAiAgentRun::class);
        Http::assertNothingSent();
    }

    public function test_professional_cannot_read_another_users_run_status(): void
    {
        [, $manager, $professional, $demand] = $this->workspace();
        $run = AiAgentRun::create(['organization_id' => $demand->organization_id, 'demand_id' => $demand->id, 'requested_by' => $manager->id, 'agent' => 'demand_assistant', 'provider' => 'vercel-ai-gateway', 'model' => 'test-provider/test-model', 'input_hash' => hash('sha256', 'question'), 'input_characters' => 8, 'status' => 'queued']);

        $this->actingAs($professional)->getJson(route('ai-agent.status', [$demand, $run]))->assertForbidden();
    }

    public function test_job_records_answer_and_never_claims_unreported_provider_cost(): void
    {
        [, $manager, , $demand] = $this->workspace();
        Http::fake(['https://ai-gateway.vercel.sh/v1/chat/completions' => Http::response($this->answerResponse('Próxima etapa: executar.'), 200)]);
        $run = AiAgentRun::create(['organization_id' => $demand->organization_id, 'demand_id' => $demand->id, 'requested_by' => $manager->id, 'agent' => 'demand_assistant', 'provider' => 'vercel-ai-gateway', 'model' => 'test-provider/test-model', 'input_hash' => hash('sha256', 'question'), 'input_characters' => 8, 'status' => 'queued']);

        (new ProcessAiAgentRun($run->id, Crypt::encryptString('Qual a próxima etapa?')))->handle(app(AiAgentRuntime::class));

        $this->assertSame('completed', $run->fresh()->status);
        $this->assertSame('Próxima etapa: executar.', $run->fresh()->answer);
        $this->assertNull($run->fresh()->provider_cost);
        $this->assertNull($run->fresh()->cost_currency);
        $this->assertSame(0, DemandTask::count());
    }

    private function workspace(): array
    {
        $organization = Organization::create(['name' => 'Mix7', 'slug' => 'mix7']);
        $manager = User::factory()->create(['organization_id' => $organization->id, 'role' => UserRole::AgencyOwner, 'is_active' => true]);
        $professional = User::factory()->create(['organization_id' => $organization->id, 'role' => UserRole::Professional, 'is_active' => true]);
        $demand = Demand::create(['organization_id' => $organization->id, 'created_by' => $manager->id, 'title' => 'Site institucional', 'brief' => 'Briefing sintético de teste.', 'status' => DemandStatus::Planning]);

        return [$organization, $manager, $professional, $demand];
    }

    private function toolResponse(array $calls): array
    {
        return ['choices' => [['message' => ['role' => 'assistant', 'content' => null, 'tool_calls' => array_map(fn ($call) => ['id' => $call['id'], 'type' => 'function', 'function' => $call['function']], $calls)]]]];
    }

    private function answerResponse(string $answer): array
    {
        return ['choices' => [['message' => ['role' => 'assistant', 'content' => $answer]]], 'usage' => ['prompt_tokens' => 12, 'completion_tokens' => 7]];
    }
}
