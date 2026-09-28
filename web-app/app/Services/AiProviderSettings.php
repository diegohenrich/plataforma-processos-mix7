<?php

namespace App\Services;

use App\Models\AiProviderSetting;

class AiProviderSettings
{
    /** @return array{provider:string,base_url:string,model:string,key:string,enabled:bool,local_cli:string,source:string} */
    public function forOrganization(?int $organizationId): array
    {
        $saved = $organizationId
            ? AiProviderSetting::query()->where('organization_id', $organizationId)->first()
            : null;

        if ($saved) {
            return [
                'provider' => $saved->provider,
                'base_url' => (string) $saved->base_url,
                'model' => (string) $saved->model,
                'key' => (string) $saved->api_key,
                'enabled' => $saved->enabled,
                'local_cli' => (string) config('services.ai_gateway.claude_bin', 'claude'),
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
            'source' => 'environment',
        ];
    }

    /** @param array{provider:string,base_url:string,model:string,key:string,enabled:bool,local_cli:string,source:string} $settings */
    public function isConfigured(array $settings): bool
    {
        if (! $settings['enabled']) {
            return false;
        }

        if ($settings['provider'] === 'claude-code-subscription') {
            return app()->environment('local') && $settings['local_cli'] !== '';
        }

        if ($settings['model'] === '' || $settings['base_url'] === '') {
            return false;
        }

        return $settings['key'] !== ''
            || ($settings['provider'] === 'openai-compatible' && config('services.ai_gateway.allow_unauthenticated') === true);
    }

    public function isConfiguredFor(?int $organizationId): bool
    {
        return $this->isConfigured($this->forOrganization($organizationId));
    }
}
