@extends('layouts.app')

@section('title', ($personal ? 'Meu trabalho' : 'Produção da equipe').' · Plataforma Mix7')

@section('body')
<div class="shell">
    @include('layouts.navigation', ['active' => 'activity'])
    <main class="main">
        @include('layouts.topbar')
        <div class="content">
            <p class="eyebrow">{{ $personal ? 'Minha rotina' : 'Acompanhamento da equipe' }}</p>
            <h1 class="heading">{{ $personal ? 'Meu trabalho' : 'Produção da equipe' }}</h1>
            <p class="subheading">{{ $personal ? 'Veja suas tarefas ativas e o tempo que você registrou.' : 'Acompanhe tarefas atribuídas e tempo registrado por profissional.' }}</p>

            @if ($personal && $activeEntry)
                <section class="activity-timer" aria-live="polite">
                    <div><span class="eyebrow">Cronômetro em andamento</span><strong>{{ $activeEntry->task->title }}</strong><span>O tempo continua sendo registrado nesta tarefa.</span></div>
                    <form method="post" action="{{ route('demand-tasks.timer.pause', $activeEntry->task) }}">@csrf<button class="secondary-button" type="submit">Pausar cronômetro</button></form>
                </section>
            @endif

            <section class="activity-period-note">
                <strong>Período móvel de 30 dias</strong>
                <span>{{ $periodStart->format('d/m/Y') }} a {{ $periodEnd->format('d/m/Y') }}. Tarefas concluídas usam a data registrada de conclusão; tempo é a soma das sessões iniciadas no período, incluindo cronômetro ativo.</span>
            </section>

            <div class="activity-grid" aria-label="Indicadores objetivos">
                @if ($rows->isEmpty())
                    <div class="empty-state"><h2>Nenhum profissional cadastrado</h2><p>Quando houver profissionais na equipe, a atividade deles aparecerá aqui.</p></div>
                @else
                @foreach ($rows as $row)
                    @php($hours = number_format($row['recorded_seconds_30d'] / 3600, 1, ',', '.'))
                    @php($estimateHours = number_format($row['estimate_minutes'] / 60, 1, ',', '.'))
                    <article class="activity-card">
                        <div class="activity-person"><span class="avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr($row['user']->name, 0, 1)) }}</span><div><h2>{{ $personal ? 'Sua atividade' : $row['user']->name }} @unless ($row['is_active'])<span class="pill pill-blocked">Acesso desativado</span>@endunless</h2><span>{{ $row['open'] }} {{ $row['open'] === 1 ? 'tarefa aberta' : 'tarefas abertas' }}</span></div></div>
                        <div class="activity-statuses" aria-label="Tarefas abertas por etapa">
                            <span><strong>{{ $row['todo'] }}</strong> A fazer</span><span><strong>{{ $row['in_progress'] }}</strong> Em andamento</span><span><strong>{{ $row['paused'] }}</strong> Pausadas</span><span><strong>{{ $row['blocked'] }}</strong> Impedidas</span>
                        </div>
                        <div class="activity-measures"><div><strong>{{ $row['completed_30d'] }}</strong><span>concluídas em 30 dias</span></div><div><strong>{{ $hours }} h</strong><span>registradas em 30 dias</span></div><div><strong>{{ $estimateHours }} h</strong><span>estimadas nas tarefas abertas</span></div></div>
                    </article>
                @endforeach
                @endif
            </div>

            @if ($personal)
                <section class="activity-task-section">
                    <div class="section-heading"><div><h2>Suas tarefas abertas</h2><p>Abra a demanda para ver briefing, dependências e atualizações.</p></div></div>
                    @forelse ($myTasks as $task)
                        <article class="activity-task">
                            <div class="activity-task-main"><span class="pill">{{ $task->status->label() }}</span><a href="{{ route('demands.show', $task->demand) }}"><strong>{{ $task->title }}</strong></a><span>{{ $task->demand->title }}</span></div>
                            <div class="activity-task-actions">
                                @if ($activeEntry && $activeEntry->task_id === $task->id)
                                    <form method="post" action="{{ route('demand-tasks.timer.pause', $task) }}">@csrf<button class="secondary-button" type="submit">Pausar</button></form>
                                @elseif (! $activeEntry)
                                    <form method="post" action="{{ route('demand-tasks.timer.start', $task) }}">@csrf<button class="secondary-button" type="submit">Iniciar tempo</button></form>
                                @endif
                            </div>
                        </article>
                    @empty
                        <div class="empty-state"><h2>Você não tem tarefas abertas</h2><p>Quando a equipe atribuir uma tarefa a você, ela aparecerá aqui.</p></div>
                    @endforelse
                    <div class="pagination-wrap">{{ $myTasks->links() }}</div>
                </section>
            @endif

            <p class="footnote">Estes números descrevem registros do sistema; não são nota, ranking nem avaliação automática. A fórmula de avaliação ainda precisa ser definida pela Mix7.</p>
        </div>
    </main>
</div>
@endsection
