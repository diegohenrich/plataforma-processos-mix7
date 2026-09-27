@extends('layouts.app')

@section('title', 'Demandas · Plataforma Mix7')

@section('body')
<style>
    .demand-view-tools{display:flex;align-items:center;gap:8px;margin:-8px 0 20px}.demand-view-tools a{display:inline-flex;align-items:center;min-height:38px;padding:7px 12px;border:1px solid #d6e0e1;border-radius:10px;background:#fff;color:#52666e;text-decoration:none;font-size:12px;font-weight:650}.demand-view-tools a[aria-current="page"]{border-color:#204b61;background:#eaf6fa;color:#204b61}
    .kanban-display-actions{display:flex;flex-wrap:wrap;gap:8px;margin:-8px 0 12px}.kanban-display-actions button{min-height:36px;padding:7px 11px;border:1px solid #d6e0e1;border-radius:9px;background:#fff;color:#315a6b;font:inherit;font-size:11px;font-weight:700;cursor:pointer}.kanban-display-actions button:focus-visible{outline:3px solid #8ecde2;outline-offset:2px}.kanban-display-actions button:disabled{color:#829095;background:#f3f6f6;cursor:default}
    body:has(.board-shell){overflow-x:hidden}.kanban-board{display:grid;grid-auto-columns:minmax(245px,1fr);grid-auto-flow:column;gap:14px;overflow-x:auto;padding:2px 2px 14px;align-items:start;min-width:0;max-width:100%}.kanban-column{min-width:0;min-height:230px;padding:12px;background:#edf3f4;border:1px solid #e0e9ea;border-radius:15px;transition:background .15s,border-color .15s}.kanban-column.is-drop-target{background:#e2f4fa;border-color:#75bbd2}.kanban-column-header{display:flex;justify-content:space-between;align-items:center;gap:8px;padding:2px 3px 12px}.kanban-column-header h2{margin:0;color:#38515b;font-size:12px;line-height:1.35}.kanban-count{display:grid;place-items:center;min-width:24px;height:24px;padding:0 7px;border-radius:99px;background:#fff;color:#52869a;font-size:11px;font-weight:750}.kanban-cards{display:grid;gap:9px;min-height:175px}.kanban-more{margin-top:9px}.kanban-more summary{width:max-content;max-width:100%;padding:8px 10px;border:1px solid #d6e0e1;border-radius:9px;background:#fff;color:#315a6b;font-size:11px;font-weight:700;cursor:pointer;list-style:none}.kanban-more summary::-webkit-details-marker{display:none}.kanban-more summary::before{content:'＋';margin-right:5px}.kanban-more[open] summary::before{content:'−'}.kanban-more[open] .kanban-cards{margin-top:9px}.kanban-card{min-width:0;padding:11px;background:#fff;border:1px solid #e0e9ea;border-radius:12px;box-shadow:0 2px 7px #204b6109}.kanban-card[draggable="true"]{cursor:grab}.kanban-card.is-dragging{opacity:.48}.kanban-card a{color:inherit;text-decoration:none}.kanban-card a:focus-visible,.kanban-card button:focus-visible,.kanban-card select:focus-visible,.kanban-move-panel summary:focus-visible,.kanban-more summary:focus-visible{outline:3px solid #8ecde2;outline-offset:2px}.kanban-card-title{display:block;margin:0;color:#202e35;font-size:13px;font-weight:750;line-height:1.4}.kanban-brief{display:-webkit-box;overflow:hidden;-webkit-box-orient:vertical;-webkit-line-clamp:2;margin:5px 0 7px;color:#687a80;font-size:11px;line-height:1.4;overflow-wrap:anywhere}.kanban-meta{display:flex;flex-wrap:wrap;gap:5px;color:#7a898e;font-size:10px;line-height:1.4}.kanban-meta span{padding:4px 7px;border-radius:999px;background:#f1f7f8}.kanban-move-panel{margin-top:7px;padding-top:6px;border-top:1px solid #edf0ef}.kanban-move-panel summary{width:max-content;color:#315a6b;font-size:10px;font-weight:700;cursor:pointer;list-style:none}.kanban-move-panel summary::-webkit-details-marker{display:none}.kanban-move-panel summary::before{content:'＋';margin-right:4px}.kanban-move-panel[open] summary::before{content:'−'}.kanban-move{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:6px;margin-top:7px}.kanban-move label{position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0,0,0,0)}.kanban-move select{min-width:0;width:100%;border:1px solid #d6e0e1;border-radius:8px;background:#fff;padding:7px;color:#38515b;font:inherit;font-size:10px}.kanban-move button{border:0;border-radius:8px;background:#204b61;color:#fff;padding:7px 9px;font:inherit;font-size:10px;font-weight:700;cursor:pointer}.kanban-move button:disabled{background:#dce5e6;color:#76868b;cursor:not-allowed}.kanban-empty{padding:12px 7px;color:#829095;text-align:center;font-size:10px}.kanban-hint{margin:0 0 12px;color:#718087;font-size:11px}
    @media(max-width:650px){.kanban-board{grid-auto-columns:minmax(245px,82vw);gap:10px}.kanban-column{min-height:205px}.kanban-cards{min-height:150px}.demand-view-tools{margin-top:-4px}}
</style>
<div class="shell board-shell">
    @include('layouts.navigation', ['active' => 'demands'])
    <main class="main">
        @include('layouts.topbar')
        <div class="content">
            <div class="page-heading">
                <div><p class="eyebrow">{{ auth()->user()->role === App\Enums\UserRole::Client ? 'Área do cliente' : 'Trabalho da agência' }}</p><h1 class="heading">{{ auth()->user()->role === App\Enums\UserRole::Client ? 'Minhas demandas' : 'Demandas' }}</h1><p class="subheading">{{ auth()->user()->role === App\Enums\UserRole::Client ? 'Acompanhe a etapa atual dos trabalhos vinculados à sua conta.' : 'Briefings, responsáveis e etapas em um só lugar.' }}</p></div>
                @can('create', App\Models\Demand::class)<a class="primary-link" href="{{ route('demands.create') }}">Nova demanda</a>@endcan
            </div>

            @include('partials.flash')

            @if (auth()->user()->role !== App\Enums\UserRole::Client)
                <nav class="demand-view-tools" aria-label="Visualização das demandas">
                    <a href="{{ route('demands.index', ['view' => 'board']) }}" @if($isBoard) aria-current="page" @endif>Quadro</a>
                    <a href="{{ route('demands.index', ['view' => 'list']) }}" @if(!$isBoard) aria-current="page" @endif>Lista</a>
                </nav>
            @endif

            @if ($demands->isEmpty())
                <section class="empty-state"><span class="empty-icon" aria-hidden="true">◷</span><h2>{{ auth()->user()->role === App\Enums\UserRole::Client ? 'Nenhuma demanda vinculada' : 'Nenhuma demanda por aqui' }}</h2><p>{{ auth()->user()->role === App\Enums\UserRole::Client ? 'A Mix7 ainda não vinculou demandas à sua conta.' : 'Quando uma demanda for criada ou atribuída a você, ela aparecerá nesta visualização.' }}</p>@can('create', App\Models\Demand::class)<a class="primary-link" href="{{ route('demands.create') }}">Criar primeira demanda</a>@endcan</section>
            @elseif ($isBoard)
                @if ($canMoveDemands)<p class="kanban-hint">Arraste um cartão para uma etapa permitida ou use o controle “Mover para”. A revisão interna só é liberada quando todas as tarefas terminam.</p>@endif
                <div class="kanban-display-actions" aria-label="Controles de exibição do quadro">
                    <button type="button" data-kanban-expand-all onclick="document.querySelectorAll('[data-kanban-more]').forEach((column) => { column.open = true; }); this.disabled = true; document.querySelector('[data-kanban-collapse-all]').disabled = false;">Expandir todas as etapas</button>
                    <button type="button" data-kanban-collapse-all disabled onclick="document.querySelectorAll('[data-kanban-more]').forEach((column) => { column.open = false; }); this.disabled = true; document.querySelector('[data-kanban-expand-all]').disabled = false;">Recolher todas as etapas</button>
                </div>
                <section class="kanban-board" aria-label="Quadro de demandas por etapa">
                    @foreach (App\Enums\DemandStatus::cases() as $stage)
                        <section class="kanban-column" data-kanban-column data-stage="{{ $stage->value }}" aria-label="{{ $stage->label() }}">
                            <header class="kanban-column-header"><h2>{{ $stage->label() }}</h2><span class="kanban-count">{{ $boardColumns[$stage->value]->count() }}</span></header>
                            @php
                                $columnDemands = $boardColumns[$stage->value];
                            @endphp
                            <div class="kanban-cards">
                                @forelse ($columnDemands as $demand)
                                    @if ($loop->index === 6)
                                        </div><details class="kanban-more" data-kanban-more><summary>Mostrar {{ $columnDemands->count() - 6 }} demandas</summary><div class="kanban-cards">
                                    @endif
                                    @php
                                        $nextStatuses = $demand->status->nextByManagement();
                                        if ($demand->tasks->contains(fn ($task) => $task->status !== App\Enums\TaskStatus::Completed)) {
                                            $nextStatuses = array_values(array_filter($nextStatuses, fn ($next) => $next !== App\Enums\DemandStatus::InternalReview));
                                        }
                                        $assigneeNames = $demand->tasks->pluck('assignee.name')->filter()->unique()->take(2)->implode(', ');
                                    @endphp
                                    <article class="kanban-card" data-kanban-card data-next-stages="{{ collect($nextStatuses)->pluck('value')->implode(',') }}" draggable="{{ $canMoveDemands && count($nextStatuses) ? 'true' : 'false' }}">
                                        <a href="{{ route('demands.show', $demand) }}" aria-label="Abrir demanda: {{ $demand->title }}">
                                            <span class="kanban-card-title">{{ $demand->title }}</span>
                                            <span class="kanban-brief">{{ $demand->ai_summary ?: \Illuminate\Support\Str::limit($demand->brief, 125) }}</span>
                                        </a>
                                        <div class="kanban-meta">
                                            @if ($demand->module_key)<span>{{ $demand->moduleDisplayLabel() }} · v{{ $demand->module_version }}</span>@endif
                                            <span>{{ $demand->tasks->count() }} {{ \Illuminate\Support\Str::plural('tarefa', $demand->tasks->count()) }}</span>
                                            <span>{{ $demand->tasks->where('status', App\Enums\TaskStatus::Completed)->count() }} concluídas</span>
                                            @if($assigneeNames)<span title="{{ $assigneeNames }}">{{ $assigneeNames }}</span>@endif
                                        </div>
                                        @if($canMoveDemands && count($nextStatuses))
                                            <details class="kanban-move-panel">
                                                <summary>Mover demanda</summary>
                                                <form class="kanban-move" method="post" action="{{ route('demands.status', $demand) }}" data-kanban-move>
                                                    @csrf @method('PATCH')
                                                    <label for="next-status-{{ $demand->id }}">Mover para</label>
                                                    <select id="next-status-{{ $demand->id }}" name="status" data-next-status>
                                                        @foreach($nextStatuses as $next)<option value="{{ $next->value }}">{{ $next->label() }}</option>@endforeach
                                                    </select>
                                                    <button type="submit">Mover</button>
                                                </form>
                                            </details>
                                        @elseif($canMoveDemands)
                                            <p class="kanban-meta" style="margin:9px 0 0">{{ $demand->status === App\Enums\DemandStatus::ClientApproval ? 'Aguardando decisão do cliente pelo link.' : ($demand->status === App\Enums\DemandStatus::InProgress ? 'A revisão interna será liberada após concluir todas as tarefas.' : 'Sem próxima etapa') }}</p>
                                        @endif
                                    </article>
                                @empty
                                    <p class="kanban-empty">Nenhuma demanda nesta etapa.</p>
                                @endforelse
                            </div>@if ($columnDemands->count() > 6)</details>@endif
                        </section>
                    @endforeach
                </section>
            @else
                <section class="demand-list" aria-label="Lista de demandas">
                    @foreach ($demands as $demand)
                        <a class="demand-card" href="{{ route('demands.show', $demand) }}">
                            @if (auth()->user()->role === App\Enums\UserRole::Client)
                                <div class="demand-card-main"><div class="demand-title-row"><h2>{{ $demand->title }}</h2><span class="pill">{{ $demand->status->label() }}</span></div><p>Acompanhe a etapa atual. A aprovação de materiais será enviada em um link separado.</p><span class="meta-line">Atualizada em {{ $demand->updated_at->format('d/m/Y') }}</span></div>
                            @else
                                <div class="demand-card-main"><div class="demand-title-row"><h2>{{ $demand->title }}</h2><span class="pill">{{ $demand->status->label() }}</span></div><p>{{ $demand->ai_summary ?: \Illuminate\Support\Str::limit($demand->brief, 145) }}</p><span class="meta-line">Criada por {{ $demand->creator->name }} · {{ $demand->created_at->format('d/m/Y') }}</span></div>
                                <div class="demand-card-side"><strong>{{ $demand->tasks->count() }}</strong><span>{{ \Illuminate\Support\Str::plural('tarefa', $demand->tasks->count()) }}</span><span class="task-mini">{{ $demand->tasks->where('status', App\Enums\TaskStatus::Completed)->count() }} concluídas</span></div>
                            @endif
                        </a>
                    @endforeach
                </section>
                <div class="pagination-wrap">{{ $demands->links() }}</div>
            @endif
        </div>
    </main>
</div>
@if ($isBoard && $canMoveDemands)
    <script>
        document.querySelectorAll('[data-kanban-card][draggable="true"]').forEach((card) => {
            card.addEventListener('dragstart', (event) => {
                if (event.target.closest('form, button, select, summary')) { event.preventDefault(); return; }
                card.classList.add('is-dragging');
                event.dataTransfer.effectAllowed = 'move';
                event.dataTransfer.setData('text/plain', card.dataset.nextStages);
            });
            card.addEventListener('dragend', () => card.classList.remove('is-dragging'));
        });
        document.querySelectorAll('[data-kanban-column]').forEach((column) => {
            column.addEventListener('dragover', (event) => {
                const card = document.querySelector('.kanban-card.is-dragging');
                if (!card || !card.dataset.nextStages.split(',').includes(column.dataset.stage)) return;
                event.preventDefault();
                event.dataTransfer.dropEffect = 'move';
                column.classList.add('is-drop-target');
            });
            column.addEventListener('dragleave', (event) => {
                if (!column.contains(event.relatedTarget)) column.classList.remove('is-drop-target');
            });
            column.addEventListener('drop', (event) => {
                const card = document.querySelector('.kanban-card.is-dragging');
                column.classList.remove('is-drop-target');
                if (!card || !card.dataset.nextStages.split(',').includes(column.dataset.stage)) return;
                event.preventDefault();
                const form = card.querySelector('[data-kanban-move]');
                if (!form) return;
                form.querySelector('[data-next-status]').value = column.dataset.stage;
                form.requestSubmit();
            });
        });
    </script>
@endif
@endsection
