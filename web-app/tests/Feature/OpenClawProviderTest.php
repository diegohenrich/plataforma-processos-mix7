<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Services\AiProviderSettings;
use App\Services\AiTextProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class OpenClawProviderTest extends TestCase
{
    use RefreshDatabase;

    private function configure(): int
    {
        config([
            'services.ai_gateway.provider' => 'openclaw-internal',
            'services.ai_gateway.base_url' => 'http://openclaw:18789/v1',
            'services.ai_gateway.model' => 'openclaw/mix7',
            'services.ai_gateway.key' => 'synthetic-test-token',
        ]);

        return Organization::create(['name' => 'Teste OpenClaw', 'slug' => 'teste-openclaw'])->id;
    }

    public function test_openclaw_request_is_text_only_stateless_and_private(): void
    {
        $id = $this->configure();
        Http::fake(['http://openclaw:18789/v1/chat/completions' => Http::response([
            'choices' => [['message' => ['content' => 'Proposta para revisar.']]],
        ])]);

        $result = app(AiTextProvider::class)->complete($id, [['role' => 'user', 'content' => 'Pedido fictício']]);
        $this->assertSame('Proposta para revisar.', $result['message']['content']);
        Http::assertSent(fn ($request) => $request->url() === 'http://openclaw:18789/v1/chat/completions'
            && ! array_key_exists('tools', $request->data())
            && ! array_key_exists('user', $request->data())
            && $request->data()['model'] === 'openclaw/mix7');
    }

    public function test_openclaw_internal_tool_calls_are_rejected(): void
    {
        $id = $this->configure();
        Http::fake(['http://openclaw:18789/v1/chat/completions' => Http::response([
            'choices' => [['message' => ['content' => 'text', 'tool_calls' => [['id' => 'forbidden']]]]],
        ])]);

        $this->expectException(RuntimeException::class);
        app(AiTextProvider::class)->complete($id, [['role' => 'user', 'content' => 'Pedido fictício']]);
    }

    public function test_laravel_tool_proposals_are_text_only_until_laravel_authorizes_them(): void
    {
        $id = $this->configure();
        Http::fake(['http://openclaw:18789/v1/chat/completions' => Http::response([
            'choices' => [['message' => ['content' => json_encode([
                'action' => 'call_tools', 'answer' => '', 'tool_calls' => [
                    ['name' => 'list_knowledge', 'arguments' => ['query' => 'briefing']],
                ],
            ], JSON_THROW_ON_ERROR)]]],
        ])]);

        $result = app(AiTextProvider::class)->complete($id, [
            ['role' => 'system', 'content' => 'Ajude com o briefing.'],
            ['role' => 'user', 'content' => 'Pergunta fictícia'],
        ], [[
            'type' => 'function', 'function' => [
                'name' => 'list_knowledge', 'description' => 'Consulta autorizada pelo CRM',
                'parameters' => ['type' => 'object', 'properties' => ['query' => ['type' => 'string']]],
            ],
        ]]);

        $this->assertSame('list_knowledge', $result['message']['tool_calls'][0]['function']['name']);
        Http::assertSent(fn ($request) => ! array_key_exists('tools', $request->data())
            && str_contains($request->data()['messages'][0]['content'], 'list_knowledge'));
    }

    public function test_public_endpoint_is_not_considered_configured(): void
    {
        $id = $this->configure();
        config(['services.ai_gateway.base_url' => 'https://example.com/v1']);
        $this->assertFalse(app(AiProviderSettings::class)->isConfiguredFor($id));
    }
}
