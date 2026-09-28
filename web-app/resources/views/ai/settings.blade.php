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
                    <label class="field" for="ai-provider">Como conectar</label>
                    <select id="ai-provider" name="provider" required>
                        <option value="openai-compatible" @selected(old('provider', $saved?->provider ?? ($effective['source'] === 'environment' ? $effective['provider'] : 'openai-compatible')) === 'openai-compatible')>API compatível com OpenAI Chat Completions</option>
                        <option value="anthropic-api" @selected(old('provider', $saved?->provider) === 'anthropic-api')>API Anthropic Messages</option>
                        <option value="claude-code-subscription" @selected(old('provider', $saved?->provider) === 'claude-code-subscription')>Claude Code local com minha assinatura</option>
                    </select>
                    <p class="ai-provider-note" id="ai-provider-help">Compatível com provedores que implementam chat completions e ferramentas, como OpenAI, Gateway e serviços locais. Endereço, modelo e chave variam conforme o fornecedor.</p>
                    <div id="ai-api-fields">
                        <label class="field" for="ai-base-url">Endereço da API<input id="ai-base-url" name="base_url" type="url" value="{{ old('base_url', $saved?->base_url) }}" maxlength="255" placeholder="https://api.exemplo.com/v1"><span class="field-help">Use o endereço-base, sem incluir /chat/completions ou /messages.</span></label>
                        @error('base_url')<span class="error">{{ $message }}</span>@enderror
                        <label class="field" for="ai-model">Modelo<input id="ai-model" name="model" value="{{ old('model', $saved?->model) }}" maxlength="160" placeholder="Ex.: claude-sonnet-4-6 ou gpt-4.1-mini" autocomplete="off"></label>
                        @error('model')<span class="error">{{ $message }}</span>@enderror
                        <label class="field" for="ai-api-key">Chave da API<input id="ai-api-key" name="api_key" type="password" maxlength="4000" autocomplete="new-password" placeholder="{{ $saved?->api_key ? 'Chave salva; deixe vazio para manter' : 'Cole a chave privada do provedor' }}"><span class="field-help">A chave é criptografada no servidor, não volta ao navegador e não deve ser enviada ao GitHub.</span></label>
                        @if ($saved?->api_key)<label class="ai-clear-key"><input type="checkbox" name="clear_api_key" value="1"> Apagar a chave salva</label>@endif
                    </div>
                    <div id="ai-claude-fields" hidden>
                        <label class="field" for="ai-claude-model">Modelo do Claude Code (opcional)<input id="ai-claude-model" name="claude_model" value="{{ old('claude_model', $saved?->model) }}" placeholder="Deixe vazio para usar o padrão da conta"></label>
                        <p class="ai-provider-note">Usa o login que já estiver no Claude Code desta máquina. Se não estiver conectado, abra o terminal e execute <code>claude</code> para fazer login. A assinatura limita o uso à sua conta e cota; não é uma API compartilhada para produção.</p>
                    </div>
                    <label class="ai-enabled"><input type="checkbox" name="enabled" value="1" @checked(old('enabled', $saved?->enabled ?? false))> Ativar esta conexão para os assistentes da Mix7</label>
                    <div class="form-actions"><button class="primary-button" type="submit">Salvar configuração</button></div>
                </form>
                <form method="post" action="{{ route('ai-settings.test') }}" class="ai-test-form">@csrf<button type="submit" class="secondary-button">Testar conexão sem dados de cliente</button></form>
            </section>
            <section class="panel ai-connection-card"><h2>O que usa esta conexão</h2><ul class="ai-settings-uses"><li>Briefing conversacional antes de criar uma demanda.</li><li>Orientação contextual nas áreas internas.</li><li>Planejamento com sugestões de tarefas para revisão humana.</li><li>Leitura do feedback de aprovação, preservando comentário, versão e marcação.</li></ul><p class="field-help">Todo o texto enviado ao provedor sai da hospedagem. Ative para conteúdo real somente depois de aprovar a política de dados da Mix7.</p></section>
        </div>
    </main>
</div>
<script>
(() => {
    const provider = document.getElementById('ai-provider');
    const apiFields = document.getElementById('ai-api-fields');
    const claudeFields = document.getElementById('ai-claude-fields');
    const help = document.getElementById('ai-provider-help');
    const url = document.getElementById('ai-base-url');
    const model = document.getElementById('ai-model');
    const key = document.getElementById('ai-api-key');
    const claudeModel = document.getElementById('ai-claude-model');
    const update = () => {
        const localClaude = provider.value === 'claude-code-subscription';
        apiFields.hidden = localClaude;
        claudeFields.hidden = !localClaude;
        url.required = !localClaude;
        model.required = !localClaude;
        model.disabled = localClaude;
        url.disabled = localClaude;
        key.disabled = localClaude;
        claudeModel.disabled = !localClaude;
        help.textContent = provider.value === 'anthropic-api'
            ? 'Conecta diretamente ao protocolo Messages da Anthropic. Requer uma chave da Claude Platform; a assinatura do Claude Code não substitui uma chave de API no site compartilhado.'
            : provider.value === 'claude-code-subscription'
                ? 'Disponível somente nesta instância local, autenticada no Claude Code. A hospedagem compartilhada deve usar uma API de serviço.'
                : 'Compatível com provedores que implementam chat completions e ferramentas, como OpenAI, Gateway e serviços locais. Endereço, modelo e chave variam conforme o fornecedor.';
    };
    provider.addEventListener('change', update);
    update();
})();
</script>
<style>.ai-connection-card{margin-top:20px}.ai-connection-card h2{font-size:16px;margin:0 0 12px}.ai-connection-card .section-heading h2{margin:0}.ai-settings-form{margin-top:18px}.ai-settings-form select{display:block;width:100%;margin:8px 0 12px;padding:11px 12px;border:1px solid #d6e0e1;border-radius:10px;background:#fff;color:#202e35;font:inherit;font-size:13px}.ai-provider-note,.ai-settings-tested{color:#667a82;font-size:12px;line-height:1.6}.ai-provider-note code{color:#204b61}.ai-clear-key,.ai-enabled{display:flex;align-items:center;gap:8px;margin:12px 0;color:#52666e;font-size:12px}.ai-settings-uses{color:#52666e;font-size:13px;line-height:1.8}.ai-test-form{margin-top:4px}</style>
@endsection
