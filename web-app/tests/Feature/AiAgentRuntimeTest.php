<?php

namespace Tests\Feature;

use App\Enums\DemandStatus;
use App\Enums\TaskStatus;
use App\Enums\UserRole;
use App\Jobs\ProcessAiAgentRun;
use App\Models\AiAgentRun;
use App\Models\Demand;
use App\Models\DemandTask;
use App\Models\KnowledgeItem;
use App\Models\Organization;
use App\Models\User;
use App\Services\AiAgentRuntime;
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
