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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiProviderAndBriefingTest extends TestCase
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

    public function test_owner_can_save_encrypted_api_connection_and_only_owner_can_manage_it(): void
    {
        [$organization, $owner, $manager] = $this->workspace();
        $secret = 'test-secret-that-must-not-render';

        $this->actingAs($owner)->put(route('ai-settings.update'), [
            'provider' => 'openai-compatible', 'base_url' => 'https://api.example.test/v1',
            'model' => 'text-model-v1', 'api_key' => $secret, 'enabled' => '1',
        ])->assertRedirect(route('ai-settings.index'))->assertSessionHasNoErrors();

        $setting = AiProviderSetting::where('organization_id', $organization->id)->firstOrFail();
        $storedValue = DB::table('ai_provider_settings')->where('id', $setting->id)->value('api_key');
        $this->assertNotSame($secret, $storedValue);
        $this->assertSame($secret, $setting->api_key);
        $this->actingAs($owner)->get(route('ai-settings.index'))->assertOk()->assertDontSee($secret);
        $this->actingAs($manager)->get(route('ai-settings.index'))->assertForbidden();
        $this->actingAs($manager)->put(route('ai-settings.update'), [])->assertForbidden();
    }

    public function test_guided_briefing_uses_configured_provider_and_never_creates_a_demand(): void
    {
        [, $owner] = $this->workspace();
        $briefing = [
            'message' => 'Quem é o público principal?', 'title' => 'Site institucional',
            'brief' => "Objetivo: apresentar a empresa.\nPúblico: A confirmar.",
            'follow_up' => ['Qual ação o visitante deve realizar?'], 'ready' => false, 'module_fields' => (object) [],
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
            && str_contains($request['messages'][0]['content'], 'Nenhum dado será salvo'));
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

    public function test_switching_to_local_claude_subscription_clears_api_key_and_saves_optional_model(): void
    {
        [$organization, $owner] = $this->workspace();
        AiProviderSetting::create([
            'organization_id' => $organization->id, 'provider' => 'openai-compatible',
            'base_url' => 'https://api.example.test/v1', 'model' => 'old-model',
            'api_key' => 'old-encrypted-key', 'enabled' => true,
        ]);

        $this->actingAs($owner)->put(route('ai-settings.update'), [
            'provider' => 'claude-code-subscription', 'claude_model' => 'claude-sonnet-test', 'enabled' => '1',
        ])->assertRedirect(route('ai-settings.index'))->assertSessionHasNoErrors();

        $setting = AiProviderSetting::where('organization_id', $organization->id)->firstOrFail();
        $this->assertSame('claude-code-subscription', $setting->provider);
        $this->assertSame('claude-sonnet-test', $setting->model);
        $this->assertNull($setting->api_key);
        $this->assertNull($setting->base_url);
    }

    public function test_owner_can_enable_local_codex_chatgpt_login_without_an_api_key(): void
    {
        [$organization, $owner] = $this->workspace();
        AiProviderSetting::create([
            'organization_id' => $organization->id, 'provider' => 'openai-compatible',
            'base_url' => 'https://api.example.test/v1', 'model' => 'old-model',
            'api_key' => 'old-encrypted-key', 'enabled' => true,
        ]);

        $this->actingAs($owner)->put(route('ai-settings.update'), [
            'provider' => 'codex-chatgpt-subscription', 'codex_model' => '', 'enabled' => '1',
        ])->assertRedirect(route('ai-settings.index'))->assertSessionHasNoErrors();

        $setting = AiProviderSetting::where('organization_id', $organization->id)->firstOrFail();
        $this->assertSame('codex-chatgpt-subscription', $setting->provider);
        $this->assertSame('', $setting->model);
        $this->assertNull($setting->api_key);
        $this->assertNull($setting->base_url);
        $this->app['env'] = 'local';
        $effective = app(AiProviderSettings::class)->forOrganization((int) $organization->id);
        $this->assertTrue(app(AiProviderSettings::class)->isConfigured($effective));

        $this->app['env'] = 'production';
        $this->assertFalse(app(AiProviderSettings::class)->isConfigured($effective));
    }

    public function test_codex_cli_failures_are_classified_without_exposing_raw_provider_output(): void
    {
        $classify = new \ReflectionMethod(AiTextProvider::class, 'codexFailure');

        $quota = $classify->invoke(app(AiTextProvider::class), 'HTTP 429 usage limit exceeded; diagnostic bearer secret-do-not-show');
        $this->assertSame('usage_limit', $quota['category']);
        $this->assertStringContainsString('limite de uso', $quota['message']);
        $this->assertStringNotContainsString('secret-do-not-show', $quota['message']);

        $auth = $classify->invoke(app(AiTextProvider::class), 'authentication required');
        $this->assertSame('authentication', $auth['category']);
        $this->assertStringContainsString('codex login', $auth['message']);

        $network = $classify->invoke(app(AiTextProvider::class), 'connection timed out');
        $this->assertSame('connectivity', $network['category']);
        $this->assertStringContainsString('falha de conexão', $network['message']);
    }

    private function workspace(): array
    {
        $organization = Organization::create(['name' => 'Mix7', 'slug' => 'mix7-ai-'.uniqid()]);
        $owner = User::factory()->create(['organization_id' => $organization->id, 'role' => UserRole::AgencyOwner, 'is_active' => true]);
        $manager = User::factory()->create(['organization_id' => $organization->id, 'role' => UserRole::MarketingManager, 'is_active' => true]);

        return [$organization, $owner, $manager];
    }
}
