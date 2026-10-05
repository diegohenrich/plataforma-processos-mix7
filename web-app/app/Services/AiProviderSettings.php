<?php

namespace App\Services;

use App\Models\AiProviderSetting;

class AiProviderSettings
{
    /** @return array{provider:string,base_url:string,model:string,key:string,enabled:bool,local_cli:string,codex_cli:string,source:string} */
    public function forOrganization(?int $organizationId): array
    {
        $saved = $organizationId
            ? AiProviderSetting::query()->where('organization_id', $organizationId)->first()
            : null;

        if ((string) config('services.ai_gateway.provider') === 'openclaw-internal' && ! app()->environment('testing')) {
            return [
                'provider' => 'openclaw-internal',
                'base_url' => rtrim((string) config('services.ai_gateway.base_url'), '/'),
                'model' => 'openclaw/mix7',
                'key' => (string) config('services.ai_gateway.key'),
                'enabled' => $saved?->provider === 'openclaw-internal' && $saved->enabled,
                'local_cli' => '', 'codex_cli' => '', 'source' => 'environment',
            ];
        }

        if ($saved) {
            return [
                'provider' => $saved->provider,
                'base_url' => (string) $saved->base_url,
                'model' => (string) $saved->model,
                'key' => (string) $saved->api_key,
                'enabled' => $saved->enabled,
                'local_cli' => (string) config('services.ai_gateway.claude_bin', 'claude'),
                'codex_cli' => (string) config('services.ai_gateway.codex_bin', 'codex'),
                'source' => 'database',
            ];
        }

        return [
            'provider' => (string) config('services.ai_gateway.provider'),
            'base_url' => rtrim((string) config('services.ai_gateway.base_url'), '/'),
            'model' => (string) config('services.ai_gateway.model'),
            'key' => (string) (config('services.ai_gateway.key') ?: config('services.ai_gateway.oidc_token')),
            'enabled' => true,
            'local_cli' => (string) config('services.ai_gateway.claude_bin', 'claude'),
            'codex_cli' => (string) config('services.ai_gateway.codex_bin', 'codex'),
            'source' => 'environment',
        ];
    }

    /** @param array{provider:string,base_url:string,model:string,key:string,enabled:bool,local_cli:string,codex_cli:string,source:string} $settings */
    public function isConfigured(array $settings): bool
    {
        if (! $settings['enabled']) {
            return false;
        }

        if ($settings['provider'] === 'openclaw-internal') {
            $url = parse_url($settings['base_url']);

            return is_array($url)
                && ($url['scheme'] ?? null) === 'http'
                && in_array($url['host'] ?? null, ['openclaw', '127.0.0.1'], true)
                && (int) ($url['port'] ?? 18789) === 18789
                && ($url['path'] ?? '') === '/v1'
                && ! isset($url['user']) && ! isset($url['pass'])
                && ! isset($url['query']) && ! isset($url['fragment'])
                && $settings['model'] === 'openclaw/mix7'
                && $settings['key'] !== '';
        }

        if ($settings['provider'] === 'ollama-gemma-local') {
            return app()->environment('testing')
                && $settings['base_url'] === 'http://127.0.0.1:11434/v1'
                && $settings['model'] === 'gemma3:4b';
        }

        if (in_array($settings['provider'], ['claude-code-subscription', 'codex-chatgpt-subscription'], true)) {
            return false;
        }

        // Provider fakes are retained for isolated feature tests only. The local app
        // and every deployed environment use the explicitly approved Gemma runtime.
        if (app()->environment('testing')) {
            return in_array($settings['provider'], ['openai-compatible', 'anthropic-api'], true)
                && $settings['base_url'] !== '' && $settings['model'] !== ''
                && ($settings['key'] !== '' || ($settings['provider'] === 'openai-compatible'
                    && config('services.ai_gateway.allow_unauthenticated') === true));
        }

        return false;
    }

    public function isConfiguredFor(?int $organizationId): bool
    {
        return $this->isConfigured($this->forOrganization($organizationId));
    }
}
