<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\AiAgentRun;
use App\Models\AiProviderSetting;
use App\Models\Demand;
use App\Models\DemandModuleDefinition;
use App\Models\Organization;
use App\Models\User;
use App\Services\AiProviderSettings;
use App\Services\AiTextProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiProviderAndBriefingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.ai_gateway.provider' => 'openai-compatible',
            'services.ai_gateway.key' => 'test-key',
            'services.ai_gateway.oidc_token' => '',
            'services.ai_gateway.base_url' => 'https://ai-gateway.vercel.sh/v1',
            'services.ai_gateway.model' => 'test-provider/test-model',
        ]);
    }

    public function test_owner_can_activate_local_gemma_and_only_owner_can_manage_it(): void
    {
        [$organization, $owner, $manager] = $this->workspace();
        $this->actingAs($owner)->put(route('ai-settings.update'), [
            'provider' => 'ollama-gemma-local', 'enabled' => '1',
        ])->assertRedirect(route('ai-settings.index'))->assertSessionHasNoErrors();

        $setting = AiProviderSetting::where('organization_id', $organization->id)->firstOrFail();
        $this->assertSame('ollama-gemma-local', $setting->provider);
        $this->assertSame('http://127.0.0.1:11434/v1', $setting->base_url);
        $this->assertSame('gemma3:4b', $setting->model);
        $this->assertNull($setting->api_key);
        $this->assertTrue(app(AiProviderSettings::class)->isConfigured(app(AiProviderSettings::class)->forOrganization((int) $organization->id)));
        $this->actingAs($owner)->get(route('ai-settings.index'))->assertOk()->assertSee('Gemma 3:4b local')->assertDontSee('Codex local');
        $this->actingAs($manager)->get(route('ai-settings.index'))->assertForbidden();
        $this->actingAs($manager)->put(route('ai-settings.update'), [])->assertForbidden();
    }

    public function test_demand_creation_shows_document_upload_for_guided_briefing(): void
    {
        [$organization, $owner] = $this->workspace();
        User::factory()->create([
            'organization_id' => $organization->id, 'role' => UserRole::Professional, 'is_active' => true,
        ]);
        AiProviderSetting::create([
            'organization_id' => $organization->id, 'provider' => 'openai-compatible',
            'base_url' => 'https://ai-gateway.vercel.sh/v1', 'model' => 'test-provider/test-model',
            'api_key' => 'test-key', 'enabled' => true,
        ]);

        $this->actingAs($owner)->get(route('demands.create'))
            ->assertOk()
            ->assertSee('Já tem um briefing ou transcrição?')
            ->assertSee('briefing-document')
            ->assertSee('application/pdf')
            ->assertSee('Escolha os responsáveis')
            ->assertSee('O arquivo original fica no seu navegador');
    }

    public function test_ai_settings_rejects_codex_claude_and_remote_api_providers(): void
    {
        [, $owner] = $this->workspace();
        foreach (['codex-chatgpt-subscription', 'claude-code-subscription', 'openai-compatible', 'anthropic-api'] as $provider) {
            $this->actingAs($owner)->put(route('ai-settings.update'), [
                'provider' => $provider, 'enabled' => '1',
            ])->assertSessionHasErrors('provider');
        }
        $this->assertDatabaseCount('ai_provider_settings', 0);
    }

    public function test_guided_briefing_uses_configured_provider_and_never_creates_a_demand(): void
    {
        [, $owner] = $this->workspace();
        $briefing = [
            'message' => 'Quem é o público principal?', 'title' => 'Site institucional',
            'brief' => "Objetivo: apresentar a empresa.\nPúblico: A confirmar.",
            'follow_up' => ['Qual ação o visitante deve realizar?'], 'ready' => false, 'module_fields' => (object) [],
            'tasks' => [['title' => 'Definir páginas e objetivo do site', 'estimate_minutes' => null]],
        ];
        $body = ['choices' => [['message' => ['role' => 'assistant', 'content' => json_encode($briefing, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)]]]];
        Http::fake(['https://ai-gateway.vercel.sh/v1/chat/completions' => Http::response($body, 200)]);

        $this->actingAs($owner)->postJson(route('ai-briefing.suggest'), [
            'module_key' => 'website_review',
            'messages' => [['role' => 'user', 'content' => 'Preciso de um site para apresentar a empresa.']],
        ])->assertOk()->assertJsonPath('title', 'Site institucional')
            ->assertJsonPath('ready', false)
            ->assertJsonPath('follow_up.0', 'Qual ação o visitante deve realizar?');

        $this->assertSame(0, Demand::count());
        Http::assertSent(fn ($request) => $request['model'] === 'test-provider/test-model'
            && isset($request['response_format']['json_schema']['schema'])
            && str_contains($request['messages'][0]['content'], 'Você decide') === false
            && str_contains($request['messages'][0]['content'], 'Nada é salvo até'));
    }

    public function test_guided_briefing_uses_attached_document_as_context_without_saving_it(): void
    {
        [, $owner] = $this->workspace();
        $draft = [
            'message' => 'Qual é a data de publicação?', 'title' => 'Campanha de inauguração',
            'brief' => 'Divulgar a inauguração da clínica. Público: moradores de Brasília.',
            'follow_up' => ['Qual é a data de publicação?'], 'ready' => false, 'module_fields' => (object) [],
            'tasks' => [['title' => 'Definir chamada para agendamento', 'estimate_minutes' => null]],
        ];
        Http::fake(['https://ai-gateway.vercel.sh/v1/chat/completions' => Http::response([
            'choices' => [['message' => ['role' => 'assistant', 'content' => json_encode($draft, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)]]],
        ], 200)]);

        $this->actingAs($owner)->postJson(route('ai-briefing.suggest'), [
            'module_key' => 'social_creative',
            'messages' => [['role' => 'user', 'content' => 'Organize o briefing deste documento.']],
            'document_name' => 'transcricao-cliente.pdf',
            'document_text' => 'Cliente pediu divulgação da inauguração de uma clínica em Brasília para moradores da região.',
        ])->assertOk()->assertJsonPath('title', 'Campanha de inauguração')
            ->assertJsonPath('tasks.0.title', 'Definir chamada para agendamento')
            ->assertJsonPath('tasks.0.estimate_minutes', null);

        Http::assertSent(fn ($request) => str_contains($request['messages'][0]['content'], 'transcricao-cliente.pdf')
            && str_contains($request['messages'][0]['content'], 'divulgação da inauguração de uma clínica'));
        $this->assertDatabaseCount('demands', 0);
        $this->assertDatabaseCount('demand_attachments', 0);
    }

    public function test_anthropic_messages_adapter_preserves_schema_tools_and_usage(): void
    {
        [$organization] = $this->workspace();
        AiProviderSetting::create([
            'organization_id' => $organization->id, 'provider' => 'anthropic-api',
            'base_url' => 'https://api.anthropic.com/v1', 'model' => 'claude-sonnet-test',
            'api_key' => 'anthropic-test-key', 'enabled' => true,
        ]);
        Http::fake(['https://api.anthropic.com/v1/messages' => Http::response([
            'content' => [['type' => 'text', 'text' => '{"ok":true}']],
            'usage' => ['input_tokens' => 11, 'output_tokens' => 7],
        ], 200)]);

        $result = app(AiTextProvider::class)->complete((int) $organization->id, [
            ['role' => 'system', 'content' => 'Seja conciso.'],
            ['role' => 'user', 'content' => 'Retorne o resultado.'],
        ], [], ['type' => 'object', 'properties' => ['ok' => ['type' => 'boolean']], 'required' => ['ok'], 'additionalProperties' => false], 80);

        $this->assertSame('{"ok":true}', $result['message']['content']);
        $this->assertSame(11, $result['usage']['input_tokens']);
        $this->assertSame(7, $result['usage']['output_tokens']);
        Http::assertSent(fn ($request) => $request->url() === 'https://api.anthropic.com/v1/messages'
            && $request->hasHeader('x-api-key', 'anthropic-test-key')
            && $request['system'] === 'Seja conciso.'
            && $request['output_config']['format']['type'] === 'json_schema'
            && $request['messages'][0]['role'] === 'user');
    }

    public function test_guided_briefing_fills_only_the_selected_organizations_custom_fields(): void
    {
        [$organization, $owner] = $this->workspace();
        DemandModuleDefinition::create([
            'organization_id' => $organization->id, 'created_by' => $owner->id, 'key' => 'email_campaign',
            'label' => 'Campanha de e-mail', 'description' => 'Planejar uma campanha de e-mail.', 'config_version' => 1,
            'fields' => [['key' => 'audience', 'label' => 'Público', 'type' => 'text', 'required' => true]],
            'workflow_steps' => [], 'is_active' => true,
        ]);
        $draft = [
            'message' => 'Qual lista vai receber a campanha?', 'title' => 'Campanha de lançamento',
            'brief' => 'Divulgar o lançamento. Público: clientes atuais.', 'follow_up' => ['Qual é a data?'],
            'ready' => false, 'module_fields' => ['audience' => 'Clientes atuais'],
            'tasks' => [['title' => 'Definir conteúdo da campanha', 'estimate_minutes' => null]],
        ];
        $body = ['choices' => [['message' => ['role' => 'assistant', 'content' => json_encode($draft, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)]]]];
        Http::fake(['https://ai-gateway.vercel.sh/v1/chat/completions' => Http::response($body, 200)]);

        $this->actingAs($owner)->postJson(route('ai-briefing.suggest'), [
            'module_key' => 'email_campaign', 'messages' => [['role' => 'user', 'content' => 'Quero divulgar um lançamento para os clientes atuais.']],
        ])->assertOk()->assertJsonPath('module_fields.audience', 'Clientes atuais');

        Http::assertSent(fn ($request) => isset($request['response_format']['json_schema']['schema']['properties']['module_fields']['properties']['audience'])
            && str_contains($request['messages'][0]['content'], 'Campanha de e-mail'));
        $this->assertDatabaseCount('demands', 0);
    }

    public function test_contextual_copilot_is_available_to_internal_roles_and_records_the_area(): void
    {
        [, , $manager] = $this->workspace();
        Http::fake(['https://ai-gateway.vercel.sh/v1/chat/completions' => Http::response([
            'choices' => [['message' => ['role' => 'assistant', 'content' => 'Confira a versão do material antes de responder ao cliente.']]],
            'usage' => ['prompt_tokens' => 9, 'completion_tokens' => 12],
        ], 200)]);

        $this->actingAs($manager)->postJson(route('contextual-assistant.ask'), [
            'area' => 'approvals', 'messages' => [['role' => 'user', 'content' => 'Como organizo um pedido marcado no vídeo?']],
        ])->assertOk()->assertJsonPath('answer', 'Confira a versão do material antes de responder ao cliente.');

        $run = AiAgentRun::where('agent', 'contextual_assistant')->firstOrFail();
        $this->assertSame('completed', $run->status);
        Http::assertSent(fn ($request) => str_contains($request['messages'][1]['content'], 'aprovação de criativos e feedback do cliente'));

        $client = User::factory()->create(['organization_id' => $manager->organization_id, 'role' => UserRole::Client, 'is_active' => true]);
        $this->actingAs($client)->postJson(route('contextual-assistant.ask'), [
            'area' => 'approvals', 'messages' => [['role' => 'user', 'content' => 'Como aprovo?']],
        ])->assertForbidden();
    }

    public function test_contextual_copilot_routes_all_ten_areas_to_the_provider(): void
    {
        [$organization] = $this->workspace();
        $areas = ['overview', 'demands', 'tasks', 'approvals', 'team', 'knowledge', 'service-access', 'capacity', 'evaluations', 'onboarding'];
        $labels = [
            'visão geral e navegação', 'criação e acompanhamento de demandas', 'tarefas e execução do trabalho',
            'aprovação de criativos e feedback do cliente', 'equipe e responsabilidades', 'conhecimento e onboarding',
            'acessos a serviços externos', 'disponibilidade e carga de trabalho', 'avaliações humanas de tarefas',
            'integração e treinamento de profissionais',
        ];
        Http::fake(['https://ai-gateway.vercel.sh/v1/chat/completions' => Http::response([
            'choices' => [['message' => ['role' => 'assistant', 'content' => 'Orientação fictícia para esta área.']]],
        ], 200)]);

        foreach ($areas as $area) {
            $professional = User::factory()->create([
                'organization_id' => $organization->id, 'role' => UserRole::Professional, 'is_active' => true,
            ]);
            $this->actingAs($professional)->postJson(route('contextual-assistant.ask'), [
                'area' => $area, 'messages' => [['role' => 'user', 'content' => 'Como começo este fluxo fictício?']],
            ])->assertOk()->assertJsonPath('answer', 'Orientação fictícia para esta área.');
        }

        $sentPrompts = Http::recorded()->map(fn ($pair) => $pair[0]['messages'][1]['content'])->all();
        foreach ($labels as $label) {
            $this->assertTrue(collect($sentPrompts)->contains(fn (string $prompt): bool => str_contains($prompt, $label)), "A área {$label} não foi incluída no contexto enviado.");
        }
        $this->assertCount(10, $sentPrompts);
    }

    public function test_gemma_uses_structured_application_managed_tool_requests(): void
    {
        [$organization] = $this->workspace();
        $this->app['env'] = 'local';
        AiProviderSetting::create([
            'organization_id' => $organization->id, 'provider' => 'ollama-gemma-local',
            'base_url' => 'http://127.0.0.1:11434/v1', 'model' => 'gemma3:4b', 'enabled' => true,
        ]);
        Http::fake(['http://127.0.0.1:11434/api/chat' => Http::response([
            'message' => ['role' => 'assistant', 'content' => json_encode([
                'action' => 'call_tools', 'answer' => '', 'tool_calls' => [['name' => 'list_knowledge', 'arguments' => ['query' => 'briefing']]],
            ], JSON_THROW_ON_ERROR)],
            'prompt_eval_count' => 60, 'eval_count' => 20,
        ], 200)]);

        $response = app(AiTextProvider::class)->complete((int) $organization->id, [
            ['role' => 'system', 'content' => 'Use consultas autorizadas.'],
            ['role' => 'user', 'content' => 'Como montar o briefing?'],
        ], [[
            'type' => 'function', 'function' => ['name' => 'list_knowledge', 'description' => 'Consultar conhecimento ativo.',
                'parameters' => ['type' => 'object', 'properties' => ['query' => ['type' => 'string']], 'required' => ['query'], 'additionalProperties' => false]],
        ]]);

        $this->assertSame('list_knowledge', $response['message']['tool_calls'][0]['function']['name']);
        $this->assertSame(['query' => 'briefing'], json_decode($response['message']['tool_calls'][0]['function']['arguments'], true));
        $this->assertSame(60, $response['usage']['input_tokens']);
        Http::assertSent(fn ($request) => $request->url() === 'http://127.0.0.1:11434/api/chat'
            && ! isset($request['tools'])
            && $request['model'] === 'gemma3:4b'
            && isset($request['format']['properties']['tool_calls'])
            && $request['options']['num_ctx'] === 8192
            && $request['options']['repeat_penalty'] > 1
            && $request['think'] === false
            && str_contains($request['messages'][0]['content'], 'list_knowledge'));
    }

    public function test_gemma_plain_answers_are_kept_concise_to_reduce_generation_time(): void
    {
        [$organization] = $this->workspace();
        $this->app['env'] = 'local';
        AiProviderSetting::create([
            'organization_id' => $organization->id, 'provider' => 'ollama-gemma-local',
            'base_url' => 'http://127.0.0.1:11434/v1', 'model' => 'gemma3:4b', 'enabled' => true,
        ]);
        Http::fake(['http://127.0.0.1:11434/api/chat' => Http::response([
            'message' => ['role' => 'assistant', 'content' => 'Resposta curta.'],
            'prompt_eval_count' => 20, 'eval_count' => 4,
        ], 200)]);

        app(AiTextProvider::class)->complete((int) $organization->id, [
            ['role' => 'system', 'content' => 'Responda de forma direta.'],
            ['role' => 'user', 'content' => 'Qual o próximo passo?'],
        ], [], null, 1800);

        Http::assertSent(fn ($request) => $request->url() === 'http://127.0.0.1:11434/api/chat'
            && $request['options']['num_predict'] === 600
            && $request['keep_alive'] === '5m');
    }

    private function workspace(): array
    {
        $organization = Organization::create(['name' => 'Mix7', 'slug' => 'mix7-ai-'.uniqid()]);
        $owner = User::factory()->create(['organization_id' => $organization->id, 'role' => UserRole::AgencyOwner, 'is_active' => true]);
        $manager = User::factory()->create(['organization_id' => $organization->id, 'role' => UserRole::MarketingManager, 'is_active' => true]);

        return [$organization, $owner, $manager];
    }
}
