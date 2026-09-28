<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\AiProviderSetting;
use App\Services\AiProviderSettings;
use App\Services\AiTextProvider;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
            'provider' => ['required', 'string', 'in:ollama-gemma-local'],
            'enabled' => ['sometimes', 'boolean'],
        ]);

        $setting = AiProviderSetting::query()->firstOrNew(['organization_id' => $request->user()->organization_id]);
        $setting->api_key = null;
        $setting->fill([
            'provider' => 'ollama-gemma-local',
            'base_url' => 'http://127.0.0.1:11434/v1',
            'model' => 'gemma3:4b',
            'enabled' => (bool) ($data['enabled'] ?? false),
            'tested_at' => null,
        ]);
        $setting->organization_id = $request->user()->organization_id;
        $setting->save();

        return to_route('ai-settings.index')->with('success', 'Configuração do Gemma 3:4b local salva para esta agência.');
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

        return back()->with('success', 'O Gemma 3:4b local respondeu ao teste fictício.');
    }

    private function authorizeOwner(Request $request): void
    {
        abort_unless($request->user()->is_active && $request->user()->role === UserRole::AgencyOwner && $request->user()->organization_id, 403);
    }
}
