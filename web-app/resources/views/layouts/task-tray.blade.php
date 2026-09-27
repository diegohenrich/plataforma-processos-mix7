@if ($taskTrayTasks->isNotEmpty() || $taskTrayActiveEntry)
    <aside class="task-tray" aria-label="Cronômetro e tarefas atribuídas">
        <details class="task-tray-details">
            <summary>
                <span class="task-tray-indicator" aria-hidden="true"></span>
                <span class="task-tray-summary"><strong>Minhas tarefas</strong><small>{{ $taskTrayHasMore ? '20+' : $taskTrayTasks->count() }} em aberto</small></span>
                @if ($taskTrayActiveEntry)
                    <span class="task-tray-current"><strong data-task-tray-timer data-started-at="{{ $taskTrayActiveEntry->started_at->toISOString() }}" data-elapsed-seconds="{{ $taskTrayElapsedSeconds }}" aria-label="Cronômetro ativo na tarefa {{ $taskTrayActiveEntry->task->title }}">{{ gmdate('H:i:s', $taskTrayElapsedSeconds) }}</strong><small>{{ $taskTrayActiveEntry->task->title }}</small></span>
                @else
                    <span class="task-tray-prompt">Escolha uma tarefa para iniciar</span>
                @endif
                <span class="task-tray-chevron" aria-hidden="true">⌃</span>
            </summary>
            <div class="task-tray-content">
                <p>Escolha uma tarefa atribuída a você. O tempo será salvo na tarefa selecionada.</p>
                <ul class="task-tray-list">
                    @foreach ($taskTrayTasks as $task)
                        @php($isActive = $taskTrayActiveEntry?->task_id === $task->id)
                        @php($blockedByDependency = $task->dependencies->contains(fn ($dependency) => $dependency->status !== App\Enums\TaskStatus::Completed))
                        <li>
                            <a href="{{ route('demands.show', $task->demand) }}"><strong>{{ $task->title }}</strong><small>{{ $task->demand->title }} · {{ $task->status->label() }}</small></a>
                            @if ($isActive)
                                <form method="post" action="{{ route('demand-tasks.timer.pause', $task) }}">@csrf<button class="task-tray-action is-pause" type="submit">Pausar</button></form>
                            @elseif ($taskTrayActiveEntry)
                                <button class="task-tray-action" type="button" disabled title="Pause a tarefa atual antes de iniciar outra">Aguarde</button>
                            @elseif ($blockedByDependency)
                                <button class="task-tray-action" type="button" disabled title="Conclua as tarefas anteriores primeiro">Bloqueada</button>
                            @else
                                <form method="post" action="{{ route('demand-tasks.timer.start', $task) }}">@csrf<button class="task-tray-action is-start" type="submit">Iniciar</button></form>
                            @endif
                            @if (in_array(App\Enums\TaskStatus::Completed, $task->status->next(), true))
                                <form method="post" action="{{ route('demand-tasks.status', $task) }}">@csrf @method('PATCH')<input type="hidden" name="status" value="{{ App\Enums\TaskStatus::Completed->value }}"><button class="task-tray-action is-complete" type="submit" aria-label="Concluir tarefa {{ $task->title }}">Concluir</button></form>
                            @endif
                        </li>
                    @endforeach
                </ul>
                @if ($taskTrayHasMore)<p class="task-tray-more">Há mais tarefas atribuídas. <a href="{{ route('demand-tasks.board') }}">Abrir o quadro completo</a>.</p>@else<a class="task-tray-board-link" href="{{ route('demand-tasks.board') }}">Abrir meu quadro de tarefas</a>@endif
            </div>
        </details>
        @if ($taskTrayActiveEntry)
            <form method="post" action="{{ route('demand-tasks.timer.pause', $taskTrayActiveEntry->task) }}">@csrf<button class="task-tray-quick-pause" type="submit" aria-label="Pausar cronômetro de {{ $taskTrayActiveEntry->task->title }}">Pausar</button></form>
        @endif
    </aside>
    <style>
        body{padding-bottom:92px}.task-tray{position:fixed;right:18px;bottom:16px;z-index:1000;display:flex;align-items:flex-end;gap:8px;width:min(440px,calc(100vw - 32px));font-family:inherit}.task-tray-details{min-width:0;flex:1;background:#fff;border:1px solid #d7e9e9;border-radius:15px;box-shadow:0 12px 38px #204b6126}.task-tray-details[open]{max-height:min(72vh,620px);display:flex;flex-direction:column}.task-tray-details summary{display:flex;align-items:center;gap:10px;min-height:70px;padding:11px 13px;list-style:none;cursor:pointer}.task-tray-details summary::-webkit-details-marker{display:none}.task-tray-indicator{flex:0 0 9px;width:9px;height:9px;border-radius:50%;background:{{ $taskTrayActiveEntry ? '#3c9b73' : '#8ecde2' }};box-shadow:0 0 0 4px {{ $taskTrayActiveEntry ? '#3c9b731a' : '#8ecde21a' }}}.task-tray-summary,.task-tray-current{display:grid;gap:3px;min-width:0}.task-tray-summary strong{color:#204b61;font-size:12px}.task-tray-summary small,.task-tray-current small{overflow:hidden;color:#718087;font-size:10px;text-overflow:ellipsis;white-space:nowrap}.task-tray-current{margin-left:auto;text-align:right}.task-tray-current strong{font-variant-numeric:tabular-nums;color:#204b61;font-size:15px}.task-tray-prompt{margin-left:auto;color:#718087;font-size:11px}.task-tray-chevron{color:#718087;font-size:17px}.task-tray-details[open] .task-tray-chevron{transform:rotate(180deg)}.task-tray-content{min-height:0;overflow:auto;border-top:1px solid #edf0ef;padding:12px 14px}.task-tray-content>p,.task-tray-more{margin:0 0 10px;color:#718087;font-size:11px;line-height:1.45}.task-tray-list{display:grid;gap:7px;list-style:none;padding:0;margin:0}.task-tray-list li{display:flex;align-items:center;gap:10px;padding:9px 0;border-top:1px solid #f0f3f2}.task-tray-list li>a{display:grid;gap:3px;min-width:0;flex:1;color:inherit;text-decoration:none}.task-tray-list li>a strong{overflow:hidden;color:#304952;font-size:12px;text-overflow:ellipsis;white-space:nowrap}.task-tray-list li>a small{overflow:hidden;color:#819096;font-size:10px;text-overflow:ellipsis;white-space:nowrap}.task-tray-action,.task-tray-quick-pause{min-height:36px;padding:7px 11px;border:1px solid #d6e0e1;border-radius:9px;background:#fff;color:#38515b;font:inherit;font-size:11px;font-weight:700;cursor:pointer}.task-tray-action.is-start,.task-tray-quick-pause{border-color:#204b61;background:#204b61;color:#fff}.task-tray-action.is-pause{border-color:#d6e0e1;background:#f4fbfc;color:#204b61}.task-tray-action:disabled{opacity:.55;cursor:not-allowed}.task-tray-quick-pause{flex:0 0 auto;min-height:48px;padding:8px 13px;box-shadow:0 12px 38px #204b6126}.task-tray-board-link,.task-tray-more a{color:#326c82;font-size:11px;font-weight:700}.task-tray-board-link{display:inline-block;margin-top:11px}.task-tray-more{margin:11px 0 0}@media(max-width:650px){body{padding-bottom:88px}.task-tray{right:10px;bottom:10px;width:calc(100vw - 20px)}.task-tray-details summary{min-height:64px;padding:9px 11px}.task-tray-prompt{max-width:105px;text-align:right}.task-tray-quick-pause{min-height:44px;padding:7px 11px}}
        .task-tray-list li>form{flex:0 0 auto}.task-tray-action.is-complete{border-color:#d5e7df;background:#eff8f3;color:#27684c}
    </style>
    @if ($taskTrayActiveEntry)
        <script>
            (() => {
                const timer = document.querySelector('[data-task-tray-timer]');
                if (!timer) return;
                const heartbeatUrl = @json(route('demand-tasks.timer.heartbeat'));
                const heartbeatInterval = @json(\App\Services\TaskTimerHeartbeat::INTERVAL_SECONDS * 1000);
                const csrfToken = @json(csrf_token());
                const sendHeartbeat = async () => {
                    try {
                        const response = await fetch(heartbeatUrl, {method:'POST', credentials:'same-origin', headers:{'Accept':'application/json','X-CSRF-TOKEN':csrfToken}});
                        const result = await response.json();
                        if (response.ok && result.data?.active === false) window.location.reload();
                    } catch (_) { /* A brief network interruption is tolerated; stale sessions close on the next request. */ }
                };
                sendHeartbeat();
                window.setInterval(sendHeartbeat, heartbeatInterval);
                const base = Number(timer.dataset.elapsedSeconds);
                const started = Date.parse(timer.dataset.startedAt);
                const render = () => {
                    const seconds = base + Math.max(0, Math.floor((Date.now() - started) / 1000));
                    const hours = String(Math.floor(seconds / 3600)).padStart(2, '0');
                    const minutes = String(Math.floor((seconds % 3600) / 60)).padStart(2, '0');
                    const remainder = String(seconds % 60).padStart(2, '0');
                    timer.textContent = `${hours}:${minutes}:${remainder}`;
                };
                render();
                window.setInterval(render, 1000);
            })();
        </script>
    @endif
@endif
