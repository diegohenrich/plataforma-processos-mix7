<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Process\Process;

class AiTextProvider
{
    /**
     * @param  list<array<string, mixed>>  $messages
     * @param  list<array<string, mixed>>  $tools
     * @param  array<string, mixed>|null  $jsonSchema
     * @return array{message:array{role:string,content:?string,tool_calls:list<array<string,mixed>>},usage:array<string,mixed>}
     */
    public function complete(int $organizationId, array $messages, array $tools = [], ?array $jsonSchema = null, int $maxTokens = 1800): array
    {
        $settings = app(AiProviderSettings::class)->forOrganization($organizationId);
        if (! app(AiProviderSettings::class)->isConfigured($settings)) {
            throw new RuntimeException('A conexão de IA não está configurada ou está desativada. Nenhuma pergunta foi enviada.');
        }

        if ($settings['provider'] === 'claude-code-subscription') {
            return $this->completeWithClaudeCode($settings, $messages, $tools, $jsonSchema, $maxTokens);
        }

        if ($settings['provider'] === 'anthropic-api') {
            return $this->completeWithAnthropic($settings, $messages, $tools, $jsonSchema, $maxTokens);
        }

        return $this->completeWithOpenAiCompatible($settings, $messages, $tools, $jsonSchema, $maxTokens);
    }

    /** @param array<string, mixed> $settings
     * @param  list<array<string, mixed>>  $messages
     * @param  list<array<string, mixed>>  $tools
     * @param  array<string, mixed>|null  $jsonSchema
     * @return array{message:array{role:string,content:?string,tool_calls:list<array<string,mixed>>},usage:array<string,mixed>}
     */
    private function completeWithOpenAiCompatible(array $settings, array $messages, array $tools, ?array $jsonSchema, int $maxTokens): array
    {
        $payload = [
            'model' => $settings['model'],
            'max_tokens' => $maxTokens,
            'stream' => false,
            'messages' => $messages,
        ];
        if ($tools !== []) {
            $payload['tools'] = $tools;
            $payload['tool_choice'] = 'auto';
        }
        if ($jsonSchema !== null) {
            $payload['response_format'] = ['type' => 'json_schema', 'json_schema' => [
                'name' => 'mix7_assistant_response', 'strict' => true, 'schema' => $jsonSchema,
            ]];
        }

        try {
            $request = Http::baseUrl($settings['base_url'])->acceptJson()->asJson()->connectTimeout(5)->timeout(55);
            if ($settings['key'] !== '') {
                $request = $request->withToken($settings['key']);
            }
            $response = $request->post('/chat/completions', $payload)->throw();
        } catch (ConnectionException $exception) {
            throw new RuntimeException('O serviço de IA não respondeu. Nenhuma alteração foi feita.', previous: $exception);
        } catch (RequestException $exception) {
            throw new RuntimeException('O provedor de IA recusou a solicitação. Confira modelo, endpoint e credencial.', previous: $exception);
        }

        $message = $response->json('choices.0.message');
        if (! is_array($message)) {
            throw new RuntimeException('O provedor retornou uma resposta inválida.');
        }

        return [
            'message' => [
                'role' => 'assistant',
                'content' => is_string($message['content'] ?? null) ? $message['content'] : null,
                'tool_calls' => is_array($message['tool_calls'] ?? null) ? $message['tool_calls'] : [],
            ],
            'usage' => [
                'input_tokens' => $this->integerOrNull($response->json('usage.prompt_tokens') ?? $response->json('usage.input_tokens')),
                'output_tokens' => $this->integerOrNull($response->json('usage.completion_tokens') ?? $response->json('usage.output_tokens')),
                'cost' => $response->json('usage.cost') ?? $response->json('providerMetadata.gateway.cost') ?? $response->json('provider_metadata.gateway.cost'),
            ],
        ];
    }

    /** @param array<string, mixed> $settings
     * @param  list<array<string, mixed>>  $messages
     * @param  list<array<string, mixed>>  $tools
     * @param  array<string, mixed>|null  $jsonSchema
     * @return array{message:array{role:string,content:?string,tool_calls:list<array<string,mixed>>},usage:array<string,mixed>}
     */
    private function completeWithAnthropic(array $settings, array $messages, array $tools, ?array $jsonSchema, int $maxTokens): array
    {
        $system = '';
        $claudeMessages = [];
        foreach ($messages as $message) {
            if (($message['role'] ?? '') === 'system') {
                $system .= (string) ($message['content'] ?? '')."\n";

                continue;
            }
            $role = ($message['role'] ?? '') === 'assistant' ? 'assistant' : 'user';
            $content = $this->anthropicContent($message);
            if ($content !== []) {
                $claudeMessages[] = ['role' => $role, 'content' => $content];
            }
        }

        $payload = ['model' => $settings['model'], 'max_tokens' => $maxTokens, 'messages' => $claudeMessages];
        if ($system !== '') {
            $payload['system'] = trim($system);
        }
        if ($tools !== []) {
            $payload['tools'] = array_map(fn (array $tool): array => [
                'name' => $tool['function']['name'],
                'description' => $tool['function']['description'] ?? '',
                'input_schema' => $tool['function']['parameters'] ?? ['type' => 'object', 'properties' => new \stdClass],
            ], $tools);
            $payload['tool_choice'] = ['type' => 'auto', 'disable_parallel_tool_use' => true];
        }
        if ($jsonSchema !== null) {
            $payload['output_config'] = ['format' => ['type' => 'json_schema', 'schema' => $jsonSchema]];
        }

        $endpoint = str_ends_with($settings['base_url'], '/v1') ? '/messages' : '/v1/messages';
        try {
            $response = Http::baseUrl($settings['base_url'])
                ->acceptJson()->asJson()->withHeaders(['anthropic-version' => '2023-06-01', 'x-api-key' => $settings['key']])
                ->connectTimeout(5)->timeout(55)->post($endpoint, $payload)->throw();
        } catch (ConnectionException $exception) {
            throw new RuntimeException('O serviço de IA não respondeu. Nenhuma alteração foi feita.', previous: $exception);
        } catch (RequestException $exception) {
            throw new RuntimeException('O provedor Anthropic recusou a solicitação. Confira modelo, endpoint e credencial.', previous: $exception);
        }

        $blocks = $response->json('content', []);
        $text = collect($blocks)->where('type', 'text')->pluck('text')->implode("\n");
        $toolCalls = collect($blocks)->where('type', 'tool_use')->map(fn (array $block): array => [
            'id' => $block['id'] ?? (string) Str::uuid(),
            'type' => 'function',
            'function' => ['name' => $block['name'] ?? '', 'arguments' => json_encode($block['input'] ?? [], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)],
        ])->values()->all();

        return [
            'message' => ['role' => 'assistant', 'content' => $text !== '' ? $text : null, 'tool_calls' => $toolCalls],
            'usage' => ['input_tokens' => $this->integerOrNull($response->json('usage.input_tokens')), 'output_tokens' => $this->integerOrNull($response->json('usage.output_tokens')), 'cost' => null],
        ];
    }

    /** @return list<array<string, mixed>> */
    private function anthropicContent(array $message): array
    {
        $role = $message['role'] ?? '';
        if ($role === 'tool') {
            return [[
                'type' => 'tool_result',
                'tool_use_id' => $message['tool_call_id'] ?? '',
                'content' => (string) ($message['content'] ?? ''),
            ]];
        }
        if ($role === 'assistant' && is_array($message['tool_calls'] ?? null)) {
            $blocks = [];
            foreach ($message['tool_calls'] as $toolCall) {
                $arguments = $toolCall['function']['arguments'] ?? '{}';
                $blocks[] = [
                    'type' => 'tool_use',
                    'id' => $toolCall['id'] ?? (string) Str::uuid(),
                    'name' => $toolCall['function']['name'] ?? '',
                    'input' => is_string($arguments) ? (json_decode($arguments, true) ?: []) : [],
                ];
            }
            if (is_string($message['content'] ?? null) && $message['content'] !== '') {
                array_unshift($blocks, ['type' => 'text', 'text' => $message['content']]);
            }

            return $blocks;
        }

        return [['type' => 'text', 'text' => (string) ($message['content'] ?? '')]];
    }

    /** @param array<string, mixed> $settings
     * @param  list<array<string, mixed>>  $messages
     * @param  list<array<string, mixed>>  $tools
     * @param  array<string, mixed>|null  $jsonSchema
     * @return array{message:array{role:string,content:?string,tool_calls:list<array<string,mixed>>},usage:array<string,mixed>}
     */
    private function completeWithClaudeCode(array $settings, array $messages, array $tools, ?array $jsonSchema, int $maxTokens): array
    {
        if (! app()->environment('local')) {
            throw new RuntimeException('Claude Code por assinatura está disponível somente na instância local. Em hospedagem compartilhada, use uma API de serviço ou chave de conta de serviço.');
        }

        $prompt = $this->claudePrompt($messages, $tools);
        $schema = $jsonSchema;
        if ($tools !== []) {
            $schema = [
                'type' => 'object',
                'properties' => [
                    'action' => ['type' => 'string', 'enum' => ['answer', 'call_tools']],
                    'answer' => ['type' => 'string'],
                    'tool_calls' => ['type' => 'array', 'items' => [
                        'type' => 'object',
                        'properties' => [
                            'name' => ['type' => 'string', 'enum' => collect($tools)->pluck('function.name')->all()],
                            'arguments' => ['type' => 'object', 'additionalProperties' => true],
                        ],
                        'required' => ['name', 'arguments'], 'additionalProperties' => false,
                    ]],
                ],
                'required' => ['action', 'answer', 'tool_calls'], 'additionalProperties' => false,
            ];
            $prompt .= "\n\nSe precisar consultar uma ferramenta, retorne action=call_tools, answer vazio e tool_calls com nome e argumentos. Se não precisar, retorne action=answer e tool_calls vazio. Nunca invente resultados de ferramentas.";
        }

        $command = [$settings['local_cli'], '--bare', '-p', '--output-format', 'json', '--no-session-persistence', '--tools', ''];
        if ($settings['model'] !== '') {
            $command[] = '--model';
            $command[] = $settings['model'];
        }
        if ($schema !== null) {
            $command[] = '--json-schema';
            $command[] = json_encode($schema, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        }

        try {
            $process = new Process($command, null, null, $prompt, 70);
            $process->run();
        } catch (\Throwable $exception) {
            throw new RuntimeException('Não foi possível iniciar o Claude Code local. Confira a instalação do Claude Code.', previous: $exception);
        }

        $output = trim($process->getOutput());
        $result = json_decode($output, true);
        if (! is_array($result)) {
            $error = trim($process->getErrorOutput());
            throw new RuntimeException($error !== '' ? mb_substr($error, 0, 220) : 'O Claude Code local retornou uma resposta inválida.');
        }
        if (($result['is_error'] ?? false) || ! is_string($result['result'] ?? null)) {
            $message = (string) ($result['result'] ?? $process->getErrorOutput() ?: 'O Claude Code não concluiu a resposta.');
            if (str_contains(mb_strtolower($message), 'not logged in')) {
                $message = 'O Claude Code ainda não está autenticado nesta máquina. Abra o terminal e execute claude para entrar com sua assinatura.';
            }
            throw new RuntimeException(mb_substr($message, 0, 240));
        }

        $content = trim($result['result']);
        $normalizedTools = [];
        if ($tools !== []) {
            $decoded = json_decode($content, true);
            if (! is_array($decoded) || ! in_array($decoded['action'] ?? null, ['answer', 'call_tools'], true)) {
                throw new RuntimeException('O Claude Code não retornou o formato estruturado esperado.');
            }
            $content = (string) ($decoded['answer'] ?? '');
            foreach (($decoded['tool_calls'] ?? []) as $call) {
                if (! is_array($call) || ! is_string($call['name'] ?? null) || ! is_array($call['arguments'] ?? null)) {
                    throw new RuntimeException('O Claude Code retornou uma consulta inválida.');
                }
                $normalizedTools[] = [
                    'id' => (string) Str::uuid(),
                    'type' => 'function',
                    'function' => ['name' => $call['name'], 'arguments' => json_encode($call['arguments'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)],
                ];
            }
        }

        return [
            'message' => ['role' => 'assistant', 'content' => $content !== '' ? $content : null, 'tool_calls' => $normalizedTools],
            'usage' => [
                'input_tokens' => $this->integerOrNull($result['usage']['input_tokens'] ?? null),
                'output_tokens' => $this->integerOrNull($result['usage']['output_tokens'] ?? null),
                'cost' => null,
            ],
        ];
    }

    /** @param list<array<string, mixed>> $messages
     * @param  list<array<string, mixed>>  $tools
     */
    private function claudePrompt(array $messages, array $tools): string
    {
        $parts = [];
        foreach ($messages as $message) {
            $parts[] = strtoupper((string) ($message['role'] ?? 'user')).":\n".(string) ($message['content'] ?? '');
        }
        if ($tools !== []) {
            $parts[] = "FERRAMENTAS DE CONSULTA SOMENTE LEITURA PERMITIDAS:\n".json_encode($tools, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        }

        return implode("\n\n", $parts);
    }

    private function integerOrNull(mixed $value): ?int
    {
        return is_numeric($value) && (int) $value >= 0 ? (int) $value : null;
    }
}
