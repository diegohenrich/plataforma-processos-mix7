@extends('layouts.app')

@section('title', ($personal ? 'Meu trabalho' : 'Produção da equipe').' · Plataforma Mix7')

@section('body')
<div class="shell">
    @include('layouts.navigation', ['active' => 'team'])
    <main class="main">
        @include('layouts.topbar')
        <div class="content">
            <p class="eyebrow">{{ $personal ? 'Minha rotina' : 'Acompanhamento da equipe' }}</p>
            <h1 class="heading">{{ $personal ? 'Meu trabalho' : 'Produção da equipe' }}</h1>
            <p class="subheading">{{ $personal ? 'Veja suas tarefas ativas e o tempo que você registrou.' : 'Acompanhe tarefas atribuídas e tempo registrado por profissional.' }}</p>
            @include('partials.team-tabs', ['teamView' => 'activity'])

            @if (! $personal)
                <section class="team-now" aria-labelledby="team-now-title" data-team-now data-url="{{ route('team.activity.now') }}">
                    <div class="team-now-heading"><div><h2 id="team-now-title">Em que cada profissional está comprometido</h2><p>Veja todas as tarefas abertas por pessoa. “Cronômetro ativo” indica trabalho com tempo sendo registrado agora. Atualiza a cada 30 segundos.</p></div><span data-team-now-updated>Carregando…</span></div>
                    <div class="team-now-grid" data-team-now-list aria-live="polite"><p>Consultando o estado atual da equipe…</p></div>
                    <p class="team-now-error" data-team-now-error hidden>Não foi possível atualizar agora. A tela tentará novamente.</p>
                </section>
                <script>
                    (() => {
                        const panel = document.querySelector('[data-team-now]');
                        if (!panel) return;
                        const list = panel.querySelector('[data-team-now-list]');
                        const updated = panel.querySelector('[data-team-now-updated]');
                        const error = panel.querySelector('[data-team-now-error]');
                        const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (character) => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[character]));
                        const refresh = async () => {
                            if (document.hidden) return;
                            try {
                                const response = await fetch(panel.dataset.url, {headers: {'Accept': 'application/json'}, credentials: 'same-origin'});
                                if (!response.ok) throw new Error('Atualização indisponível');
                                const payload = await response.json();
                                list.innerHTML = payload.data.professionals.map((person) => {
                                    const commitments = person.commitments.length ? person.commitments.map((task) => {
                                        const details = [task.planned_due_on ? `prazo ${new Date(`${task.planned_due_on}T12:00:00`).toLocaleDateString('pt-BR')}` : null, Number.isInteger(task.estimate_minutes) ? `${task.estimate_minutes} min previstos` : null, task.timer_started_at ? `cronômetro iniciado às ${new Date(task.timer_started_at).toLocaleTimeString('pt-BR', {hour:'2-digit', minute:'2-digit'})}` : null].filter(Boolean).join(' · ');
                                        return `<li><span class="team-now-state ${task.status === 'blocked' ? 'is-blocked' : ''}">${escapeHtml(task.status_label)}${task.timer_running ? ' · cronômetro ativo' : ''}</span><strong>${escapeHtml(task.task)}</strong><small>${escapeHtml(task.demand || 'Demanda sem título')}${details ? ` · ${escapeHtml(details)}` : ''}</small></li>`;
                                    }).join('') : '<li class="team-now-empty">Sem tarefas abertas atribuídas.</li>';
                                    return `<article class="team-now-person"><h3>${escapeHtml(person.professional)}${person.active ? '' : ' · acesso desativado'}<small class="team-now-count">${person.commitments.length} ${person.commitments.length === 1 ? 'compromisso aberto' : 'compromissos abertos'}</small></h3><ul>${commitments}</ul></article>`;
                                }).join('') || '<p>Nenhum profissional encontrado.</p>';
                                updated.textContent = `Atualizado às ${new Date(payload.data.refreshed_at).toLocaleTimeString('pt-BR', {hour:'2-digit', minute:'2-digit', second:'2-digit'})}`;
                                error.hidden = true;
                            } catch {
                                error.hidden = false;
                            }
                        };
                        refresh();
                        window.setInterval(refresh, 30000);
                        document.addEventListener('visibilitychange', refresh);
                    })();
                </script>
            @endif

            @if ($personal && $activeEntry)
                <section class="activity-timer" aria-live="polite">
                    <div><span class="eyebrow">Cronômetro em andamento</span><strong>{{ $activeEntry->task->title }}</strong><span>O tempo continua sendo registrado nesta tarefa.</span><span>Se o navegador fechou sem pausar, encerre agora para não continuar contando. O tempo anterior permanece no registro e pode precisar de revisão.</span></div>
                    <div class="timer-recovery-actions"><form method="post" action="{{ route('demand-tasks.timer.pause', $activeEntry->task) }}">@csrf<button class="secondary-button" type="submit">Pausar normalmente</button></form><form method="post" action="{{ route('demand-tasks.timer.recover') }}">@csrf<button class="secondary-button" type="submit">Recuperar e encerrar agora</button></form></div>
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
                        <div class="activity-person"><span class="avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr($row['user']->name, 0, 1)) }}</span><div><h2>{{ $personal ? 'Sua atividade' : $row['user']->name }} @unless ($row['is_active'])<span class="pill pill-blocked">Acesso desativado</span>@endunless</h2><span>{{ $row['open'] }} {{ $row['open'] === 1 ? 'tarefa aberta' : 'tarefas abertas' }}</span>@unless ($personal)<a href="{{ route('team.capacity', ['professional_id' => $row['user']->id]) }}">Ver disponibilidade e carga desta pessoa</a>@endunless</div></div>
                        <div class="activity-statuses" aria-label="Tarefas abertas por etapa">
                            <span><strong>{{ $row['todo'] }}</strong> A fazer</span><span><strong>{{ $row['in_progress'] }}</strong> Em andamento</span><span><strong>{{ $row['paused'] }}</strong> Pausadas</span><span><strong>{{ $row['blocked'] }}</strong> Impedidas</span>
                        </div>
                        <div class="activity-measures"><div><strong>{{ $row['completed_30d'] }}</strong><span>concluídas em 30 dias</span></div><div><strong>{{ $hours }} h</strong><span>registradas em 30 dias</span></div><div><strong>{{ $estimateHours }} h</strong><span>estimadas nas tarefas abertas</span></div></div>
                        <details class="activity-weekly-trend">
                            <summary>Ver evolução semanal</summary>
                            <p>Semanas dentro do período móvel de 30 dias. A primeira e a última podem ser parciais. Os números mostram registros, sem nota ou comparação entre pessoas.</p>
                            <div class="table-scroll"><table>
                                <thead><tr><th>Semana</th><th>Concluídas</th><th>Tempo registrado</th></tr></thead>
                                <tbody>
                                    @foreach ($row['weekly_trend'] as $week)
                                        <tr><th>{{ $week['week'] }} @if ($week['partial'])<span class="pill">parcial</span>@endif</th><td>{{ $week['completed'] }}</td><td>{{ number_format($week['recorded_seconds'] / 3600, 1, ',', '.') }} h</td></tr>
                                    @endforeach
                                </tbody>
                            </table></div>
                        </details>
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
<style>
    .activity-person a{display:inline-block;margin-top:4px;color:#39758b;font-size:11px;font-weight:700;text-decoration:none}.activity-person a:hover{text-decoration:underline}.activity-task-section{display:grid;gap:10px;margin-top:24px}.activity-task{display:flex;align-items:center;justify-content:space-between;gap:16px;padding:14px 16px;background:#fff;border:1px solid #e3e9e8;border-radius:13px}.activity-task-main{display:grid;grid-template-columns:max-content minmax(0,1fr);align-items:center;gap:6px 9px;min-width:0}.activity-task-main>a{color:#204b61;font-size:13px;font-weight:750;line-height:1.4;text-decoration:none;overflow-wrap:anywhere}.activity-task-main>a:hover{text-decoration:underline}.activity-task-main>span:last-child{grid-column:2;color:#718087;font-size:11px}.activity-task-actions{display:flex;flex:none}.activity-task-actions form{margin:0}@media(max-width:650px){.activity-task{align-items:flex-start;flex-direction:column}.activity-task-actions{width:100%}.activity-task-actions button{width:100%}}
    .team-now{margin:0 0 22px;padding:18px;background:#fff;border:1px solid #dce9ec;border-radius:16px}.team-now-heading{display:flex;align-items:flex-start;justify-content:space-between;gap:12px}.team-now-heading h2{margin:0;color:#204b61;font-size:16px}.team-now-heading p{margin:5px 0 0;color:#718087;font-size:11px;line-height:1.5}.team-now-heading>span{color:#718087;font-size:10px;white-space:nowrap}.team-now-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,260px),1fr));gap:10px;margin-top:14px}.team-now-person{min-width:0;padding:12px;border:1px solid #e6edef;border-radius:12px;background:#f9fcfc}.team-now-person h3{margin:0 0 9px;color:#204b61;font-size:13px}.team-now-person ul{display:grid;gap:8px;margin:0;padding:0;list-style:none}.team-now-person li{display:grid;gap:4px;padding-top:8px;border-top:1px solid #e7eeee}.team-now-person li:first-child{padding-top:0;border:0}.team-now-person li strong{font-size:12px;overflow-wrap:anywhere}.team-now-person li small,.team-now-empty{color:#718087;font-size:10px}.team-now-state{width:max-content;max-width:100%;padding:3px 7px;border-radius:99px;background:#e5f4f8;color:#326c82;font-size:9px;font-weight:750}.team-now-state.is-blocked{background:#fff0ed;color:#9a5148}.team-now-error{margin:12px 0 0;color:#9a5148;font-size:11px}@media(max-width:600px){.team-now-heading{flex-direction:column}.team-now-heading>span{white-space:normal}}
</style>
@endsection
