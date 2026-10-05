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
                        const formatWaiting = (seconds) => {
                            const value = Math.max(0, Number(seconds) || 0);
                            const days = Math.floor(value / 86400);
                            const hours = Math.floor((value % 86400) / 3600);
                            const minutes = Math.floor((value % 3600) / 60);
                            const parts = [];
                            if (days) parts.push(`${days} ${days === 1 ? 'dia' : 'dias'}`);
                            if (hours) parts.push(`${hours} ${hours === 1 ? 'hora' : 'horas'}`);
                            if (!days && minutes) parts.push(`${minutes} ${minutes === 1 ? 'minuto' : 'minutos'}`);
                            if (!parts.length) parts.push('1 minuto');
                            return parts.length > 1 ? `${parts.slice(0, -1).join(', ')} e ${parts.at(-1)}` : parts[0];
                        };
                        const refresh = async () => {
                            if (document.hidden) return;
                            try {
                                const response = await fetch(panel.dataset.url, {headers: {'Accept': 'application/json'}, credentials: 'same-origin'});
                                if (!response.ok) throw new Error('Atualização indisponível');
                                const payload = await response.json();
                                list.innerHTML = payload.data.professionals.map((person) => {
                                    const commitments = person.commitments.length ? person.commitments.map((task) => {
                                        const details = [task.waiting_seconds !== null ? `aguardando início há ${formatWaiting(task.waiting_seconds)}` : null, task.planned_due_on ? `prazo ${new Date(`${task.planned_due_on}T12:00:00`).toLocaleDateString('pt-BR')}` : null, Number.isInteger(task.estimate_minutes) ? `${task.estimate_minutes} min previstos` : null, task.timer_started_at ? `cronômetro iniciado às ${new Date(task.timer_started_at).toLocaleTimeString('pt-BR', {hour:'2-digit', minute:'2-digit'})}` : null].filter(Boolean).join(' · ');
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
                <span>{{ $periodStart->format('d/m/Y') }} a {{ $periodEnd->format('d/m/Y') }}. Os tempos médios de aceite e execução consideram tarefas iniciadas/concluídas nesse intervalo; “execução” vai do primeiro início até a conclusão. Horas cronometradas continuam separadas e incluem timer ativo.</span>
            </section>

            <section class="monthly-history" aria-labelledby="monthly-history-title">
                <div class="section-heading"><div><h2 id="monthly-history-title">{{ $personal ? 'Meu mês a mês' : 'Desempenho da equipe por mês' }}</h2><p>Histórico desde o primeiro registro. As tarefas concluídas e avaliações ficam no mês de conclusão; horas ficam no mês em que foram registradas. O mês atual ainda está aberto.</p></div></div>
                @if ($personal)
                    @php($currentMonth = $monthlyHistory->firstWhere('current', true))
                    @php($myMonth = $currentMonth['rows']->first() ?? null)
                    <article class="monthly-encouragement @if ($myMonth && $myMonth['top_quartile']) monthly-encouragement-highlight @endif"><span aria-hidden="true">✦</span><div><strong>Seu mês, {{ now()->format('m/Y') }}</strong><p>@if ($myMonth && $myMonth['top_quartile'])Você está entre os profissionais com melhor avaliação neste mês. Continue assim!@elseif ($myMonth && $myMonth['scored_tasks'] >= 3)Seu mês está sendo acompanhado. Continue registrando suas entregas e avaliações.@elseif ($myMonth && $myMonth['scored_tasks'] > 0)Você tem {{ $myMonth['scored_tasks'] }} {{ $myMonth['scored_tasks'] === 1 ? 'tarefa avaliada' : 'tarefas avaliadas' }}. A mensagem de destaque aparece após 3 avaliações e quando houver base suficiente para comparação.@else Sua pontuação aparece aqui conforme suas tarefas forem avaliadas.@endif</p>@if ($myMonth && $myMonth['monthly_score'] !== null)<small class="monthly-score-detail">Nota média: {{ number_format($myMonth['monthly_score'], 1, ',', '.') }}/10 em {{ $myMonth['scored_tasks'] }} {{ $myMonth['scored_tasks'] === 1 ? 'tarefa avaliada' : 'tarefas avaliadas' }}.</small>@endif</div><div class="monthly-encouragement-count">{{ $myMonth['completed'] ?? 0 }}<small>{{ ($myMonth['completed'] ?? 0) === 1 ? 'tarefa concluída' : 'tarefas concluídas' }}</small></div></article>
                @endif
                <div class="monthly-history-list">
                    @foreach ($monthlyHistory as $month)
                        <details class="monthly-history-month" @if ($month['current']) open @endif>
                            <summary><strong>{{ $month['label'] }}</strong>@if ($month['current'])<span class="pill">Em andamento</span>@endif</summary>
                            <div class="table-scroll"><table>
                                <thead><tr><th>{{ $personal ? 'Resumo' : 'Profissional' }}</th><th>Concluídas</th><th>Avaliações</th><th>Nota média (máx. 10)</th><th>Horas registradas</th><th>Média até iniciar</th><th>Média até concluir</th></tr></thead>
                                <tbody>
                                    @foreach ($month['rows'] as $row)
                                        <tr><th>{{ $personal ? 'Você' : $row['user']->name }} @if (! $personal && $row['top_quartile'])<span class="monthly-top-badge">Destaque do mês</span>@endif</th><td>{{ $row['completed'] }}</td><td>{{ $row['reviews'] }}</td><td>@if ($row['monthly_score'] !== null){{ number_format($row['monthly_score'], 1, ',', '.') }}/10 <small>({{ $row['scored_tasks'] }} {{ $row['scored_tasks'] === 1 ? 'tarefa' : 'tarefas' }})</small>@else <span class="monthly-no-score">Sem nota numérica</span>@endif</td><td>{{ number_format($row['recorded_seconds'] / 3600, 1, ',', '.') }} h</td><td>{{ $row['average_acceptance_label'] }}</td><td>{{ $row['average_execution_label'] }}</td></tr>
                                    @endforeach
                                </tbody>
                            </table></div>
                        </details>
                    @endforeach
                </div>
                @unless ($personal)<p class="monthly-history-note">A nota mensal é a média da soma de prazo e qualidade nas tarefas concluídas no mês. Cada avaliação de direção ou gerência vale igualmente 1; quando há mais de uma avaliação da mesma tarefa, primeiro calculamos a média daquela tarefa. O destaque considera profissionais ativos com pelo menos 3 tarefas avaliadas e só aparece quando há ao menos 4 pessoas elegíveis. Avaliações antigas sem notas numéricas continuam no histórico, sem pontuação retroativa. Use os dados como apoio: bonificação é decisão humana.</p>@endunless
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
                        <div class="activity-measures"><div><strong>{{ $row['completed_30d'] }}</strong><span>concluídas em 30 dias</span></div><div><strong>{{ $hours }} h</strong><span>registradas no cronômetro em 30 dias</span></div><div><strong>{{ $estimateHours }} h</strong><span>estimadas nas tarefas abertas</span></div><div><strong>{{ $row['average_acceptance_label'] }}</strong><span>média até iniciar (últimos 30 dias)</span></div><div><strong>{{ $row['average_execution_label'] }}</strong><span>média do início à conclusão (últimos 30 dias)</span></div></div>
                        @if($row['pending_start_count'] > 0)
                            <p class="activity-waiting-summary">{{ $row['pending_start_count'] }} {{ $row['pending_start_count'] === 1 ? 'tarefa aguardando início' : 'tarefas aguardando início' }} · a mais antiga há {{ $row['oldest_pending_label'] }}</p>
                        @endif
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
                            <div class="activity-task-main"><span class="pill">{{ $task->status->label() }}</span><a href="{{ route('demands.show', $task->demand) }}"><strong>{{ $task->title }}</strong></a><span>{{ $task->demand->title }}</span>@if($task->currentAssignment && ! $task->currentAssignment->accepted_at && $task->status === App\Enums\TaskStatus::Todo)<span class="activity-task-waiting">Na sua fila há {{ \Carbon\CarbonInterval::seconds((int) $task->currentAssignment->assigned_at->diffInSeconds(now()))->cascade()->forHumans(['parts' => 2, 'join' => ' e ', 'locale' => 'pt_BR']) }}. Inicie o cronômetro ou avise a gestão se estiver impedido.</span>@endif</div>
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

            <p class="footnote">Os indicadores refletem registros e avaliações humanas. A comparação é mensal e não exibe posições para profissionais; bonificações continuam dependendo de análise e decisão da gestão.</p>
        </div>
    </main>
</div>
<style>
    .activity-person a{display:inline-block;margin-top:4px;color:#39758b;font-size:11px;font-weight:700;text-decoration:none}.activity-person a:hover{text-decoration:underline}.activity-task-section{display:grid;gap:10px;margin-top:24px}.activity-task{display:flex;align-items:center;justify-content:space-between;gap:16px;padding:14px 16px;background:#fff;border:1px solid #e3e9e8;border-radius:13px}.activity-task-main{display:grid;grid-template-columns:max-content minmax(0,1fr);align-items:center;gap:6px 9px;min-width:0}.activity-task-main>a{color:#204b61;font-size:13px;font-weight:750;line-height:1.4;text-decoration:none;overflow-wrap:anywhere}.activity-task-main>a:hover{text-decoration:underline}.activity-task-main>span:last-child{grid-column:2;color:#718087;font-size:11px}.activity-task-actions{display:flex;flex:none}.activity-task-actions form{margin:0}@media(max-width:650px){.activity-task{align-items:flex-start;flex-direction:column}.activity-task-actions{width:100%}.activity-task-actions button{width:100%}}
    .activity-waiting-summary,.activity-task-waiting{margin:0;padding:8px 10px;border-radius:9px;background:#fff2d7;color:#81520c!important;font-size:10px!important;font-weight:800;line-height:1.45}.activity-task-main .activity-task-waiting{grid-column:2}
    .team-now{margin:0 0 22px;padding:18px;background:#fff;border:1px solid #dce9ec;border-radius:16px}.team-now-heading{display:flex;align-items:flex-start;justify-content:space-between;gap:12px}.team-now-heading h2{margin:0;color:#204b61;font-size:16px}.team-now-heading p{margin:5px 0 0;color:#718087;font-size:11px;line-height:1.5}.team-now-heading>span{color:#718087;font-size:10px;white-space:nowrap}.team-now-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,260px),1fr));gap:10px;margin-top:14px}.team-now-person{min-width:0;padding:12px;border:1px solid #e6edef;border-radius:12px;background:#f9fcfc}.team-now-person h3{margin:0 0 9px;color:#204b61;font-size:13px}.team-now-person ul{display:grid;gap:8px;margin:0;padding:0;list-style:none}.team-now-person li{display:grid;gap:4px;padding-top:8px;border-top:1px solid #e7eeee}.team-now-person li:first-child{padding-top:0;border:0}.team-now-person li strong{font-size:12px;overflow-wrap:anywhere}.team-now-person li small,.team-now-empty{color:#718087;font-size:10px}.team-now-state{width:max-content;max-width:100%;padding:3px 7px;border-radius:99px;background:#e5f4f8;color:#326c82;font-size:9px;font-weight:750}.team-now-state.is-blocked{background:#fff0ed;color:#9a5148}.team-now-error{margin:12px 0 0;color:#9a5148;font-size:11px}@media(max-width:600px){.team-now-heading{flex-direction:column}.team-now-heading>span{white-space:normal}}
    .monthly-history{display:grid;gap:12px;margin:18px 0 24px}.monthly-history .section-heading{margin:0}.monthly-history .section-heading h2{margin:0;color:#204b61;font-size:17px}.monthly-history .section-heading p{margin:5px 0 0;color:#718087;font-size:11px;line-height:1.5}.monthly-encouragement{display:flex;align-items:center;gap:12px;padding:15px 17px;border:1px solid #dbeaf0;border-radius:14px;background:linear-gradient(110deg,#f3fbfd,#fff)}.monthly-encouragement>span{display:grid;place-items:center;flex:none;width:38px;height:38px;border-radius:12px;background:#dff2f7;color:#39758b;font-size:20px}.monthly-encouragement>div:nth-child(2){min-width:0;flex:1}.monthly-encouragement strong{color:#204b61;font-size:13px}.monthly-encouragement p{margin:4px 0 0;color:#64777d;font-size:11px;line-height:1.45}.monthly-encouragement-count{color:#204b61;font-size:22px;font-weight:800;text-align:right}.monthly-encouragement-count small{display:block;color:#718087;font-size:9px;font-weight:600}.monthly-history-list{display:grid;gap:8px}.monthly-history-month{overflow:hidden;border:1px solid #e1e9e9;border-radius:12px;background:#fff}.monthly-history-month summary{display:flex;align-items:center;gap:9px;padding:12px 14px;color:#204b61;font-size:12px;cursor:pointer;list-style:none}.monthly-history-month summary::-webkit-details-marker{display:none}.monthly-history-month[open] summary{border-bottom:1px solid #e8eeee}.monthly-history-month .table-scroll{margin:0;padding:0 12px}.monthly-history-month table{min-width:720px}.monthly-history-month th,.monthly-history-month td{padding:9px 8px;text-align:left;border-bottom:1px solid #edf1f1;font-size:10px;white-space:nowrap}.monthly-history-month tbody tr:last-child th,.monthly-history-month tbody tr:last-child td{border-bottom:0}.monthly-history-note{margin:0;color:#718087;font-size:10px;line-height:1.5}
</style>
<style>
    .monthly-encouragement-highlight{border-color:#b9deea;box-shadow:0 0 0 2px #eaf7fb;animation:monthly-glow 2.8s ease-in-out infinite}.monthly-encouragement-highlight>span{background:#ccecf5;animation:monthly-sparkle 1.8s ease-in-out infinite}.monthly-score-detail{display:block;margin-top:5px;color:#526e77;font-size:10px}.monthly-top-badge{display:inline-block;margin:3px 0 0;padding:3px 6px;border-radius:99px;background:#e6f5f8;color:#326c82;font-size:9px;font-weight:750;white-space:nowrap}.monthly-no-score{color:#829095;font-size:10px}.monthly-history-month table{min-width:760px}.monthly-history-month td small{display:block;color:#718087;font-size:9px}@keyframes monthly-glow{50%{box-shadow:0 0 0 4px #eaf7fb,0 8px 22px #ccecf544}}@keyframes monthly-sparkle{50%{transform:rotate(18deg) scale(1.08)}}@media(prefers-reduced-motion:reduce){.monthly-encouragement-highlight,.monthly-encouragement-highlight>span{animation:none}}
</style>
@endsection
