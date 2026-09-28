@extends('layouts.app')

@section('title', 'Conexão de IA · Plataforma Mix7')

@section('body')
<div class="shell">
    @include('layouts.navigation', ['active' => 'ai-settings'])
    <main class="main">
        @include('layouts.topbar')
        <div class="content narrow-content">
            <p class="eyebrow">Configuração central</p>
            <h1 class="heading">Inteligência artificial</h1>
            <p class="subheading">Uma conexão alimenta os assistentes, os briefings guiados, o planejamento e a revisão de criativos.</p>
            @include('partials.flash')
            @if ($errors->has('provider'))<div class="notice notice-error">{{ $errors->first('provider') }}</div>@endif
            <section class="panel ai-connection-card">
                <div class="section-heading"><div><h2>Conexão ativa</h2><p>{{ $configured ? 'Configurada para esta agência.' : 'Ainda não configurada. Nenhuma pergunta é enviada enquanto estiver desativada.' }}</p></div><span class="pill">{{ $configured ? 'Disponível' : 'Desativada' }}</span></div>
                @if ($saved?->tested_at)<p class="ai-settings-tested">Último teste bem-sucedido: {{ $saved->tested_at->format('d/m/Y H:i') }}</p>@endif
                <form method="post" action="{{ route('ai-settings.update') }}" class="ai-settings-form">@csrf @method('PUT')
                    <label class="field" for="ai-provider">Provedor do CRM</label>
                    <select id="ai-provider" name="provider" required>
                        <option value="ollama-gemma-local" selected>Gemma 3:4b local (Ollama)</option>
                    </select>
                    <p class="ai-provider-note">O modelo roda neste computador pelo Ollama. O CRM usa <code>http://127.0.0.1:11434</code> e o modelo <code>gemma3:4b</code>; nenhuma chave de API ou login do Codex é necessário. Mantenha o Ollama aberto para usar os assistentes.</p>
                    <label class="ai-enabled"><input type="checkbox" name="enabled" value="1" @checked(old('enabled', $saved?->enabled ?? false))> Ativar esta conexão para os assistentes da Mix7</label>
                    <div class="form-actions"><button class="primary-button" type="submit">Salvar configuração</button></div>
                </form>
                <form method="post" action="{{ route('ai-settings.test') }}" class="ai-test-form">@csrf<button type="submit" class="secondary-button">Testar conexão sem dados de cliente</button></form>
            </section>
            <section class="panel ai-connection-card"><h2>O que usa esta conexão</h2><ul class="ai-settings-uses"><li>Briefing conversacional antes de criar uma demanda.</li><li>Orientação contextual nas áreas internas.</li><li>Planejamento com sugestões de tarefas para revisão humana.</li><li>Leitura do feedback de aprovação, preservando comentário, versão e marcação.</li></ul><p class="field-help">As solicitações ficam neste computador. A qualidade e a velocidade dependem da memória e do processador/GPU disponíveis. Nenhuma sugestão é aplicada sem revisão humana.</p></section>
        </div>
    </main>
</div>
<style>.ai-connection-card{margin-top:20px}.ai-connection-card h2{font-size:16px;margin:0 0 12px}.ai-connection-card .section-heading h2{margin:0}.ai-settings-form{margin-top:18px}.ai-settings-form select{display:block;width:100%;margin:8px 0 12px;padding:11px 12px;border:1px solid #d6e0e1;border-radius:10px;background:#fff;color:#202e35;font:inherit;font-size:13px}.ai-provider-note,.ai-settings-tested{color:#667a82;font-size:12px;line-height:1.6}.ai-provider-note code{color:#204b61}.ai-enabled{display:flex;align-items:center;gap:8px;margin:12px 0;color:#52666e;font-size:12px}.ai-settings-uses{color:#52666e;font-size:13px;line-height:1.8}.ai-test-form{margin-top:4px}</style>
@endsection
