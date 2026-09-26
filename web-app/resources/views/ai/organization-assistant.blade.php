@extends('layouts.app')

@section('title', 'Assistente da agência · Plataforma Mix7')

@section('body')
<div class="shell">
    @include('layouts.navigation', ['active' => 'organization-assistant'])
    <main class="main">
        @include('layouts.topbar')
        <div class="content">
            <p class="eyebrow">IA assistida</p>
            <h1 class="heading">Assistente da agência</h1>
            <p class="subheading">Escolha a área de ajuda. Cada especialista consulta somente os dados necessários para responder.</p>
            @if (session('success'))<div class="notice" role="status">{{ session('success') }}</div>@endif
            @if ($errors->any())<div class="notice notice-error" role="alert">{{ $errors->first() }}</div>@endif
            <section class="card organization-assistant-card">
                <div class="organization-assistant-heading"><div><h2>Especialistas da agência</h2><p>Todos são somente leitura. Você revisa a resposta; nenhuma ação é aplicada automaticamente.</p></div><span class="assistant-badge">Revisão humana</span></div>
                <div class="organization-assistant-scope"><strong>Áreas disponíveis</strong><ul><li>Geral: conhecimento, resumos de demandas e atividade agregada da equipe.</li><li>Conhecimento e onboarding: referências e instruções internas ativas.</li><li>Operação e produção: contagens e tempos agregados dos últimos 30 dias, sem nota ou ranking.</li></ul></div>
                <p class="assistant-privacy">A pergunta e os resultados das consultas serão enviados ao provedor de IA configurado. Não inclua senhas nem dados pessoais desnecessários. Nenhuma tarefa, etapa ou aprovação será alterada.</p>
                @if ($configured)
                    <form method="post" action="{{ route('organization-assistant.ask') }}" class="organization-assistant-form">@csrf
                        <label class="field" for="organization-assistant-specialist">Área de ajuda</label>
                        <select id="organization-assistant-specialist" name="specialist">
                            <option value="organization_assistant" @selected(old('specialist', 'organization_assistant') === 'organization_assistant')>Assistente geral</option>
                            <option value="knowledge_assistant" @selected(old('specialist') === 'knowledge_assistant')>Conhecimento e onboarding</option>
                            <option value="operations_assistant" @selected(old('specialist') === 'operations_assistant')>Operação e produção</option>
                        </select>
                        <label class="field" for="organization-assistant-question">O que você precisa entender?</label>
                        <textarea id="organization-assistant-question" name="question" rows="4" minlength="3" maxlength="3000" required placeholder="Ex.: Quais demandas com “site” estão em aprovação e quais referências internas se aplicam?">{{ old('question') }}</textarea>
                        <div class="organization-assistant-footer"><span>Limite de três consultas por minuto. O tempo depende do provedor e da fila.</span><button class="primary-button" type="submit">Perguntar ao assistente</button></div>
                    </form>
                @else
                    <div class="notice notice-info">O assistente ainda não está configurado. Nenhuma pergunta será enviada enquanto faltar modelo e provedor. A configuração de custos e política de dados ainda não foi aprovada para conteúdo real.</div>
                @endif
            </section>
            <section class="organization-assistant-history">
                <div class="section-heading"><div><h2>Suas consultas recentes</h2><p>As perguntas não são guardadas em texto; o histórico contém resposta, fontes consultadas e uso reportado.</p></div></div>
                @forelse ($runs as $run)
                    <article class="card organization-assistant-run" data-status-url="{{ in_array($run->status, ['queued', 'running'], true) ? route('organization-assistant.status', $run) : '' }}" aria-live="polite">
                        <div class="organization-assistant-run-meta"><strong>{{ match ($run->agent) {'knowledge_assistant' => 'Conhecimento e onboarding', 'operations_assistant' => 'Operação e produção', default => 'Assistente geral'} }} · {{ $run->input_characters }} caracteres</strong><time datetime="{{ $run->created_at->toISOString() }}">{{ $run->created_at->format('d/m/Y H:i') }}</time><span class="assistant-state assistant-state-{{ $run->status }}">{{ match ($run->status) {'queued' => 'Na fila', 'running' => 'Consultando', 'completed' => 'Concluída', 'failed' => 'Falhou', default => $run->status} }}</span></div>
                        @if ($run->answer)<p class="organization-assistant-answer">{{ $run->answer }}</p>@elseif ($run->status === 'failed')<p class="organization-assistant-error">{{ $run->error_message ?: 'Não foi possível concluir esta consulta.' }}</p>@else<p class="organization-assistant-pending">O assistente está preparando a resposta…</p>@endif
                        @if ($run->tool_trace)<p class="organization-assistant-sources"><strong>Fontes:</strong> @foreach ($run->tool_trace as $receipt){{ match ($receipt['tool'] ?? '') {'search_knowledge' => 'conhecimento interno', 'search_organization_demands' => 'demandas da organização', 'summarize_team_activity' => 'atividade agregada da equipe', default => 'consulta recusada'} }}@if (!$loop->last), @endif @endforeach</p>@endif
                        @if ($run->status === 'completed')<p class="organization-assistant-usage">Tokens: {{ $run->input_tokens ?? '—' }} entrada · {{ $run->output_tokens ?? '—' }} saída · custo informado pelo provedor: {{ $run->provider_cost !== null ? '$'.number_format((float) $run->provider_cost, 6, '.', ',').' USD' : 'não informado' }}</p>@endif
                    </article>
                @empty
                    <div class="empty-state"><h3>Nenhuma consulta enviada</h3><p>Quando você fizer uma pergunta, a resposta e as fontes ficarão registradas aqui.</p></div>
                @endforelse
                <div class="pagination-wrap">{{ $runs->links() }}</div>
            </section>
        </div>
    </main>
</div>
<style>
.organization-assistant-card{margin-top:22px}.organization-assistant-heading,.organization-assistant-run-meta{display:flex;align-items:center;justify-content:space-between;gap:12px}.organization-assistant-heading h2,.organization-assistant-history h2{margin:0;font-size:18px}.organization-assistant-heading p,.organization-assistant-history .section-heading p{margin:6px 0;color:#718087;font-size:12px;line-height:1.55}.organization-assistant-scope{margin-top:18px;padding:14px 16px;border:1px solid #dcebee;border-radius:12px;background:#f5fbfd;color:#52666e;font-size:12px;line-height:1.6}.organization-assistant-scope strong{color:#204b61}.organization-assistant-scope ul{margin:7px 0 0;padding-left:19px}.organization-assistant-scope li+li{margin-top:4px}.organization-assistant-form{margin-top:18px}.organization-assistant-form .field{display:block;color:#3d555e;font-size:13px;font-weight:650}.organization-assistant-form textarea,.organization-assistant-form select{display:block;width:100%;margin-top:8px;border:1px solid #d6e0e1;border-radius:11px;padding:12px 13px;font:inherit;font-size:14px;color:#202e35;background:#fff}.organization-assistant-form textarea{resize:vertical}.organization-assistant-form select{margin-bottom:14px}.organization-assistant-footer{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-top:10px;color:#718087;font-size:11px}.organization-assistant-history{margin-top:28px}.organization-assistant-history .section-heading{margin-bottom:12px}.organization-assistant-run{margin-top:12px}.organization-assistant-run-meta{justify-content:flex-start;flex-wrap:wrap;color:#718087;font-size:11px}.organization-assistant-run-meta strong{color:#204b61}.organization-assistant-answer{white-space:pre-wrap;color:#344d56;font-size:13px;line-height:1.7}.organization-assistant-error{color:#a73d39;font-size:12px}.organization-assistant-pending,.organization-assistant-sources,.organization-assistant-usage{color:#718087;font-size:11px;line-height:1.6}.organization-assistant-sources strong{color:#52666e}.organization-assistant-sources,.organization-assistant-usage{margin:8px 0 0}@media(max-width:650px){.organization-assistant-heading{align-items:flex-start;flex-direction:column}.organization-assistant-footer{align-items:stretch;flex-direction:column}.organization-assistant-footer button{width:100%}}
</style>
<script>
    (() => {
        const pending = [...document.querySelectorAll('[data-status-url]')].filter((item) => item.dataset.statusUrl);
        if (!pending.length) return;
        let finished = false;
        const check = async () => {
            const outcomes = await Promise.all(pending.map(async (item) => {
                try {
                    const response = await fetch(item.dataset.statusUrl, {headers: {'Accept': 'application/json'}, credentials: 'same-origin'});
                    if (!response.ok) return false;
                    return !['queued', 'running'].includes((await response.json()).status);
                } catch { return false; }
            }));
            if (!finished && outcomes.some(Boolean)) {
                finished = true;
                window.location.reload();
            }
        };
        window.setInterval(check, 3000);
        window.setTimeout(check, 2000);
    })();
</script>
@endsection
