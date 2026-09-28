<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\AiProviderSetting;
use App\Services\AiProviderSettings;
use App\Services\AiTextProvider;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use RuntimeException;

class AiProviderSettingsController extends Controller
{
    public function index(Request $request, AiProviderSettings $settings): View
    {
        $this->authorizeOwner($request);
        $saved = AiProviderSetting::query()->where('organization_id', $request->user()->organization_id)->first();
        $effective = $settings->forOrganization((int) $request->user()->organization_id);

        return view('ai.settings', [
            'saved' => $saved,
            'effective' => $effective,
            'configured' => $settings->isConfigured($effective),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $this->authorizeOwner($request);
        $data = $request->validate([
            'provider' => ['required', 'string', 'in:openai-compatible,anthropic-api,claude-code-subscription'],
            'base_url' => ['nullable', 'string', 'max:255'],
            'model' => ['nullable', 'string', 'max:160'],
            'claude_model' => ['nullable', 'string', 'max:160'],
            'api_key' => ['nullable', 'string', 'max:4000'],
            'enabled' => ['sometimes', 'boolean'],
            'clear_api_key' => ['sometimes', 'boolean'],
        ]);

        if (in_array($data['provider'], ['openai-compatible', 'anthropic-api'], true)) {
            $data['base_url'] = rtrim(trim((string) ($data['base_url'] ?? '')), '/');
            $data['model'] = trim((string) ($data['model'] ?? ''));
            if ($data['base_url'] === '' || $data['model'] === '') {
                return back()->withErrors(['base_url' => 'Informe o endereço da API e o nome exato do modelo.'])->withInput($request->except('api_key'));
            }
            if (! $this->safeEndpoint($data['base_url'])) {
                return back()->withErrors(['base_url' => 'Use um endereço HTTPS válido. HTTP só é aceito para serviços locais quando o ambiente está local.'])->withInput($request->except('api_key'));
            }
        } else {
            $data['base_url'] = null;
            $data['model'] = trim((string) ($data['claude_model'] ?? $data['model'] ?? ''));
        }

        $setting = AiProviderSetting::query()->firstOrNew(['organization_id' => $request->user()->organization_id]);
        $previousProvider = $setting->provider;
        $apiKey = trim((string) ($data['api_key'] ?? ''));
        if ($data['provider'] !== $previousProvider) {
            $setting->api_key = null;
        }
        if ($apiKey !== '') {
            $setting->api_key = $apiKey;
        } elseif (($data['clear_api_key'] ?? false) || $data['provider'] === 'claude-code-subscription') {
            $setting->api_key = null;
        }
        $setting->fill([
            'provider' => $data['provider'],
            'base_url' => $data['base_url'],
            'model' => $data['model'],
            'enabled' => (bool) ($data['enabled'] ?? false),
            'tested_at' => null,
        ]);
        $setting->organization_id = $request->user()->organization_id;
        $setting->save();

        return to_route('ai-settings.index')->with('success', 'Configuração salva. A chave fica criptografada no banco e nunca é exibida novamente.');
    }

    public function test(Request $request, AiProviderSettings $settings, AiTextProvider $provider): RedirectResponse
    {
        $this->authorizeOwner($request);
        $effective = $settings->forOrganization((int) $request->user()->organization_id);
        if (! $settings->isConfigured($effective)) {
            return back()->withErrors(['provider' => 'Salve e ative uma conexão válida antes de testar.']);
        }

        try {
            $result = $provider->complete((int) $request->user()->organization_id, [
                ['role' => 'system', 'content' => 'Responda de forma curta e em português.'],
                ['role' => 'user', 'content' => 'Responda somente: Conexão Mix7 funcionando.'],
            ], [], null, 40);
            if (! is_string($result['message']['content'] ?? null) || trim($result['message']['content']) === '') {
                throw new RuntimeException('O provedor respondeu sem texto.');
            }
            AiProviderSetting::query()->where('organization_id', $request->user()->organization_id)->update(['tested_at' => now()]);
        } catch (\Throwable $exception) {
            return back()->withErrors(['provider' => 'Teste não concluído: '.$exception->getMessage()]);
        }

        return back()->with('success', 'A conexão respondeu. Tokens e custo dependem do provedor e do modelo escolhidos.');
    }

    private function authorizeOwner(Request $request): void
    {
        abort_unless($request->user()->is_active && $request->user()->role === UserRole::AgencyOwner && $request->user()->organization_id, 403);
    }

    private function safeEndpoint(string $url): bool
    {
        $parts = parse_url($url);
        if (! is_array($parts) || ! isset($parts['scheme'], $parts['host']) || isset($parts['user']) || isset($parts['pass']) || isset($parts['fragment'])) {
            return false;
        }
        if ($parts['scheme'] === 'https') {
            return true;
        }
        $host = Str::lower($parts['host']);

        return app()->environment('local') && $parts['scheme'] === 'http'
            && in_array($host, ['localhost', '127.0.0.1', '::1'], true);
    }
}
