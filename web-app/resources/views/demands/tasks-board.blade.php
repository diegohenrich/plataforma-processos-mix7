@extends('layouts.app')

@section('title', 'Quadro de tarefas · Plataforma Mix7')

@section('body')
<style>
    .task-board-tools{display:flex;flex-wrap:wrap;gap:8px;margin:-8px 0 20px}.task-board-tools a{display:inline-flex;align-items:center;min-height:38px;padding:7px 12px;border:1px solid #d6e0e1;border-radius:10px;background:#fff;color:#52666e;text-decoration:none;font-size:12px;font-weight:650}.task-board-tools a[aria-current="page"]{border-color:#204b61;background:#eaf6fa;color:#204b61}
    .task-board-hint{margin:0 0 12px;color:#718087;font-size:11px}body:has(.board-shell){overflow-x:hidden}.task-board{display:grid;grid-auto-columns:minmax(245px,1fr);grid-auto-flow:column;gap:14px;overflow-x:auto;padding:2px 2px 14px;align-items:start;min-width:0;max-width:100%}.task-column{min-width:0;min-height:245px;padding:12px;background:#edf3f4;border:1px solid #e0e9ea;border-radius:15px;transition:background .15s,border-color .15s}.task-column.is-drop-target{background:#e2f4fa;border-color:#75bbd2}.task-column-header{display:flex;justify-content:space-between;align-items:center;gap:8px;padding:2px 3px 12px}.task-column-header h2{margin:0;color:#38515b;font-size:12px;line-height:1.35}.task-column-count{display:grid;place-items:center;min-width:24px;height:24px;padding:0 7px;border-radius:99px;background:#fff;color:#52869a;font-size:11px;font-weight:750}.task-column-cards{display:grid;gap:9px;min-height:185px}.task-board-card{min-width:0;padding:13px;background:#fff;border:1px solid #e0e9ea;border-radius:12px;box-shadow:0 2px 7px #204b6109}.task-board-card[draggable="true"]{cursor:grab}.task-board-card.is-dragging{opacity:.48}.task-board-title{display:block;margin:0;color:#202e35;font-size:13px;font-weight:750;line-height:1.4;overflow-wrap:anywhere}.task-board-card a{color:inherit;text-decoration:none}.task-board-card a:hover .task-board-title{color:#39758b}.task-board-demand{display:block;margin-top:5px;color:#52869a;font-size:10px;font-weight:650}.task-board-meta{display:flex;flex-wrap:wrap;gap:5px;margin-top:9px;color:#7a898e;font-size:10px;line-height:1.4}.task-board-meta span{padding:4px 7px;border-radius:999px;background:#f1f7f8}.task-board-dependency{margin:9px 0 0;color:#98652c;font-size:10px;line-height:1.45}.task-board-move{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:6px;margin-top:10px;padding-top:9px;border-top:1px solid #edf0ef}.task-board-move label{position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0,0,0,0)}.task-board-move select{min-width:0;width:100%;border:1px solid #d6e0e1;border-radius:8px;background:#fff;padding:7px;color:#38515b;font:inherit;font-size:10px}.task-board-move button{border:0;border-radius:8px;background:#204b61;color:#fff;padding:7px 9px;font:inherit;font-size:10px;font-weight:700;cursor:pointer}.task-board-move button:disabled{background:#dce5e6;color:#76868b;cursor:not-allowed}.task-column-empty{padding:12px 7px;color:#829095;text-align:center;font-size:10px}.task-column-pagination{display:flex;align-items:center;justify-content:space-between;gap:8px;margin-top:10px;color:#718087;font-size:10px}.task-column-pagination a{color:#286b85;font-weight:700;text-decoration:none}.task-column-pagination a:hover{text-decoration:underline}
    @media(max-width:650px){.task-board{grid-auto-columns:minmax(245px,82vw);gap:10px}.task-column{min-height:220px}.task-column-cards{min-height:160px}.task-board-tools{margin-top:-4px}}
</style>
<div class="shell board-shell">
    @include('layouts.navigation', ['active' => 'task-board'])
    <main class="main">
        @include('layouts.topbar')
        <div class="content">
            <div class="page-heading"><div><p class="eyebrow">Gestão do trabalho</p><h1 class="heading">Quadro de tarefas</h1><p class="subheading">Veja cada tarefa na etapa atual, com responsável, demanda e impedimentos.</p></div><a class="primary-link" href="{{ route('demands.index') }}">Ver demandas</a></div>
            @include('partials.flash')
            <nav class="task-board-tools" aria-label="Outras visualizações de trabalho">
                <a href="{{ route('demand-tasks.board') }}" aria-current="page">Quadro de tarefas</a>
                <a href="{{ route('demands.index', ['view' => 'board']) }}">Etapas das demandas</a>
                <a href="{{ route('demands.index', ['view' => 'list']) }}">Lista de demandas</a>
            </nav>
            @if($currentUser->role !== App\Enums\UserRole::Professional)<p class="task-board-hint">Arraste cartões entre etapas permitidas ou escolha “Mover para”. Ao concluir, o cronômetro é encerrado e o registro de tempo é preservado.</p>@else<p class="task-board-hint">O quadro mostra somente tarefas atribuídas a você. Atualize o andamento pelo cartão; as dependências são conferidas antes de iniciar.</p>@endif
            <section class="task-board" aria-label="Tarefas por situação">
                @foreach(App\Enums\TaskStatus::cases() as $status)
                    <section class="task-column" data-task-column data-stage="{{ $status->value }}" aria-label="{{ $status->label() }}">
                        <header class="task-column-header"><h2>{{ $status->label() }}</h2><span class="task-column-count">{{ $boardCounts[$status->value] }}</span></header>
                        <div class="task-column-cards">
                            @forelse($boardColumns[$status->value] as $task)
                                @php
                                    $nextStatuses = $task->status->next();
                                    $openDependencies = $task->dependencies->filter(fn ($dependency) => $dependency->status !== App\Enums\TaskStatus::Completed);
                                    if ($openDependencies->isNotEmpty()) {
                                        $nextStatuses = array_values(array_filter($nextStatuses, fn ($next) => $next !== App\Enums\TaskStatus::InProgress));
                                    }
                                    $canUpdate = $currentUser->can('updateStatus', $task);
                                @endphp
                                <article class="task-board-card" data-task-card data-next-stages="{{ collect($nextStatuses)->pluck('value')->implode(',') }}" @if($canUpdate && count($nextStatuses)) draggable="true" @endif>
                                    <a href="{{ route('demands.show', $task->demand) }}"><span class="task-board-title">{{ $task->title }}</span><span class="task-board-demand">{{ $task->demand->title }} · {{ $task->demand->status->label() }}</span></a>
                                    <div class="task-board-meta"><span>{{ $task->assignee?->name ?? 'Sem responsável ativo' }}</span>@if($task->estimate_minutes)<span>{{ intdiv($task->estimate_minutes, 60) }}h {{ $task->estimate_minutes % 60 }}min estimados</span>@endif</div>
                                    @if($task->planned_due_on)<div class="task-board-meta"><span>Prazo {{ $task->planned_due_on->format('d/m/Y') }}</span></div>@endif
                                    @if($openDependencies->isNotEmpty())<p class="task-board-dependency">Aguardando {{ $openDependencies->count() }} {{ \Illuminate\Support\Str::plural('tarefa anterior', $openDependencies->count()) }}. Conclua as dependências antes de iniciar.</p>@endif
                                    @if($canUpdate && count($nextStatuses))
                                        <form class="task-board-move" method="post" action="{{ route('demand-tasks.status', $task) }}" data-task-move>
                                            @csrf @method('PATCH')
                                            <label for="next-task-status-{{ $task->id }}">Mover para</label>
                                            <select id="next-task-status-{{ $task->id }}" name="status" data-next-status>@foreach($nextStatuses as $next)<option value="{{ $next->value }}">{{ $next->label() }}</option>@endforeach</select>
                                            <button type="submit">Mover</button>
                                        </form>
                                    @endif
                                </article>
                            @empty
                                <p class="task-column-empty">Nenhuma tarefa nesta etapa.</p>
                            @endforelse
                        </div>
                        @if($boardCounts[$status->value] > 0)
                            @php($page = $boardPages[$status->value])
                            <nav class="task-column-pagination" aria-label="Paginação de tarefas: {{ $status->label() }}">
                                <span>Mostrando {{ $page['from'] }}–{{ $page['to'] }} de {{ $boardCounts[$status->value] }} tarefas</span>
                                <span class="task-column-pagination-links">@if($page['previous_url'])<a href="{{ $page['previous_url'] }}">Voltar às anteriores</a>@endif @if($page['next_url'])<a href="{{ $page['next_url'] }}">Mostrar mais tarefas</a>@endif</span>
                            </nav>
                        @endif
                    </section>
                @endforeach
            </section>
        </div>
    </main>
</div>
<script>
    document.querySelectorAll('[data-task-card][draggable="true"]').forEach((card) => {
        card.addEventListener('dragstart', (event) => {
            if (event.target.closest('form,button,select')) { event.preventDefault(); return; }
            card.classList.add('is-dragging');
            event.dataTransfer.effectAllowed = 'move';
            event.dataTransfer.setData('text/plain', card.dataset.nextStages);
        });
        card.addEventListener('dragend', () => card.classList.remove('is-dragging'));
    });
    document.querySelectorAll('[data-task-column]').forEach((column) => {
        column.addEventListener('dragover', (event) => {
            const card = document.querySelector('.task-board-card.is-dragging');
            if (!card || !card.dataset.nextStages.split(',').includes(column.dataset.stage)) return;
            event.preventDefault();
            event.dataTransfer.dropEffect = 'move';
            column.classList.add('is-drop-target');
        });
        column.addEventListener('dragleave', (event) => {
            if (!column.contains(event.relatedTarget)) column.classList.remove('is-drop-target');
        });
        column.addEventListener('drop', (event) => {
            const card = document.querySelector('.task-board-card.is-dragging');
            column.classList.remove('is-drop-target');
            if (!card || !card.dataset.nextStages.split(',').includes(column.dataset.stage)) return;
            event.preventDefault();
            const form = card.querySelector('[data-task-move]');
            if (!form) return;
            form.querySelector('[data-next-status]').value = column.dataset.stage;
            form.requestSubmit();
        });
    });
</script>
@endsection
