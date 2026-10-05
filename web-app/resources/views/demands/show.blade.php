@extends('layouts.app')

@section('title', $demand->title.' · Plataforma Mix7')

@section('body')
<div class="shell">
    @include('layouts.navigation', ['active' => 'demands'])
    <main class="main">
        @include('layouts.topbar')
        <div class="content">
            <a class="back-link" href="{{ route('demands.index') }}">← Voltar às demandas</a>
            @include('partials.flash')
            <section class="demand-hero"><div><p class="eyebrow">{{ $demand->organization->name }} · Demanda #{{ $demand->id }}</p><h1 class="heading">{{ $demand->title }}</h1><p class="meta-line">Criada por {{ $demand->creator->name }} em {{ $demand->created_at->format('d/m/Y H:i') }}</p>@if ($demand->module_key)<p class="meta-line">Tipo: {{ $demand->moduleDisplayLabel() }} · configuração v{{ $demand->module_version }}</p>@endif</div><span class="pill pill-large">{{ $demand->status->label() }}</span></section>
            <section class="demand-properties" aria-label="Resumo da demanda">
                <div><span>Responsável</span><strong>{{ $demand->responsible?->name ?? 'A definir' }}</strong></div>
                <div><span>Briefing preparado por</span><strong>{{ $demand->briefAuthor?->name ?? $demand->creator->name }}</strong></div>
                <div><span>Solicitante</span><strong>{{ $demand->creator->name }}</strong></div>
                <div><span>Cliente</span><strong>{{ $demand->client?->name ?? 'Não vinculado' }}</strong></div>
                <div><span>Andamento</span><strong>{{ $demand->status->label() }}</strong></div>
                <div><span>Materiais</span><strong>{{ $demand->attachments->count() ? $demand->attachments->count().' arquivo(s)' : 'Aguardando materiais' }}</strong></div>
            </section>
            @error('status')<div class="notice notice-error">{{ $message }}</div>@enderror
            @error('suggested_solution')<div class="notice notice-error">{{ $message }}</div>@enderror
            @if ($canManage)
                <section class="workflow-card"><div class="section-heading"><div><h2>Etapa do trabalho</h2><p>Avance conforme o trabalho e as decisões forem registrados.</p></div></div><ol class="stage-track">@foreach (App\Enums\DemandStatus::cases() as $stage)<li class="{{ $stage === $demand->status ? 'current' : (array_search($stage, App\Enums\DemandStatus::cases(), true) < array_search($demand->status, App\Enums\DemandStatus::cases(), true) ? 'past' : '') }}"><span class="stage-dot"></span><span>{{ $stage->label() }}</span></li>@endforeach</ol>@if ($nextStatuses)<div class="stage-actions"><span>Próxima ação:</span>@foreach ($nextStatuses as $next)<form method="post" action="{{ route('demands.status', $demand) }}">@csrf @method('PATCH')<input type="hidden" name="status" value="{{ $next->value }}"><button class="secondary-button" type="submit">{{ $next->label() }}</button></form>@endforeach</div>@else<span class="notice-inline">Demanda concluída.</span>@endif</section>
            @endif
            @if (!empty($demand->module_fields_schema))
                <section class="panel module-data-panel"><div class="section-heading"><div><h2>Informações de {{ $demand->moduleDisplayLabel() }}</h2><p>Campos internos · configuração v{{ $demand->module_version }}</p></div></div><dl class="module-data-list">@foreach ($demand->module_fields_schema as $field)@php $value = $demand->module_fields_data[$field['key']] ?? null; @endphp @if ($value !== null && $value !== '')<div><dt>{{ $field['label'] }}</dt><dd>{{ $value }}</dd></div>@endif @endforeach</dl></section>
            @endif
            @if ($moduleSteps->isNotEmpty())
                <section class="panel module-steps-panel"><div class="section-heading"><div><h2>Etapas de {{ $demand->moduleDisplayLabel() }}</h2><p>Lista interna desta demanda · configuração v{{ $demand->module_version }}. O andamento principal continua no fluxo acima.</p></div></div><ol class="module-steps-list">@foreach ($moduleSteps as $step)<li class="{{ $step->completed_at ? 'is-complete' : '' }}"><div><strong>{{ $step->label }}</strong>@if ($step->completed_at)<span>Concluída por {{ $step->completer?->name ?? 'conta removida' }} · {{ $step->completed_at->format('d/m/Y H:i') }}</span>@else<span>Pendente</span>@endif</div>@if ($canManage)<form method="post" action="{{ route('demands.module-steps.update', [$demand, $step->key]) }}">@csrf @method('PATCH')<input type="hidden" name="completed" value="{{ $step->completed_at ? '0' : '1' }}"><button class="secondary-button" type="submit">{{ $step->completed_at ? 'Reabrir etapa' : 'Marcar concluída' }}</button></form>@endif</li>@endforeach</ol></section>
            @endif

            @if ($canManage)
                <section class="panel review-link-panel">
                    <div class="section-heading"><div><h2>Revisão do cliente por link</h2><p>O briefing e as tarefas internas ficam privados. As respostas de todas as versões permanecem registradas aqui.</p></div></div>
                    @if ($demand->status === App\Enums\DemandStatus::ClientApproval)
                        @if (session('review_link_url'))
                            <div class="notice notice-success"><strong>Link da versão {{ $reviewLinks->firstWhere('id', session('review_link_id'))?->version }} criado.</strong><p>{{ session('review_link_emailed') ? 'O link foi enviado ao endereço informado. Você também pode copiá-lo abaixo.' : 'Copie e envie ao cliente. Por segurança, o link completo aparece somente nesta confirmação.' }}</p><a href="{{ session('review_link_url') }}" target="_blank" rel="noopener noreferrer">{{ session('review_link_url') }}</a></div>
                        @endif
                        <form method="post" enctype="multipart/form-data" action="{{ route('demand-reviews.store', $demand) }}" class="review-link-form">@csrf
                            <label class="field">Link do material (opcional se anexar arquivo)<input type="url" name="material_url" maxlength="2048" placeholder="https://..." value="{{ old('material_url') }}"></label>
                            <label class="field">Ou anexe PDF, imagem ou vídeo (máx. 20 MB)<input type="file" name="material_file" accept=".pdf,.jpg,.jpeg,.png,.webp,.mp4,.webm,application/pdf,image/jpeg,image/png,image/webp,video/mp4,video/webm"></label>
                            <label class="field">Link válido até (horário local deste dispositivo)<input id="review-expires-local" type="datetime-local" required value="{{ old('expires_at_local') }}"></label><input id="review-expires-utc" type="hidden" name="expires_at" value="{{ old('expires_at') }}">
                            @if (in_array(config('mail.default'), ['log', 'array'], true))<p class="empty-inline">Envio por e-mail indisponível até configurar um serviço de e-mail. Você ainda poderá copiar e compartilhar o link.</p>@else<label class="field">Enviar o link por e-mail (opcional)<input type="email" name="send_to_email" maxlength="254" autocomplete="email" placeholder="cliente@empresa.com" value="{{ old('send_to_email') }}"><small>Ao preencher, o link será enviado para esse endereço quando você criar a versão. O cliente não precisa entrar no sistema.</small></label>@endif
                            <button class="primary-button" type="submit">Criar link de revisão</button>
                        </form>
                        @error('material_url')<span class="error">{{ $message }}</span>@enderror @error('material_file')<span class="error">{{ $message }}</span>@enderror @error('expires_at')<span class="error">{{ $message }}</span>@enderror @error('send_to_email')<span class="error">{{ $message }}</span>@enderror
                    @else
                        <p class="empty-inline">Para enviar uma nova versão, avance a demanda para Aprovação do cliente.</p>
                    @endif
                    @if ($reviewLinks->isNotEmpty())
                        <h3 class="review-history-title">Versões enviadas</h3>
                        <ul class="review-link-list">@foreach ($reviewLinks as $reviewLink)@php $hasFinalDecision = $reviewLink->responses->contains(fn ($response) => in_array($response->type, ['approved', 'changes_requested'], true)); @endphp<li><div><strong>Versão {{ $reviewLink->version }}</strong><span><time class="review-expiry" datetime="{{ $reviewLink->expires_at->toISOString() }}">{{ $reviewLink->expires_at->format('d/m/Y H:i') }}</time> (horário local)@if ($reviewLink->revoked_at) · Revogado @elseif ($hasFinalDecision) · Respondida @elseif ($reviewLink->expires_at->isPast()) · Expirado @else · Ativo @endif</span><span>{{ $reviewLink->material_file_name ?: $reviewLink->material_url }}</span><span>{{ $reviewLink->responses->count() }} resposta(s)</span>@if ($reviewLink->material_file_path)<details class="private-material"><summary>Visualizar arquivo desta versão</summary>@if ($reviewLink->material_mime === 'application/pdf')@include('components.pdf-preview', ['pdfUrl' => route('demand-reviews.team-material', [$demand, $reviewLink]), 'pdfName' => $reviewLink->material_file_name])@else<iframe title="Arquivo privado da versão {{ $reviewLink->version }}" src="{{ route('demand-reviews.team-material', [$demand, $reviewLink]) }}" loading="lazy"></iframe>@endif<a href="{{ route('demand-reviews.team-material', [$demand, $reviewLink]) }}" target="_blank" rel="noopener noreferrer">Abrir arquivo em outra guia</a> · <a href="{{ route('demand-reviews.team-material', [$demand, $reviewLink]) }}?download=1">Baixar arquivo</a></details>@endif @foreach ($reviewLink->responses as $response)<article class="review-feedback"><strong>{{ $response->reviewer_name }} · {{ match($response->type) {'approved' => 'Aprovou', 'changes_requested' => 'Pediu ajustes', 'annotation' => 'Anotou no material', default => 'Comentou'} }}</strong>@if ($response->anchor_type && $response->anchor_data)<span>{{ match($response->anchor_type) {'text' => 'Trecho: “'.($response->anchor_data['text'] ?? '').'”', 'area' => 'Área: centro X '.($response->anchor_data['x'] ?? '?').'%, centro Y '.($response->anchor_data['y'] ?? '?').'%' . (isset($response->anchor_data['width'], $response->anchor_data['height']) ? ', largura '.$response->anchor_data['width'].'%, altura '.$response->anchor_data['height'].'%' : ''), 'time' => 'Vídeo em '.($response->anchor_data['time'] ?? ''), 'page' => 'Página '.($response->anchor_data['page'] ?? ''), default => ''} }} · {{ $response->anchor_data['url'] ?? $reviewLink->material_url }}</span>@endif<p>{{ $response->comment ?: 'Sem comentário adicional.' }}</p><time>{{ $response->created_at->format('d/m/Y H:i') }}</time></article>@endforeach</div>@if (!$reviewLink->revoked_at && $reviewLink->expires_at->isFuture())<form method="post" action="{{ route('demand-reviews.revoke', [$demand, $reviewLink]) }}">@csrf @method('DELETE')<button class="secondary-button" type="submit">Revogar</button></form>@endif</li>@endforeach</ul>
                    @endif
                    @if ($reviewLinks->contains(fn ($link) => $link->responses->contains(fn ($response) => ! empty($response->anchor_data['path'] ?? []))))
                        <section class="drawing-responses"><h3>Rabiscos enviados com comentários</h3>@foreach ($reviewLinks as $reviewLink)@foreach ($reviewLink->responses as $response)@if (!empty($response->anchor_data['path'] ?? []))<article><strong>{{ $response->reviewer_name }} · versão {{ $reviewLink->version }}</strong><p>{{ $response->comment }}</p><x-anchor-drawing :points="$response->anchor_data['path']" /></article>@endif @endforeach @endforeach</section>
                    @endif
                </section>
                <script>document.querySelectorAll('.review-expiry').forEach((time) => { time.textContent = new Intl.DateTimeFormat('pt-BR', { dateStyle: 'short', timeStyle: 'short' }).format(new Date(time.dateTime)); });</script>
                @if ($demand->status === App\Enums\DemandStatus::ClientApproval)<script>document.querySelector('.review-link-form')?.addEventListener('submit', function () { const local = document.getElementById('review-expires-local'); document.getElementById('review-expires-utc').value = new Date(local.value).toISOString(); });</script>@endif
            @endif

            @if ($canManage)
                <section class="panel client-assignment-panel"><div class="section-heading"><div><h2>Cliente desta demanda</h2><p>O cliente vinculado verá somente o nome e a etapa atual. O material de aprovação continua sendo enviado por link.</p></div></div><form method="post" action="{{ route('demands.client.assign', $demand) }}" class="client-assignment-form">@csrf @method('PATCH')<label class="field">Conta de cliente<select name="client_user_id"><option value="">Nenhum cliente vinculado</option>@foreach ($clients as $client)<option value="{{ $client->id }}" @selected($demand->client_user_id === $client->id)>{{ $client->name }} · {{ $client->email }}</option>@endforeach</select></label><button class="secondary-button" type="submit">Salvar vínculo</button></form>@error('client_user_id')<div class="notice notice-error">{{ $message }}</div>@enderror</section>
            @endif

            <section class="panel demand-attachments" aria-labelledby="attachments-heading">
                <div class="section-heading"><div><h2 id="attachments-heading">Arquivos da equipe <span class="count-badge">{{ $demand->attachments->count() }}</span></h2><p>Materiais de trabalho privados. Só a equipe desta demanda consegue abrir estes arquivos.</p></div></div>
                @php
                    $phpUploadLimitBytes = ini_parse_quantity((string) ini_get('upload_max_filesize'));
                    $phpPostLimitBytes = ini_parse_quantity((string) ini_get('post_max_size'));
                @endphp
                <form method="post" enctype="multipart/form-data" action="{{ route('demand-attachments.store', $demand) }}" class="attachment-upload" data-attachment-upload data-server-file-limit="{{ $phpUploadLimitBytes }}" data-server-post-limit="{{ $phpPostLimitBytes }}" data-app-file-limit="{{ 20 * 1024 * 1024 }}" data-app-file-count="10">
                    @csrf
                    <label class="attachment-drop" data-attachment-drop>
                        <span class="attachment-drop-icon" aria-hidden="true">＋</span>
                        <strong>Arraste arquivos aqui ou escolha do computador</strong>
                        <span>PDF, imagens, vídeo e documentos · até 20 MB cada · máximo 10 por envio</span>
                        <input type="file" name="files[]" accept=".pdf,.jpg,.jpeg,.png,.webp,.gif,.mp4,.webm,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.csv,.zip" multiple required data-attachment-input>
                    </label>
                    <p class="field-help" data-server-upload-limits>Limite deste servidor: {{ number_format($phpUploadLimitBytes / 1024 / 1024, 0, ',', '.') }} MB por arquivo e {{ $phpPostLimitBytes > 0 ? number_format($phpPostLimitBytes / 1024 / 1024, 0, ',', '.') . ' MB por envio' : 'envio sem limite total' }}. A aplicação aceita até 20 MB por arquivo.</p>
                    <div class="attachment-upload-footer"><span class="field-help" data-attachment-names aria-live="polite">Nenhum arquivo selecionado.</span><button class="primary-button" type="submit">Anexar à demanda</button></div>
                    <p class="error" data-attachment-upload-error role="alert" hidden></p>
                    @error('files')<span class="error">{{ $message }}</span>@enderror
                    @error('files.*')<span class="error">{{ $message }}</span>@enderror
                </form>
                @if ($demand->attachments->isNotEmpty())
                    <ul class="attachment-list">
                        @foreach ($demand->attachments as $attachment)
                            @php $attachmentUrl = route('demand-attachments.show', [$demand, $attachment]); @endphp
                            <li class="attachment-item"><div class="attachment-file-icon" aria-hidden="true">{{ str($attachment->original_name)->afterLast('.')->upper()->limit(4, '') }}</div><div class="attachment-file-info"><a href="{{ $attachmentUrl }}" target="_blank" rel="noopener noreferrer">{{ $attachment->original_name }}</a><span>{{ number_format($attachment->file_size / 1024 / 1024, 2, ',', '.') }} MB · enviado por {{ $attachment->uploader->name }} em {{ $attachment->created_at->format('d/m/Y H:i') }}</span>@if ($attachment->mime_type === 'application/pdf')<details class="attachment-preview"><summary>Visualizar PDF nesta tela</summary>@include('components.pdf-preview', ['pdfUrl' => $attachmentUrl, 'pdfName' => $attachment->original_name])<p><a href="{{ $attachmentUrl }}" target="_blank" rel="noopener noreferrer">Abrir PDF em outra guia</a> · <a href="{{ $attachmentUrl }}?download=1">Baixar PDF</a></p></details>@elseif (str_starts_with($attachment->mime_type, 'image/'))<details class="attachment-preview"><summary>Ver imagem nesta tela</summary><img src="{{ $attachmentUrl }}" alt="{{ $attachment->original_name }}" loading="lazy"></details>@elseif (str_starts_with($attachment->mime_type, 'video/'))<details class="attachment-preview"><summary>Ver vídeo nesta tela</summary><video controls preload="metadata"><source src="{{ $attachmentUrl }}" type="{{ $attachment->mime_type }}">Seu navegador não reproduz este vídeo.</video></details>@endif</div><a class="secondary-button attachment-download" href="{{ $attachmentUrl }}?download=1" download>Baixar</a></li>
                        @endforeach
                    </ul>
                @else
                    <p class="empty-inline attachment-empty">Nenhum arquivo foi anexado a esta demanda.</p>
                @endif
            </section>
            @if ($demand->attachments->contains('mime_type', 'application/pdf') || $reviewLinks->contains('material_mime', 'application/pdf'))
                @vite('resources/js/pdf-preview.js')
            @endif

            <div class="detail-grid">
                <div class="detail-main">
                    <section class="panel"><div class="section-heading"><div><h2>Briefing</h2><p>O pedido e o contexto do trabalho, organizados para a equipe.</p></div></div><div class="brief-text">{{ $demand->brief }}</div></section>
                    <section class="panel demand-resources-panel"><div class="section-heading"><div><h2>Materiais e acessos</h2><p>{{ $demand->creator->name }} é responsável por disponibilizar o que for necessário.</p></div></div><dl class="demand-resource-properties"><div><dt>Onde encontrar os materiais</dt><dd>{{ $demand->materials_location ?: 'Ainda não informado. Peça os arquivos a '.$demand->creator->name.' ou anexe-os nesta demanda.' }}</dd></div><div><dt>Como obter os acessos</dt><dd>{{ $demand->access_instructions ?: 'Ainda não informado. Peça orientação a '.$demand->creator->name.'. Não compartilhe senhas nesta página.' }}</dd></div></dl><p class="field-help">Os arquivos anexados aparecem na seção “Arquivos da equipe”. Acesso a serviços deve ser concedido pelo próprio serviço, sem registrar credenciais aqui.</p></section>
                    <section class="panel suggested-solution-panel" aria-labelledby="suggested-solution-heading">
                        <div class="section-heading"><div><h2 id="suggested-solution-heading">Solução sugerida pela IA</h2><p>Proposta criada a partir do título e do briefing. Revise antes de orientar ou iniciar o trabalho.</p></div><span class="assistant-badge">A equipe decide</span></div>
                        @if ($demand->suggested_solution)
                            <div class="suggested-solution-text">{{ $demand->suggested_solution }}</div>
                            @if ($suggestedSolutionEvent)<p class="field-help">Gerada por {{ $suggestedSolutionEvent->actor?->name ?? 'usuário removido' }} · {{ $suggestedSolutionEvent->created_at->format('d/m/Y H:i') }}</p>@endif
                        @else
                            <p class="empty-inline">Ainda não há uma proposta para esta demanda.</p>
                        @endif
                        @if (auth()->user()->role !== App\Enums\UserRole::Client)
                            @if ($aiConfigured)
                                <form method="post" action="{{ route('ai-solution.generate', $demand) }}">@csrf<button class="secondary-button" type="submit">{{ $demand->suggested_solution ? 'Gerar nova sugestão' : 'Propor solução com IA' }}</button></form>
                                <p class="field-help">A IA considera o título, o briefing e as orientações de material e acesso registradas. Ela não inventa dados ausentes nem cria tarefas. Revise a sugestão antes de usá-la.</p>
                            @else
                                <p class="field-help">Configure o assistente da agência para gerar uma proposta. A demanda e seu briefing continuam disponíveis normalmente.</p>
                            @endif
                        @endif
                    </section>
                    @if (auth()->user()->role !== App\Enums\UserRole::Client)
                        @php $assistantConfigured = $aiConfigured; @endphp
                        <section class="panel ai-assistant-panel" aria-labelledby="ai-assistant-heading">
                            <div class="section-heading"><div><h2 id="ai-assistant-heading">Assistente da demanda</h2><p>{{ $canManage ? 'Escolha ajuda geral ou organização do feedback de aprovação desta demanda.' : 'Peça ajuda sobre o briefing e suas tarefas atribuídas nesta demanda.' }}</p></div><span class="assistant-badge">Somente leitura</span></div>
                            <p class="assistant-privacy">Ao enviar uma pergunta, o texto e os trechos de contexto consultados vão ao provedor de IA configurado. Para profissionais, o contexto se limita ao briefing visível e às próprias tarefas; dados de cliente, feedback de aprovação e tarefas de colegas não são enviados. Não inclua senhas nem dados pessoais desnecessários. O assistente não cria tarefas nem altera etapas.</p>
                            @if ($assistantConfigured)
                                <form method="post" action="{{ route('ai-agent.ask', $demand) }}" class="assistant-form">@csrf
                                    <label class="field" for="assistant-specialist">Área de ajuda</label>
                                    <select id="assistant-specialist" name="specialist">
                                        <option value="demand_assistant" @selected(old('specialist', 'demand_assistant') === 'demand_assistant')>Ajuda geral da demanda</option>
                                        @if ($canManage)<option value="approval_assistant" @selected(old('specialist') === 'approval_assistant')>Organizar feedback de aprovação</option>@endif
                                    </select>
                                    <label class="field" for="assistant-question">O que você precisa entender?</label>
                                    <textarea id="assistant-question" name="question" rows="3" minlength="3" maxlength="3000" required placeholder="Ex.: Quais tarefas ainda faltam e quais referências se aplicam?">{{ old('question') }}</textarea>
                                    <div class="assistant-form-footer"><span class="field-help">Até 3 consultas por minuto. A execução pode levar alguns segundos.</span><button class="primary-button" type="submit">Perguntar ao assistente</button></div>
                                </form>
                            @else
                                    <div class="notice notice-info">Assistente ainda não configurado. Nenhuma pergunta será enviada enquanto faltar um modelo e a configuração do provedor. É possível usar um servidor local compatível com a API OpenAI para evitar cobrança por token, desde que ele esteja acessível ao Laravel e permaneça ligado. O AI Gateway é outra opção, com custos e créditos limitados.</div>
                            @endif
                            @error('assistant')<div class="notice notice-error">{{ $message }}</div>@enderror
                            @error('question')<div class="notice notice-error">{{ $message }}</div>@enderror
                            @if ($aiAgentRuns->isNotEmpty())
                                <h3 class="assistant-history-heading">Perguntas recentes</h3>
                                <div class="assistant-history" data-assistant-poll>
                                    @foreach ($aiAgentRuns as $run)
                                        <article class="assistant-run" data-assistant-run data-status-url="{{ in_array($run->status, ['queued', 'running'], true) ? route('ai-agent.status', [$demand, $run]) : '' }}">
                                            <div class="assistant-run-meta"><strong>{{ $run->requester->name }} · {{ $run->agent === 'approval_assistant' ? 'Especialista de aprovação' : 'Ajuda geral' }}</strong><time datetime="{{ $run->created_at->toISOString() }}">{{ $run->created_at->format('d/m/Y H:i') }}</time><span class="assistant-state assistant-state-{{ $run->status }}" data-assistant-state>{{ match ($run->status) {'queued' => 'Na fila', 'running' => 'Consultando', 'completed' => 'Concluído', 'failed' => 'Falhou', default => $run->status} }}</span></div>
                                            @if ($run->answer)
                                                @if ($run->agent === 'approval_assistant')
                                                    @php
                                                        $approvalProposal = json_decode($run->answer, true);
                                                    @endphp
                                                    @if (is_array($approvalProposal))
                                                        <div class="approval-ai-proposal"><p class="approval-ai-summary">{{ $approvalProposal['summary'] ?? 'Sugestões organizadas a partir dos comentários do cliente.' }}</p>
                                                            @forelse (($approvalProposal['adjustments'] ?? []) as $adjustment)
                                                                <article class="approval-ai-item"><div class="approval-ai-meta"><strong>{{ $adjustment['task_title'] ?: 'Comentário para análise' }}</strong><span>Versão {{ $adjustment['version'] }} · comentário #{{ $adjustment['response_id'] }}</span><span class="approval-ai-kind">{{ match ($adjustment['classification']) {'adjustment' => 'Pedido de ajuste', 'question' => 'Dúvida', 'approval' => 'Aprovação', 'conflict' => 'Possível conflito', default => 'Comentário'} }}</span></div>
                                                                    <blockquote><strong>Comentário original do cliente</strong><br>{{ $adjustment['original_comment'] }}</blockquote>
                                                                    @if ($adjustment['anchor_type'] || ($adjustment['anchor'] ?? []))<p class="approval-ai-anchor"><strong>Marcação do cliente:</strong> {{ match ($adjustment['anchor_type']) {'text' => 'trecho selecionado: “'.($adjustment['anchor']['text'] ?? '').'”', 'area' => 'área do material, centro X '.($adjustment['anchor']['x'] ?? '?').'%, centro Y '.($adjustment['anchor']['y'] ?? '?').'%', 'time' => 'vídeo no instante '.($adjustment['anchor']['time'] ?? '?'), 'page' => 'página '.($adjustment['anchor']['page'] ?? '?'), default => 'posição registrada no material'} }}</p>@if (!empty($adjustment['anchor']['path'] ?? []))<x-anchor-drawing :points="$adjustment['anchor']['path']" />@endif @endif
                                                                    @if ($adjustment['instruction'])<p><strong>Instrução sugerida:</strong> {{ $adjustment['instruction'] }}</p>@endif
                                                                    @if ($adjustment['expected_result'])<p><strong>Resultado esperado:</strong> {{ $adjustment['expected_result'] }}</p>@endif
                                                                    @if (!empty($adjustment['acceptance_criteria']))<p><strong>Conferir:</strong> {{ implode(' · ', $adjustment['acceptance_criteria']) }}</p>@endif
                                                                    @if ($canManage && $adjustment['task_title'])<button type="button" class="secondary-button approval-ai-draft-task" data-task-title="{{ $adjustment['task_title'] }}">Usar título no rascunho de tarefa</button>@endif
                                                                </article>
                                                            @empty<p class="assistant-answer">{{ $approvalProposal['summary'] ?? 'Não há ajustes identificados nos comentários disponíveis.' }}</p>@endforelse
                                                        </div>
                                                    @else<p class="assistant-error">A proposta estruturada não pôde ser exibida. O comentário original continua preservado.</p>@endif
                                                @else<p class="assistant-answer">{{ $run->answer }}</p>@endif
                                            @elseif ($run->status === 'failed')<p class="assistant-error">{{ $run->error_message ?: 'Não foi possível concluir a consulta.' }}</p>@else<p class="assistant-pending">O assistente está preparando a resposta…</p>@endif
                                            @if (in_array($run->status, ['queued', 'running'], true))<p class="assistant-pending" data-assistant-poll-hint hidden>A atualização automática pausou após 10 minutos. Atualize a página para consultar o estado mais recente.</p>@endif
                                            @if ($run->tool_trace)<p class="assistant-sources"><strong>Consultas registradas:</strong> @foreach ($run->tool_trace as $receipt){{ match ($receipt['tool'] ?? '') {'read_demand_context' => 'demanda', 'search_knowledge' => 'conhecimento interno', 'list_client_feedback' => 'feedback do cliente', default => 'consulta recusada'} }}@if (!$loop->last), @endif @endforeach</p>@endif
                                            @if ($run->input_tokens || $run->output_tokens || $run->provider_cost !== null)<p class="assistant-usage">Tokens: {{ $run->input_tokens ?? '—' }} entrada · {{ $run->output_tokens ?? '—' }} saída · Custo reportado pelo provedor: {{ $run->provider_cost !== null ? '$'.number_format((float) $run->provider_cost, 6, '.', ',').' USD' : 'não informado' }}</p>@elseif ($run->status === 'completed')<p class="assistant-usage">O provedor não informou o custo desta execução.</p>@endif
                                        </article>
                                    @endforeach
                                </div>
                            @endif
                </section>
            @endif
            @if (in_array($demand->status, [App\Enums\DemandStatus::Delivery, App\Enums\DemandStatus::Completed], true))
                <section class="panel delivery-evidence-panel">
                    <div class="section-heading"><div><h2>Registro de entrega, agendamento ou publicação</h2><p>Registre o que aconteceu depois da aprovação e guarde uma referência. Este registro não publica por você nem altera a etapa.</p></div></div>
                    @if ($canManage)
                        <form method="post" action="{{ route('demands.delivery-evidence.store', $demand) }}" class="delivery-evidence-form">@csrf
                        <label class="field" for="delivery-outcome">O que aconteceu?</label>
                        <select id="delivery-outcome" name="outcome" required>
                            <option value="">Escolha uma opção</option>
                            <option value="delivered" @selected(old('outcome') === 'delivered')>Entregue ao cliente</option>
                            <option value="scheduled" @selected(old('outcome') === 'scheduled')>Agendado</option>
                            <option value="published" @selected(old('outcome') === 'published')>Publicado</option>
                        </select>
                        <label class="field" for="delivery-evidence-url">Link de referência (opcional se preencher a observação)<input id="delivery-evidence-url" type="url" name="evidence_url" maxlength="2048" value="{{ old('evidence_url') }}" placeholder="https://..."></label>
                        <label class="field" for="delivery-evidence-details">Observação (opcional se preencher o link)<textarea id="delivery-evidence-details" name="details" rows="3" maxlength="3000">{{ old('details') }}</textarea></label>
                        <label class="field" for="delivery-occurred-at">Quando ocorreu (opcional)<input id="delivery-occurred-at" type="datetime-local" name="occurred_at" value="{{ old('occurred_at') }}"></label>
                            <button class="primary-button" type="submit">Registrar evidência</button>
                        </form>
                        @foreach (['outcome', 'evidence_url', 'details', 'occurred_at'] as $field)
                            @error($field)<span class="error">{{ $message }}</span>@enderror
                        @endforeach
                    @endif
                    @if ($deliveryEvidences->isNotEmpty())
                        <h3 class="review-history-title">Histórico de entregas</h3>
                        <ul class="review-link-list">
                            @foreach ($deliveryEvidences as $evidence)
                                <li><div><strong>{{ match ($evidence->outcome) {'scheduled' => 'Agendado', 'published' => 'Publicado', default => 'Entregue ao cliente'} }}</strong><span>Registrado por {{ $evidence->recorder->name }} em {{ $evidence->created_at->format('d/m/Y H:i') }}@if ($evidence->occurred_at) · Ocorrido em {{ $evidence->occurred_at->format('d/m/Y H:i') }}@endif</span>@if ($evidence->evidence_url)<a href="{{ $evidence->evidence_url }}" target="_blank" rel="noopener noreferrer">Abrir referência</a>@endif @if ($evidence->details)<p>{{ $evidence->details }}</p>@endif</div></li>
                            @endforeach
                        </ul>
                    @else
                        <p class="empty-inline">Ainda não há registro de entrega, agendamento ou publicação.</p>
                    @endif
                </section>
            @endif
                    @if ($canManage)
                        <section class="panel ai-planning-panel">
                            <div class="section-heading"><div><h2>Planejamento com IA</h2><p>A IA prepara uma proposta. Nenhuma tarefa é criada sem sua revisão e aprovação.</p></div></div>
                            @if ($demand->status === App\Enums\DemandStatus::Planning)
                                @if ($aiConfigured)
                                    @php $feedbackCount = $reviewLinks->sum(fn ($link) => $link->responses->count()); @endphp
                                    <form method="post" action="{{ route('ai-planning.propose', $demand) }}">@csrf
                                        <button class="secondary-button" type="submit">Gerar proposta a partir do briefing</button>
                                        <span class="field-help">O título, briefing e nomes das tarefas existentes serão enviados ao provedor de IA configurado.</span>
                                        @if ($feedbackCount > 0)
                                            <label class="ai-feedback-option"><input type="checkbox" name="include_client_feedback" value="1" @checked(old('include_client_feedback'))><span>Incluir até 20 comentários e anotações do cliente, identificados por versão ({{ $feedbackCount }} disponíveis)</span></label>
                                            <span class="field-help">Os comentários selecionados também serão enviados ao provedor. Nomes e links de revisão são omitidos.</span>
                                        @endif
                                        <label class="ai-feedback-option"><input type="checkbox" name="include_assignment_candidates" value="1" @checked(old('include_assignment_candidates'))><span>Permitir que a IA sugira uma pessoa da equipe para cada tarefa</span></label>
                                        <span class="field-help">Com esta opção, o provedor recebe especialidades declaradas e referências aleatórias, sem nomes, e-mails ou IDs internos. Especialidades também são dados internos: use somente após a política de envio da Mix7 ser aprovada. Você poderá trocar cada indicação antes de aplicar.</span>
                                        <label class="ai-feedback-option"><input type="checkbox" name="include_team_capacity" value="1" @checked(old('include_team_capacity')) data-capacity-toggle><span>Considerar a capacidade semanal registrada pela gestão</span></label>
                                        <label class="field ai-capacity-week">Semana de referência<input type="week" name="capacity_week" value="{{ old('capacity_week', now()->format('o-\\WW')) }}" data-capacity-week></label>
                                        <span class="field-help">Com a opção marcada, o provedor recebe apenas totais da semana, sem nomes ou títulos de tarefas. A prévia é preliminar; a gestão ainda escolhe cada responsável. O envio continua sujeito à política de dados da Mix7.</span>
                                        <script>document.querySelector('[data-capacity-toggle]')?.addEventListener('change', function () { document.querySelector('[data-capacity-week]').disabled = !this.checked; }); document.querySelector('[data-capacity-week]').disabled = !document.querySelector('[data-capacity-toggle]')?.checked;</script>
                                    </form>
                                @else
                                    <div class="notice notice-info">Agente opcional indisponível: configure um modelo e um provedor compatível. Um modelo local pode funcionar sem chave e sem cobrança por token; a fila ainda depende de um worker Laravel ativo.</div>
                                @endif
                            @else
                                <p class="empty-inline">Disponível quando a demanda estiver na etapa Planejamento.</p>
                            @endif
                            @error('ai')<div class="notice notice-error">{{ $message }}</div>@enderror
                            @foreach ($aiPlanningRuns as $run)
                                <article class="ai-proposal">
                                    <div class="ai-proposal-meta"><strong>Proposta de {{ $run->requester->name }}</strong><span>{{ $run->created_at->format('d/m/Y H:i') }} · {{ $run->model }}</span><span>Estado: {{ match ($run->status) {'pending' => 'Aguardando revisão', 'approved' => 'Aplicada', 'discarded' => 'Descartada', default => $run->status} }}</span>@if (!empty($run->proposal['_source']['client_feedback_included']))<span>Incluiu {{ count($run->proposal['_source']['feedback_response_ids'] ?? []) }} feedback(s) do cliente</span>@endif</div>
                                    @if (!empty($run->proposal['_source']['team_capacity_included']))
                                        @php $capacitySource = $run->proposal['_source']['team_capacity']; @endphp
                                        <details class="ai-capacity-source"><summary>Ver totais de capacidade enviados · semana {{ $capacitySource['week'] }}</summary><p>{{ $capacitySource['professionals_with_recorded_capacity'] }} de {{ $capacitySource['active_professionals'] }} profissionais com capacidade informada; {{ $capacitySource['professionals_without_recorded_capacity'] }} sem registro. Disponibilidade registrada: {{ number_format($capacitySource['recorded_available_minutes'] / 60, 2, ',', '.') }} h após {{ number_format($capacitySource['recorded_absence_minutes'] / 60, 2, ',', '.') }} h de ausências. Tarefas abertas com prazo nesta semana: {{ $capacitySource['dated_open_tasks_due_this_week'] }}, total estimado {{ number_format($capacitySource['open_estimate_minutes_due_this_week'] / 60, 2, ',', '.') }} h; {{ $capacitySource['dated_tasks_missing_estimate'] }} sem estimativa e {{ $capacitySource['open_tasks_without_due_date'] }} sem prazo. Nenhum nome ou título foi enviado.</p></details>
                                        @if (!empty($run->proposal['capacity_observation']))<p class="ai-capacity-observation"><strong>Leitura preliminar da IA:</strong> {{ $run->proposal['capacity_observation'] }} Revise os totais antes de decidir.</p>@endif
                                    @endif
                                    @if (!empty($run->proposal['_source']['assignment_candidates_included']))
                                        <p class="field-help">A IA recebeu especialidades com referências anônimas, sem nomes, e-mails ou IDs internos. Confira ou troque cada pessoa antes de aplicar.</p>
                                    @elseif (!empty($run->proposal['_source']['assignment_suggestions_requested']))
                                        <p class="notice notice-info">Nenhuma pessoa profissional ativa tem especialidades cadastradas para esta sugestão. Escolha os responsáveis manualmente.</p>
                                    @endif
                                    @if ($run->status === 'pending' && $demand->status === App\Enums\DemandStatus::Planning)
                                        <form method="post" action="{{ route('ai-planning.approve', [$demand, $run]) }}" class="ai-review-form">@csrf
                                            <label class="field">Resumo curto para o quadro (revise antes de aprovar)<textarea name="summary" maxlength="280" rows="3">{{ $run->proposal['summary'] ?? '' }}</textarea></label>
                                            <p class="field-help">Este resumo só aparecerá para a equipe depois que você revisar e aplicar a proposta. Apague o texto se preferir manter apenas o trecho do briefing.</p>
                                            @foreach (($run->proposal['questions'] ?? []) as $questionIndex => $question)<label class="field">Pergunta para completar o briefing<input name="questions[{{ $questionIndex }}]" value="{{ $question }}" maxlength="500" required></label>@endforeach
                                            @foreach (($run->proposal['tasks'] ?? []) as $index => $suggestedTask)
                                                @php
                                                    $matchedProfessionals = $professionals->filter(fn ($person) => $person->matchesSpecialty((string) $suggestedTask['responsibility_profile']))->values();
                                                    $candidateRef = $suggestedTask['suggested_assignee_ref'] ?? null;
                                                    $candidateSuggestion = $candidateRef ? ($run->proposal['_source']['assignment_candidates'][$candidateRef] ?? null) : null;
                                                    $aiSuggestedProfessional = $candidateSuggestion ? $professionals->firstWhere('id', $candidateSuggestion['user_id']) : null;
                                                    $recommendedProfessionalId = $aiSuggestedProfessional?->id ?? ($matchedProfessionals->count() === 1 ? $matchedProfessionals->first()->id : null);
                                                    $recommendationLabel = $aiSuggestedProfessional ? 'Responsável sugerido pela IA — confirme' : ($recommendedProfessionalId ? 'Responsável sugerido — confirme' : 'Responsável');
                                                @endphp
                                                <div class="ai-task-row" data-ai-assignee-row>
                                                    <label class="field">Aplicar esta tarefa?<select name="tasks[{{ $index }}][include]" data-ai-include><option value="1" selected>Sim, incluir</option><option value="0">Não, remover</option></select></label>
                                                    <fieldset class="ai-task-fields">
                                                        <label class="field">Tarefa sugerida<input name="tasks[{{ $index }}][title]" value="{{ $suggestedTask['title'] }}" maxlength="180" required></label>
                                                        <label class="field">Perfil sugerido<input name="tasks[{{ $index }}][responsibility_profile]" value="{{ $suggestedTask['responsibility_profile'] }}" maxlength="120" required data-ai-responsibility-profile></label>
                                                        <label class="field">Estimativa (minutos)<input type="number" name="tasks[{{ $index }}][estimate_minutes]" value="{{ $suggestedTask['estimate_minutes'] }}" min="1" max="100000" required></label>
                                                        <label class="field"><span data-ai-assignee-label>{{ $recommendationLabel }}</span>
                                                            <select name="tasks[{{ $index }}][assignee_id]" required data-ai-assignee-select data-ai-suggested="{{ $aiSuggestedProfessional ? '1' : '0' }}">
                                                                <option value="" data-professional-name="">Escolha uma pessoa</option>
                                                                @foreach ($professionals as $professional)
                                                                    <option value="{{ $professional->id }}" data-professional-name="{{ $professional->name }}" data-professional-specialties="{{ json_encode($professional->specialties ?? [], JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_HEX_TAG) }}" @selected($recommendedProfessionalId === $professional->id)>{{ $professional->name }}@if ($professional->matchesSpecialty((string) $suggestedTask['responsibility_profile'])) · especialidade compatível @endif</option>
                                                                @endforeach
                                                            </select>
                                                            <span class="field-help" data-ai-assignee-help>@if ($aiSuggestedProfessional){{ $suggestedTask['assignment_rationale'] ?: 'A IA sugeriu esta pessoa com base nas especialidades declaradas.' }} Confira ou escolha outra pessoa antes de aplicar.@elseif ($matchedProfessionals->count() > 1)Mais de uma pessoa informou esta especialidade; escolha quem executará.@elseif ($recommendedProfessionalId)Sugestão local pela correspondência exata da especialidade cadastrada. Confirme antes de aplicar.@else Nenhuma especialidade cadastrada corresponde exatamente ao perfil. Escolha manualmente.@endif</span>
                                                        </label>
                                                        <p>{{ $suggestedTask['rationale'] }}@if ($suggestedTask['depends_on'])<br><strong>Depende de:</strong> @foreach ($suggestedTask['depends_on'] as $dependencyIndex){{ $run->proposal['tasks'][$dependencyIndex]['title'] ?? 'Tarefa anterior' }}@if (!$loop->last), @endif @endforeach @else<br>Sem dependências anteriores.@endif @if (!empty($suggestedTask['feedback_refs']))<br><strong>Feedback ligado a esta tarefa:</strong> @foreach ($suggestedTask['feedback_refs'] as $feedbackId)@if (isset($feedbackById[$feedbackId]))versão {{ $feedbackById[$feedbackId]['version'] }} · “{{ mb_substr((string) $feedbackById[$feedbackId]['comment'], 0, 120) }}”@else resposta #{{ $feedbackId }}@endif @if (!$loop->last); @endif @endforeach @endif</p>
                                                    </fieldset>
                                                </div>
                                            @endforeach
                                            <p class="field-help">Ao remover uma tarefa, as tarefas seguintes deixam de depender dela.</p>
                                            @if ($professionals->isEmpty())<p class="empty-inline">Cadastre profissionais antes de aplicar a proposta.</p>@else<div class="form-actions"><button class="primary-button" type="submit">Revisar e criar tarefas selecionadas</button></div>@endif
                                        </form>
                                        <form method="post" action="{{ route('ai-planning.discard', [$demand, $run]) }}" class="ai-discard-form">@csrf @method('DELETE')<button class="secondary-button" type="submit">Descartar proposta inteira</button></form>
                                    @elseif ($run->reviewer)
                                        <p><strong>Resumo aprovado:</strong> {{ $run->reviewed_tasks['summary'] ?? '' }}</p>
                                        <p class="field-help">Revisada por {{ $run->reviewer->name }} em {{ $run->reviewed_at?->format('d/m/Y H:i') }}. {{ collect($run->reviewed_tasks['tasks'] ?? [])->filter(fn ($task) => ($task['include'] ?? true) === true)->count() }} tarefa(s) aplicada(s).</p>
                                        @if (!empty($run->reviewed_tasks['questions']))<div class="ai-follow-up"><strong>Perguntas registradas para completar o briefing</strong><ul>@foreach ($run->reviewed_tasks['questions'] as $question)<li>{{ $question }}</li>@endforeach</ul></div>@endif
                                    @endif
                                </article>
                            @endforeach
                        </section>
                    @endif
                    <section class="panel schedule-panel"><div class="section-heading"><div><h2>Cronograma</h2><p>Datas planejadas pelas pessoas. As barras mostram o período informado; elas não calculam disponibilidade ou carga horária.</p></div></div>
                        @if ($scheduledTasks->isEmpty())
                            <p class="empty-inline">Ainda não há datas planejadas para as tarefas visíveis nesta demanda.</p>
                        @else
                            @php
                                $scheduleDates = $scheduledTasks->flatMap(fn ($task) => [$task->planned_start_on, $task->planned_due_on])->filter()->map(fn ($date) => $date->startOfDay()->timestamp);
                                $scheduleMin = $scheduleDates->min();
                                $scheduleMax = $scheduleDates->max();
                                $scheduleSpan = max(1, (int) ceil(($scheduleMax - $scheduleMin) / 86400) + 1);
                            @endphp
                            <div class="schedule-legend"><span><i class="schedule-period-swatch"></i> Período planejado</span><span><i class="schedule-milestone-swatch"></i> Marco com uma data</span></div>
                            <div class="schedule-scroll" role="region" aria-label="Cronograma das tarefas" tabindex="0">
                                <div class="schedule-chart">
                                    <div class="schedule-axis"><strong>Tarefa</strong><strong>Datas</strong><div class="schedule-axis-track"><time>{{ \Carbon\CarbonImmutable::createFromTimestamp($scheduleMin)->format('d/m/Y') }}</time><time>{{ \Carbon\CarbonImmutable::createFromTimestamp($scheduleMax)->format('d/m/Y') }}</time></div></div>
                                    @foreach ($scheduledTasks as $scheduledTask)
                                        @php
                                            $startTimestamp = $scheduledTask->planned_start_on?->startOfDay()->timestamp ?? $scheduledTask->planned_due_on->startOfDay()->timestamp;
                                            $dueTimestamp = $scheduledTask->planned_due_on?->startOfDay()->timestamp ?? $startTimestamp;
                                            $left = max(0, (($startTimestamp - $scheduleMin) / 86400) / $scheduleSpan * 100);
                                            $width = max(1.2, (($dueTimestamp - $startTimestamp) / 86400 + 1) / $scheduleSpan * 100);
                                        @endphp
                                        <div class="schedule-row">
                                            <div class="schedule-task"><strong>{{ $scheduledTask->title }}</strong><span>{{ $scheduledTask->assignee->name }}</span></div>
                                            <div class="schedule-dates">{{ $scheduledTask->planned_start_on?->format('d/m/Y') ?? '—' }} → {{ $scheduledTask->planned_due_on?->format('d/m/Y') ?? '—' }}</div>
                                            <div class="schedule-track"><span class="schedule-bar {{ $scheduledTask->planned_start_on && $scheduledTask->planned_due_on ? '' : 'schedule-milestone' }}" style="left:{{ $left }}%;width:{{ $width }}%" title="{{ $scheduledTask->title }}"><span class="sr-only">{{ $scheduledTask->planned_start_on && $scheduledTask->planned_due_on ? 'Período' : 'Marco' }}</span></span></div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                        @if ($unscheduledTaskCount > 0)<p class="schedule-unscheduled">{{ $unscheduledTaskCount }} tarefa(s) sem datas planejadas.</p>@endif
                    </section>
                    <section class="panel"><div class="section-heading"><div><h2>Tarefas <span class="count-badge">{{ $tasks->count() }}</span></h2><p>Quem faz cada parte e em que ponto está.</p></div></div>
                        @forelse ($tasks as $task)
                            @php($workedSeconds = $task->timeEntries->sum(fn ($entry) => ($entry->ended_at ?? now())->getTimestamp() - $entry->started_at->getTimestamp()))
                            <article class="task-card"><div class="task-card-content"><div class="task-card-heading"><h3>{{ $task->title }}</h3><span class="pill pill-task pill-{{ $task->status->value }}">{{ $task->status->label() }}</span></div>@if ($task->description)<p>{{ $task->description }}</p>@endif<p>Atribuída a <strong>{{ $task->assignee->name }}</strong>@if ($canManage && !$task->assignee->is_active) · <span class="inactive-assignee">Acesso desativado</span>@endif · Atribuída por {{ $task->latestAssignmentEvent?->actor?->name ?? $task->creator->name }}@if ($task->estimate_minutes) · {{ $task->estimate_minutes }} min estimados @endif @can('trackTime', $task)· <span data-total-seconds="{{ $workedSeconds }}">{{ gmdate('H:i:s', $workedSeconds) }} registrados</span>@endcan</p>@if ($task->dependencies->isNotEmpty())<p class="task-dependency"><strong>Começa depois de:</strong> @foreach ($task->dependencies as $dependency){{ $dependency->title }} ({{ $dependency->status->label() }})@if (!$loop->last), @endif @endforeach</p>@endif
                                    @can('trackTime', $task)<div class="timer-actions">@if ($activeTimeTaskId === $task->id)<form method="post" action="{{ route('demand-tasks.timer.pause', $task) }}">@csrf<button type="submit" class="secondary-button">Pausar cronômetro</button></form>@else<form method="post" action="{{ route('demand-tasks.timer.start', $task) }}">@csrf<button type="submit" class="secondary-button" {{ $activeTimeTaskId ? 'disabled' : '' }}>Iniciar cronômetro</button></form>@endif</div>@endcan</div>@can('updateStatus', $task)<form method="post" action="{{ route('demand-tasks.status', $task) }}" class="task-status-form">@csrf @method('PATCH')<label class="sr-only" for="task-status-{{ $task->id }}">Atualizar status de {{ $task->title }}</label><select id="task-status-{{ $task->id }}" name="status"><option value="{{ $task->status->value }}">{{ $task->status->label() }}</option>@foreach ($task->status->next() as $status)<option value="{{ $status->value }}">{{ $status->label() }}</option>@endforeach</select><button type="submit" class="icon-button" aria-label="Salvar status da tarefa">Salvar</button></form>@endcan @can('updateAssignee', $task)@if ($task->status !== \App\Enums\TaskStatus::Completed)<details class="task-reassign"><summary>{{ $task->assignee->is_active ? 'Reatribuir tarefa' : 'Transferir tarefa' }}</summary>@if ($professionals->isEmpty())<p class="field-help">Cadastre ou reative uma pessoa profissional antes de transferir esta tarefa.</p>@else<form method="post" action="{{ route('demand-tasks.assignee', $task) }}">@csrf @method('PATCH')<label class="sr-only" for="task-assignee-{{ $task->id }}">Novo responsável para {{ $task->title }}</label><select id="task-assignee-{{ $task->id }}" name="assignee_id" required><option value="">Escolha uma pessoa ativa</option>@foreach ($professionals as $professional)<option value="{{ $professional->id }}" @selected($task->assigned_to === $professional->id)>{{ $professional->name }}</option>@endforeach</select><button type="submit" class="secondary-button">Salvar responsável</button></form><p class="field-help">A transferência registra quem fez a mudança; se houver cronômetro ativo, ele será encerrado e a tarefa pausada.</p>@endif</details>@endif @endcan</article>
                        @empty
                            <p class="empty-inline">Você ainda não tem tarefas atribuídas nesta demanda.</p>
                        @endforelse
                    </section>
                    @error('timer')<div class="notice notice-error">{{ $message }}</div>@enderror
                    @if ($canManage)
                        <section class="panel"><div class="section-heading"><div><h2>Adicionar tarefa</h2><p>Inclua outra parte do trabalho e atribua à equipe.</p></div></div>@if ($professionals->isEmpty())<p class="empty-inline">Cadastre profissionais ativos para poder atribuir tarefas.</p>@else<form method="post" action="{{ route('demand-tasks.store', $demand) }}" class="inline-task-form">@csrf<label class="field">Nome da tarefa<input name="title" maxlength="180" required placeholder="Ex.: Preparar versão para revisão"></label><label class="field">Responsável<select name="assignee_id" required><option value="">Selecione uma pessoa</option>@foreach ($professionals as $professional)<option value="{{ $professional->id }}">{{ $professional->name }}</option>@endforeach</select></label><label class="field estimate-field">Estimativa (min)<input name="estimate_minutes" type="number" min="1" max="100000" placeholder="Opcional"></label><button class="primary-button" type="submit">Adicionar</button></form>@endif @error('title')<span class="error">{{ $message }}</span>@enderror @error('assignee_id')<span class="error">{{ $message }}</span>@enderror</section>
                    @endif
                </div>
                <aside class="detail-side"><details class="panel history-panel"><summary class="history-toggle"><span><strong>Histórico</strong><small>{{ $events->count() }} {{ \Illuminate\Support\Str::plural('registro', $events->count()) }}</small></span><span class="history-toggle-icon" aria-hidden="true">+</span></summary>@if ($events->isNotEmpty())<ol class="history-list">@foreach ($events as $event)<li><span class="history-dot"></span><div><p>{{ $event->summary }}</p><time datetime="{{ $event->created_at->toISOString() }}">{{ $event->created_at->format('d/m/Y H:i') }}</time></div></li>@endforeach</ol>@else<p class="empty-inline">Nenhuma atividade registrada.</p>@endif</details></aside>
            </div>
        </div>
    </main>
</div>
<style>
.demand-properties{display:grid;grid-template-columns:repeat(6,minmax(0,1fr));gap:1px;margin:0 0 16px;overflow:hidden;border:1px solid #dce7e9;border-radius:14px;background:#dce7e9}.demand-properties>div{display:grid;align-content:start;gap:6px;min-height:66px;padding:12px 14px;background:#fff}.demand-properties span,.demand-resource-properties dt{color:#75868c;font-size:10px;line-height:1.35}.demand-properties strong{color:#204b61;font-size:12px;line-height:1.4;overflow-wrap:anywhere}.demand-resources-panel{border-left:3px solid #8ecde2}.demand-resource-properties{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px;margin:0}.demand-resource-properties>div{min-width:0;padding:12px;border:1px solid #e3edef;border-radius:10px;background:#f8fcfd}.demand-resource-properties dd{margin:6px 0 0;color:#344d56;font-size:12px;line-height:1.6;white-space:pre-wrap;overflow-wrap:anywhere}.demand-resources-panel .field-help{margin-top:12px}
.detail-main,.detail-side{min-width:0}.history-toggle{display:flex;align-items:center;justify-content:space-between;gap:12px;cursor:pointer;list-style:none}.history-toggle::-webkit-details-marker{display:none}.history-toggle:focus-visible{outline:3px solid #8ecde2;outline-offset:3px;border-radius:6px}.history-toggle>span:first-child{display:grid;gap:3px}.history-toggle strong{color:#204b61;font-size:14px}.history-toggle small{color:#718087;font-size:11px;font-weight:400}.history-toggle-icon{display:grid;place-items:center;width:26px;height:26px;border-radius:50%;background:#eaf6fa;color:#326c82;font-size:18px;line-height:1}.history-panel[open] .history-toggle-icon{font-size:0}.history-panel[open] .history-toggle-icon:after{content:'−';font-size:18px}.history-panel .history-list{margin-top:17px}
.suggested-solution-text{padding:14px;border:1px solid #dcebed;border-radius:11px;background:#f7fbfc;color:#344d56;font-size:13px;line-height:1.7;white-space:pre-wrap}.suggested-solution-panel form{margin-top:12px}
.task-reassign{margin-top:10px}.task-reassign summary{width:max-content;max-width:100%;cursor:pointer;color:#326c82;font-size:11px;font-weight:700}.task-reassign form{display:flex;flex-wrap:wrap;align-items:end;gap:8px;margin-top:8px}.task-reassign select{max-width:100%;min-height:36px;border:1px solid #d6e0e1;border-radius:9px;padding:7px 9px;background:#fff;color:#344d56}.task-reassign .secondary-button{min-height:36px;padding:7px 10px;font-size:10px}.task-reassign .field-help{margin:7px 0;color:#718087;font-size:10px;line-height:1.45}.inactive-assignee{color:#a05a30;font-size:11px}
.schedule-legend{display:flex;flex-wrap:wrap;gap:14px;margin-bottom:12px;color:#718087;font-size:11px}.schedule-legend span{display:inline-flex;align-items:center;gap:6px}.schedule-legend i{display:inline-block;width:15px;height:8px;border-radius:99px;background:#8ecde2}.schedule-legend .schedule-milestone-swatch{width:10px;height:10px;background:#204b61;transform:rotate(45deg);border-radius:2px}.schedule-panel{min-width:0;max-width:100%;box-sizing:border-box}.schedule-scroll{overflow-x:auto;padding-bottom:5px}.schedule-chart{min-width:670px}.schedule-axis,.schedule-row{display:grid;grid-template-columns:minmax(145px,1fr) 150px minmax(220px,1.5fr);align-items:center;gap:12px}.schedule-axis{padding:0 10px 9px;color:#718087;font-size:10px}.schedule-axis-track{display:flex;justify-content:space-between;grid-column:3}.schedule-axis-track time,.schedule-dates{font-size:10px;color:#718087}.schedule-row{min-height:49px;padding:7px 10px;border-top:1px solid #edf1f2}.schedule-task{display:grid;gap:3px;min-width:0}.schedule-task strong{overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:#204b61;font-size:11px}.schedule-task span{color:#8b989c;font-size:10px}.schedule-track{position:relative;height:16px;border-radius:99px;background:repeating-linear-gradient(90deg,#f3f7f8 0,#f3f7f8 calc(20% - 1px),#e4ecee calc(20% - 1px),#e4ecee 20%)}.schedule-bar{position:absolute;top:3px;bottom:3px;min-width:8px;border-radius:99px;background:#8ecde2}.schedule-milestone{width:11px!important;height:11px;top:2px;bottom:auto;min-width:11px;border:2px solid #204b61;background:#fff;transform:rotate(45deg);border-radius:2px}.schedule-unscheduled{margin:12px 0 0;color:#718087;font-size:11px}.task-schedule-form{display:flex;flex-wrap:wrap;align-items:end;gap:8px;margin-top:10px}.task-schedule-form label{display:grid;gap:4px;color:#718087;font-size:10px;font-weight:600}.task-schedule-form input{width:145px;margin:0;padding:7px 9px;font-size:11px}.task-schedule-form .secondary-button{min-height:36px;padding:7px 10px;font-size:10px}@media(max-width:700px){.schedule-panel{padding:16px}.schedule-scroll{margin-right:-4px}.task-schedule-form{width:100%}.task-schedule-form label{flex:1;min-width:125px}.task-schedule-form input{width:100%}.task-schedule-form .secondary-button{width:100%}}
.ai-planning-panel{margin-bottom:18px}.ai-planning-panel form>.field-help{max-width:640px}.ai-feedback-option{display:flex;align-items:flex-start;gap:9px;margin:12px 0 4px;color:#344d56;font-size:12px;line-height:1.5}.ai-feedback-option input{margin-top:2px;accent-color:#287ca1}.ai-proposal{margin-top:18px;padding:17px;background:#f7fbfc;border:1px solid #dcebed;border-radius:13px}.ai-proposal-meta{display:flex;flex-wrap:wrap;gap:8px 14px;color:#718087;font-size:11px}.ai-proposal-meta strong{color:#204b61}.ai-proposal>p{color:#52666e;font-size:13px;line-height:1.6}.ai-task-row{display:grid;grid-template-columns:minmax(150px,.35fr) minmax(0,1fr);gap:16px;padding:14px 0;border-top:1px solid #e6eeee}.ai-task-row>.field,.ai-task-fields .field{margin:0 0 12px}.ai-task-fields{min-width:0;margin:0;padding:0;border:0;display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:0 12px}.ai-task-fields>p{grid-column:1/-1;margin:4px 0;color:#718087;font-size:11px;line-height:1.6}.ai-discard-form{margin-top:10px}.ai-follow-up{margin-top:10px;padding:12px 14px;border-radius:11px;background:#f4f8f8;color:#52666e;font-size:11px}.ai-follow-up ul{margin:7px 0 0;padding-left:18px}.task-dependency{color:#718087;font-size:11px;margin:7px 0 0}.task-dependency strong{color:#52666e}@media(max-width:700px){.ai-task-row,.ai-task-fields{grid-template-columns:1fr}.ai-task-fields>p{grid-column:auto}.ai-task-row{gap:5px}.ai-proposal{padding:13px}}
.ai-assistant-panel{margin-bottom:18px}.assistant-badge{white-space:nowrap;border-radius:999px;background:#e8f5fa;color:#204b61;padding:7px 10px;font-size:10px;font-weight:700}.assistant-privacy,.assistant-usage,.assistant-sources{color:#718087;font-size:11px;line-height:1.6}.assistant-form{margin-top:14px}.assistant-form textarea{display:block;width:100%;resize:vertical;border:1px solid #d5e0e3;border-radius:10px;padding:12px;color:#202e35;font:inherit}.assistant-form-footer{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-top:10px}.assistant-history-heading{margin:22px 0 10px;color:#204b61;font-size:14px}.assistant-run{margin-top:10px;padding:14px;background:#f7fbfc;border:1px solid #dcebed;border-radius:12px}.assistant-run-meta{display:flex;align-items:center;flex-wrap:wrap;gap:8px 12px;color:#718087;font-size:11px}.assistant-run-meta strong{color:#204b61}.assistant-state{padding:4px 8px;border-radius:999px;background:#edf1f2}.assistant-state-completed{background:#e8f6ed;color:#287348}.assistant-state-failed{background:#fff0ef;color:#a73d39}.assistant-state-running,.assistant-state-queued{background:#fff6df;color:#876516}.assistant-answer{white-space:pre-wrap;color:#344d56;font-size:13px;line-height:1.7}.assistant-error{color:#a73d39;font-size:12px}.assistant-pending{color:#718087;font-size:12px}.assistant-sources,.assistant-usage{margin:8px 0 0}.assistant-sources strong{color:#52666e}@media(max-width:700px){.assistant-form-footer{align-items:stretch;flex-direction:column}.assistant-form-footer .primary-button{width:100%}.assistant-badge{font-size:9px}}
.client-assignment-panel{margin:0 0 18px}.client-assignment-form{display:flex;align-items:end;gap:12px}.client-assignment-form .field{flex:1;margin:0}.client-assignment-form .secondary-button{min-height:44px}@media(max-width:700px){.client-assignment-form{align-items:stretch;flex-direction:column}}
.drawing-responses{margin-top:16px;padding:14px;border:1px solid #dcebee;border-radius:12px;background:#f5fbfc}.drawing-responses h3{margin:0;font-size:13px}.drawing-responses article{margin-top:10px;padding:12px;background:white;border-radius:10px}.drawing-responses article strong{font-size:12px;color:#204b61}.drawing-responses article p{margin:6px 0;color:#52666e;font-size:12px;line-height:1.5}
.approval-ai-proposal{margin-top:12px}.approval-ai-summary{color:#344d56;font-size:13px;line-height:1.6}.approval-ai-item{margin-top:10px;padding:14px;border:1px solid #dcebed;border-radius:12px;background:#fff}.approval-ai-meta{display:flex;align-items:center;flex-wrap:wrap;gap:7px 11px;color:#718087;font-size:10px}.approval-ai-meta strong{color:#204b61;font-size:12px}.approval-ai-kind{padding:4px 8px;border-radius:99px;background:#eaf6fa;color:#326c82;font-weight:700}.approval-ai-item blockquote{margin:12px 0;padding:10px 12px;border-left:3px solid #8ecde2;background:#f7fbfc;color:#52666e;font-size:12px;line-height:1.6;white-space:pre-wrap}.approval-ai-item blockquote strong{color:#204b61}.approval-ai-item>p{color:#52666e;font-size:12px;line-height:1.55}.approval-ai-anchor{font-size:10px!important;color:#718087!important}.approval-ai-item .secondary-button{min-height:34px;padding:6px 10px;font-size:10px}
</style>
<style>
.demand-attachments{margin:18px 0}.attachment-upload{margin-top:14px}.attachment-drop{display:grid;justify-items:center;gap:7px;padding:24px;border:1px dashed #9fc8d4;border-radius:14px;background:#f7fbfc;text-align:center;color:#204b61;cursor:pointer;transition:background .15s,border-color .15s}.attachment-drop:hover,.attachment-drop.is-dragging{border-color:#39758b;background:#eaf6fa}.attachment-drop strong{font-size:13px}.attachment-drop>span:last-of-type{color:#718087;font-size:11px}.attachment-drop-icon{display:grid;place-items:center;width:34px;height:34px;border-radius:50%;background:#e3f3f8;color:#326c82;font-size:22px}.attachment-drop input{max-width:100%;margin-top:6px;color:#52666e;font:inherit;font-size:11px}.attachment-upload-footer{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-top:11px}.attachment-list{list-style:none;padding:0;margin:16px 0 0}.attachment-item{display:flex;align-items:center;gap:12px;padding:11px 0;border-top:1px solid #edf0ef}.attachment-file-icon{display:grid;place-items:center;flex:0 0 38px;height:38px;border-radius:10px;background:#eaf6fa;color:#326c82;font-size:9px;font-weight:800}.attachment-file-info{display:grid;gap:4px;min-width:0;flex:1}.attachment-file-info>a{overflow:hidden;color:#204b61;text-overflow:ellipsis;white-space:nowrap;font-size:12px;font-weight:700}.attachment-file-info span{color:#718087;font-size:10px}.attachment-download{flex-shrink:0;text-decoration:none}.attachment-empty{margin:16px 0 0}.attachment-preview{margin-top:7px}.attachment-preview summary{width:max-content;max-width:100%;cursor:pointer;color:#326c82;font-size:11px;font-weight:700}.attachment-preview iframe,.attachment-preview img,.attachment-preview video{display:block;width:min(100%,760px);max-height:620px;margin-top:8px;border:1px solid #e3e9e8;border-radius:10px;background:#f5f6f5}.attachment-preview iframe{height:480px}.attachment-preview img,.attachment-preview video{height:auto;object-fit:contain}@media(max-width:650px){.attachment-drop{padding:20px 12px}.attachment-upload-footer{align-items:stretch;flex-direction:column}.attachment-upload-footer .primary-button{align-self:flex-start}.attachment-item{align-items:flex-start;flex-wrap:wrap}.attachment-file-info{min-width:calc(100% - 55px)}.attachment-download{margin-left:50px}.attachment-preview iframe{height:62vh;min-height:360px}}
</style>
<style>
@media(max-width:1050px){.demand-properties{grid-template-columns:repeat(3,minmax(0,1fr))}}
@media(max-width:600px){.demand-properties{grid-template-columns:repeat(2,minmax(0,1fr))}.demand-properties>div{padding:10px}.demand-resource-properties{grid-template-columns:1fr}}
</style>
@if ($canManage)
<script>
document.querySelectorAll('[data-ai-include]').forEach((select) => {
    const row = select.closest('.ai-task-row');
    const fields = row.querySelector('.ai-task-fields');
    const sync = () => {
        fields.disabled = select.value !== '1';
    };
    select.addEventListener('change', sync);
    sync();
});
document.querySelectorAll('[data-ai-assignee-row]').forEach((row) => {
    const profile = row.querySelector('[data-ai-responsibility-profile]');
    const select = row.querySelector('[data-ai-assignee-select]');
    const label = row.querySelector('[data-ai-assignee-label]');
    const help = row.querySelector('[data-ai-assignee-help]');
    const normalize = (value) => value.normalize('NFD').replace(/[\u0300-\u036f]/g, '').trim().toLocaleLowerCase('pt-BR');
    const refreshSuggestion = () => {
        const requestedSpecialty = normalize(profile.value);
        const matches = [...select.options].filter((option) => {
            try {
                return JSON.parse(option.dataset.professionalSpecialties || '[]')
                    .some((specialty) => normalize(specialty) === requestedSpecialty);
            } catch {
                return false;
            }
        });

        for (const option of select.options) {
            const name = option.dataset.professionalName || option.textContent;
            option.textContent = name + (matches.includes(option) ? ' · especialidade compatível' : '');
        }

        label.textContent = matches.length === 1 ? 'Responsável sugerido — confirme' : 'Responsável';
        help.textContent = matches.length === 1
            ? 'Sugestão local pela correspondência exata da especialidade cadastrada. Confirme antes de aplicar.'
            : matches.length > 1
                ? 'Mais de uma pessoa informou esta especialidade; escolha quem executará.'
                : 'Nenhuma especialidade cadastrada corresponde exatamente ao perfil. Escolha manualmente.';
        select.value = matches.length === 1 ? matches[0].value : '';
    };

    profile.addEventListener('input', () => {
        select.dataset.aiSuggested = '0';
        refreshSuggestion();
    });
    if (select.dataset.aiSuggested !== '1') refreshSuggestion();
});
</script>
@endif
@if ($canManage)
<script>
document.addEventListener('click', (event) => {
    const button = event.target.closest('[data-task-title]');
    if (!button) return;
    const input = document.querySelector('.inline-task-form input[name="title"]');
    if (!input) {
        button.textContent = 'Abra Adicionar tarefa para usar este título';
        return;
    }
    input.value = button.dataset.taskTitle || '';
    input.focus();
    input.scrollIntoView({ behavior: 'smooth', block: 'center' });
});
</script>
@endif
<script>
document.querySelectorAll('[data-attachment-upload]').forEach((form) => {
    const input = form.querySelector('[data-attachment-input]');
    const drop = form.querySelector('[data-attachment-drop]');
    const names = form.querySelector('[data-attachment-names]');
    const error = form.querySelector('[data-attachment-upload-error]');
    const serverFileLimit = Number(form.dataset.serverFileLimit);
    const serverPostLimit = Number(form.dataset.serverPostLimit);
    const appFileLimit = Number(form.dataset.appFileLimit);
    const fileCountLimit = Number(form.dataset.appFileCount);
    const fileLimit = Math.min(serverFileLimit || appFileLimit, appFileLimit);
    const postLimit = serverPostLimit > 0 ? serverPostLimit : Infinity;
    const updateNames = () => {
        const files = [...input.files];
        names.textContent = files.length ? files.map((file) => file.name).join(', ') : 'Nenhum arquivo selecionado.';
        const oversized = files.find((file) => file.size > fileLimit);
        const totalBytes = files.reduce((total, file) => total + file.size, 0);
        let message = '';
        if (files.length > fileCountLimit) {
            message = `Escolha no máximo ${fileCountLimit} arquivos por envio.`;
        } else if (oversized) {
            message = `${oversized.name} excede o limite efetivo de ${Math.floor(fileLimit / 1024 / 1024)} MB por arquivo neste servidor.`;
        } else if (totalBytes > postLimit - 262144) {
            message = `O conjunto de arquivos excede o limite de envio do servidor (${Math.floor(postLimit / 1024 / 1024)} MB). Envie menos arquivos por vez.`;
        }
        error.textContent = message;
        error.hidden = message === '';
    };
    input.addEventListener('change', updateNames);
    ['dragenter', 'dragover'].forEach((eventName) => drop.addEventListener(eventName, (event) => {
        event.preventDefault();
        drop.classList.add('is-dragging');
    }));
    ['dragleave', 'drop'].forEach((eventName) => drop.addEventListener(eventName, (event) => {
        event.preventDefault();
        drop.classList.remove('is-dragging');
    }));
    drop.addEventListener('drop', (event) => {
        input.files = event.dataTransfer.files;
        updateNames();
    });
    form.addEventListener('submit', (event) => {
        updateNames();
        if (!error.hidden) {
            event.preventDefault();
            error.scrollIntoView({behavior: 'smooth', block: 'center'});
        }
    });
});
</script>
@vite('resources/js/assistant-polling.js')
@endsection
