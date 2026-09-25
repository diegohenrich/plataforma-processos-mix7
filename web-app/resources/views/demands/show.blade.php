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
            <section class="demand-hero"><div><p class="eyebrow">{{ $demand->organization->name }} · Demanda #{{ $demand->id }}</p><h1 class="heading">{{ $demand->title }}</h1><p class="meta-line">Criada por {{ $demand->creator->name }} em {{ $demand->created_at->format('d/m/Y H:i') }}</p></div><span class="pill pill-large">{{ $demand->status->label() }}</span></section>
            @error('status')<div class="notice notice-error">{{ $message }}</div>@enderror
            @if ($canManage)
                <section class="workflow-card"><div class="section-heading"><div><h2>Etapa do trabalho</h2><p>Avance conforme o trabalho e as decisões forem registrados.</p></div></div><ol class="stage-track">@foreach (App\Enums\DemandStatus::cases() as $stage)<li class="{{ $stage === $demand->status ? 'current' : (array_search($stage, App\Enums\DemandStatus::cases(), true) < array_search($demand->status, App\Enums\DemandStatus::cases(), true) ? 'past' : '') }}"><span class="stage-dot"></span><span>{{ $stage->label() }}</span></li>@endforeach</ol>@if ($nextStatuses)<div class="stage-actions"><span>Próxima ação:</span>@foreach ($nextStatuses as $next)<form method="post" action="{{ route('demands.status', $demand) }}">@csrf @method('PATCH')<input type="hidden" name="status" value="{{ $next->value }}"><button class="secondary-button" type="submit">{{ $next->label() }}</button></form>@endforeach</div>@else<span class="notice-inline">Demanda concluída.</span>@endif</section>
            @endif

            @if ($canManage)
                <section class="panel review-link-panel">
                    <div class="section-heading"><div><h2>Revisão do cliente por link</h2><p>O briefing e as tarefas internas ficam privados. As respostas de todas as versões permanecem registradas aqui.</p></div></div>
                    @if ($demand->status === App\Enums\DemandStatus::ClientApproval)
                        @if (session('review_link_url'))
                            <div class="notice notice-success"><strong>Link da versão {{ $reviewLinks->firstWhere('id', session('review_link_id'))?->version }} criado.</strong><p>Copie e envie ao cliente. Por segurança, o link completo aparece somente nesta confirmação.</p><a href="{{ session('review_link_url') }}" target="_blank" rel="noopener noreferrer">{{ session('review_link_url') }}</a></div>
                        @endif
                        <form method="post" enctype="multipart/form-data" action="{{ route('demand-reviews.store', $demand) }}" class="review-link-form">@csrf
                            <label class="field">Link do material (opcional se anexar arquivo)<input type="url" name="material_url" maxlength="2048" placeholder="https://..." value="{{ old('material_url') }}"></label>
                            <label class="field">Ou anexe PDF, imagem ou vídeo (máx. 20 MB)<input type="file" name="material_file" accept=".pdf,.jpg,.jpeg,.png,.webp,.mp4,.webm,application/pdf,image/jpeg,image/png,image/webp,video/mp4,video/webm"></label>
                            <label class="field">Link válido até (horário local deste dispositivo)<input id="review-expires-local" type="datetime-local" required value="{{ old('expires_at_local') }}"></label><input id="review-expires-utc" type="hidden" name="expires_at" value="{{ old('expires_at') }}">
                            <button class="primary-button" type="submit">Criar link de revisão</button>
                        </form>
                        @error('material_url')<span class="error">{{ $message }}</span>@enderror @error('material_file')<span class="error">{{ $message }}</span>@enderror @error('expires_at')<span class="error">{{ $message }}</span>@enderror
                    @else
                        <p class="empty-inline">Para enviar uma nova versão, avance a demanda para Aprovação do cliente.</p>
                    @endif
                    @if ($reviewLinks->isNotEmpty())
                        <h3 class="review-history-title">Versões enviadas</h3>
                        <ul class="review-link-list">@foreach ($reviewLinks as $reviewLink)@php($hasFinalDecision = $reviewLink->responses->contains(fn ($response) => in_array($response->type, ['approved', 'changes_requested'], true)))<li><div><strong>Versão {{ $reviewLink->version }}</strong><span><time class="review-expiry" datetime="{{ $reviewLink->expires_at->toISOString() }}">{{ $reviewLink->expires_at->format('d/m/Y H:i') }}</time> (horário local)@if ($reviewLink->revoked_at) · Revogado @elseif ($hasFinalDecision) · Respondida @elseif ($reviewLink->expires_at->isPast()) · Expirado @else · Ativo @endif</span><span>{{ $reviewLink->material_file_name ?: $reviewLink->material_url }}</span><span>{{ $reviewLink->responses->count() }} resposta(s)</span>@if ($reviewLink->material_file_path)<details class="private-material"><summary>Visualizar arquivo desta versão</summary>@if ($reviewLink->material_mime === 'application/pdf')@include('components.pdf-preview', ['pdfUrl' => route('demand-reviews.team-material', [$demand, $reviewLink]), 'pdfName' => $reviewLink->material_file_name])@else<iframe title="Arquivo privado da versão {{ $reviewLink->version }}" src="{{ route('demand-reviews.team-material', [$demand, $reviewLink]) }}" loading="lazy"></iframe>@endif<a href="{{ route('demand-reviews.team-material', [$demand, $reviewLink]) }}" target="_blank" rel="noopener noreferrer">Abrir arquivo em outra guia</a> · <a href="{{ route('demand-reviews.team-material', [$demand, $reviewLink]) }}?download=1">Baixar arquivo</a></details>@endif @foreach ($reviewLink->responses as $response)<article class="review-feedback"><strong>{{ $response->reviewer_name }} · {{ match($response->type) {'approved' => 'Aprovou', 'changes_requested' => 'Pediu ajustes', 'annotation' => 'Anotou no material', default => 'Comentou'} }}</strong>@if ($response->anchor_type && $response->anchor_data)<span>{{ match($response->anchor_type) {'text' => 'Trecho: “'.($response->anchor_data['text'] ?? '').'”', 'area' => 'Área: X '.($response->anchor_data['x'] ?? '?').'%, Y '.($response->anchor_data['y'] ?? '?').'%', 'time' => 'Vídeo em '.($response->anchor_data['time'] ?? ''), 'page' => 'Página '.($response->anchor_data['page'] ?? ''), default => ''} }} · {{ $response->anchor_data['url'] ?? $reviewLink->material_url }}</span>@endif<p>{{ $response->comment ?: 'Sem comentário adicional.' }}</p><time>{{ $response->created_at->format('d/m/Y H:i') }}</time></article>@endforeach</div>@if (!$reviewLink->revoked_at && $reviewLink->expires_at->isFuture())<form method="post" action="{{ route('demand-reviews.revoke', [$demand, $reviewLink]) }}">@csrf @method('DELETE')<button class="secondary-button" type="submit">Revogar</button></form>@endif</li>@endforeach</ul>
                    @endif
                </section>
                @if ($reviewLinks->contains('material_mime', 'application/pdf'))
                    @vite('resources/js/pdf-preview.js')
                @endif
                <script>document.querySelectorAll('.review-expiry').forEach((time) => { time.textContent = new Intl.DateTimeFormat('pt-BR', { dateStyle: 'short', timeStyle: 'short' }).format(new Date(time.dateTime)); });</script>
                @if ($demand->status === App\Enums\DemandStatus::ClientApproval)<script>document.querySelector('.review-link-form')?.addEventListener('submit', function () { const local = document.getElementById('review-expires-local'); document.getElementById('review-expires-utc').value = new Date(local.value).toISOString(); });</script>@endif
            @endif

            <div class="detail-grid">
                <div class="detail-main">
                    <section class="panel"><div class="section-heading"><div><h2>Briefing</h2><p>O pedido original fica guardado na demanda.</p></div></div><div class="brief-text">{{ $demand->brief }}</div></section>
                    <section class="panel"><div class="section-heading"><div><h2>Tarefas <span class="count-badge">{{ $tasks->count() }}</span></h2><p>Quem faz cada parte e em que ponto está.</p></div></div>
                        @forelse ($tasks as $task)
                            @php($workedSeconds = $task->timeEntries->sum(fn ($entry) => ($entry->ended_at ?? now())->getTimestamp() - $entry->started_at->getTimestamp()))
                            <article class="task-card"><div class="task-card-content"><div class="task-card-heading"><h3>{{ $task->title }}</h3><span class="pill pill-task pill-{{ $task->status->value }}">{{ $task->status->label() }}</span></div><p>Atribuída a <strong>{{ $task->assignee->name }}</strong> · Criada por {{ $task->creator->name }}@if ($task->estimate_minutes) · {{ $task->estimate_minutes }} min estimados @endif @can('trackTime', $task)· <span data-total-seconds="{{ $workedSeconds }}">{{ gmdate('H:i:s', $workedSeconds) }} registrados</span>@endcan</p>
                                    @can('trackTime', $task)<div class="timer-actions">@if ($activeTimeTaskId === $task->id)<form method="post" action="{{ route('demand-tasks.timer.pause', $task) }}">@csrf<button type="submit" class="secondary-button">Pausar cronômetro</button></form>@else<form method="post" action="{{ route('demand-tasks.timer.start', $task) }}">@csrf<button type="submit" class="secondary-button" {{ $activeTimeTaskId ? 'disabled' : '' }}>Iniciar cronômetro</button></form>@endif</div>@endcan</div>@can('updateStatus', $task)<form method="post" action="{{ route('demand-tasks.status', $task) }}" class="task-status-form">@csrf @method('PATCH')<label class="sr-only" for="task-status-{{ $task->id }}">Atualizar status de {{ $task->title }}</label><select id="task-status-{{ $task->id }}" name="status"><option value="{{ $task->status->value }}">{{ $task->status->label() }}</option>@foreach ($task->status->next() as $status)<option value="{{ $status->value }}">{{ $status->label() }}</option>@endforeach</select><button type="submit" class="icon-button" aria-label="Salvar status da tarefa">Salvar</button></form>@endcan</article>
                        @empty
                            <p class="empty-inline">Você ainda não tem tarefas atribuídas nesta demanda.</p>
                        @endforelse
                    </section>
                    @error('timer')<div class="notice notice-error">{{ $message }}</div>@enderror
                    @if ($canManage)
                        <section class="panel"><div class="section-heading"><div><h2>Adicionar tarefa</h2><p>Inclua outra parte do trabalho e atribua à equipe.</p></div></div>@if ($professionals->isEmpty())<p class="empty-inline">Cadastre profissionais ativos para poder atribuir tarefas.</p>@else<form method="post" action="{{ route('demand-tasks.store', $demand) }}" class="inline-task-form">@csrf<label class="field">Nome da tarefa<input name="title" maxlength="180" required placeholder="Ex.: Preparar versão para revisão"></label><label class="field">Responsável<select name="assignee_id" required><option value="">Selecione uma pessoa</option>@foreach ($professionals as $professional)<option value="{{ $professional->id }}">{{ $professional->name }}</option>@endforeach</select></label><label class="field estimate-field">Estimativa (min)<input name="estimate_minutes" type="number" min="1" max="100000" placeholder="Opcional"></label><button class="primary-button" type="submit">Adicionar</button></form>@endif @error('title')<span class="error">{{ $message }}</span>@enderror @error('assignee_id')<span class="error">{{ $message }}</span>@enderror</section>
                    @endif
                </div>
                <aside class="detail-side"><section class="panel history-panel"><div class="section-heading"><div><h2>Histórico</h2><p>Registro das ações nesta demanda.</p></div></div><ol class="history-list">@forelse ($events as $event)<li><span class="history-dot"></span><div><p>{{ $event->summary }}</p><time datetime="{{ $event->created_at->toISOString() }}">{{ $event->created_at->format('d/m/Y H:i') }}</time></div></li>@empty<p class="empty-inline">Nenhuma atividade registrada.</p>@endforelse</ol></section></aside>
            </div>
        </div>
    </main>
</div>
@endsection
