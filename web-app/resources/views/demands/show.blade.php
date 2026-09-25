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

            <div class="detail-grid">
                <div class="detail-main">
                    <section class="panel"><div class="section-heading"><div><h2>Briefing</h2><p>O pedido original fica guardado na demanda.</p></div></div><div class="brief-text">{{ $demand->brief }}</div></section>
                    <section class="panel"><div class="section-heading"><div><h2>Tarefas <span class="count-badge">{{ $tasks->count() }}</span></h2><p>Quem faz cada parte e em que ponto está.</p></div></div>
                        @forelse ($tasks as $task)
                            <article class="task-card"><div class="task-card-content"><div class="task-card-heading"><h3>{{ $task->title }}</h3><span class="pill pill-task pill-{{ $task->status->value }}">{{ $task->status->label() }}</span></div><p>Atribuída a <strong>{{ $task->assignee->name }}</strong> · Criada por {{ $task->creator->name }}@if ($task->estimate_minutes) · {{ $task->estimate_minutes }} min estimados @endif</p></div>@can('updateStatus', $task)<form method="post" action="{{ route('demand-tasks.status', $task) }}" class="task-status-form">@csrf @method('PATCH')<label class="sr-only" for="task-status-{{ $task->id }}">Atualizar status de {{ $task->title }}</label><select id="task-status-{{ $task->id }}" name="status"><option value="{{ $task->status->value }}">{{ $task->status->label() }}</option>@foreach ($task->status->next() as $status)<option value="{{ $status->value }}">{{ $status->label() }}</option>@endforeach</select><button type="submit" class="icon-button" aria-label="Salvar status da tarefa">Salvar</button></form>@endcan</article>
                        @empty
                            <p class="empty-inline">Você ainda não tem tarefas atribuídas nesta demanda.</p>
                        @endforelse
                    </section>
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
