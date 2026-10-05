@if ($taskTrayTasks->isNotEmpty() || $taskTrayActiveEntry)
    <aside class="task-tray" aria-label="Cronômetro e tarefas atribuídas" data-task-tray-floating>
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
                        @php
                            $isActive = $taskTrayActiveEntry?->task_id === $task->id;
                            $blockedByDependency = $task->dependencies->contains(fn ($dependency) => $dependency->status !== App\Enums\TaskStatus::Completed);
                        @endphp
                        <li>
                            <a href="{{ route('demands.show', $task->demand) }}"><strong>{{ $task->title }}</strong><small>{{ $task->demand->title }} · {{ $task->status->label() }}</small>@if($task->currentAssignment && ! $task->currentAssignment->accepted_at && $task->status === App\Enums\TaskStatus::Todo)<small class="task-tray-waiting" data-assignment-age data-waiting-since="{{ $task->currentAssignment->assigned_at->toISOString() }}">Na sua fila há {{ \Carbon\CarbonInterval::seconds((int) $task->currentAssignment->assigned_at->diffInSeconds(now()))->cascade()->forHumans(['parts' => 2, 'join' => ' e ', 'locale' => 'pt_BR']) }}. Inicie o cronômetro ou avise a gestão se estiver impedido.</small>@endif</a>
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
        <button class="task-tray-floating-open" type="button" data-task-tray-floating-open title="Abre uma janela separada para manter tarefas e cronômetro visíveis enquanto o CRM está minimizado." aria-label="Manter tarefas visíveis ao minimizar o CRM">Manter visível</button>
        <p class="task-tray-floating-status" data-task-tray-floating-status aria-live="polite"></p>
    </aside>
    @php
        $floatingTasks = $taskTrayTasks->map(function (\App\Models\DemandTask $task) use ($taskTrayActiveEntry): array {
            $blocked = $task->dependencies->contains(fn ($dependency) => $dependency->status !== App\Enums\TaskStatus::Completed);

            return [
                'id' => $task->id,
                'title' => $task->title,
                'demandTitle' => $task->demand->title,
                'demandUrl' => route('demands.show', $task->demand),
                'statusLabel' => $task->status->label(),
                'waitingSince' => $task->currentAssignment && ! $task->currentAssignment->accepted_at && $task->status === App\Enums\TaskStatus::Todo ? $task->currentAssignment->assigned_at->toISOString() : null,
                'canStart' => $taskTrayActiveEntry === null && ! $blocked && $task->status !== App\Enums\TaskStatus::Completed,
                'blocked' => $blocked,
                'canComplete' => in_array(App\Enums\TaskStatus::Completed, $task->status->next(), true),
                'startUrl' => route('demand-tasks.timer.start', $task),
                'pauseUrl' => route('demand-tasks.timer.pause', $task),
                'completeUrl' => route('demand-tasks.status', $task),
            ];
        })->values();
        $floatingState = [
            'tasks' => $floatingTasks,
            'active' => $taskTrayActiveEntry ? [
                'taskId' => $taskTrayActiveEntry->task_id,
                'startedAt' => $taskTrayActiveEntry->started_at->toISOString(),
                'elapsedSeconds' => $taskTrayElapsedSeconds,
            ] : null,
            'csrfToken' => csrf_token(),
            'heartbeatUrl' => route('demand-tasks.timer.heartbeat'),
            'boardUrl' => route('demand-tasks.board'),
        ];
    @endphp
    <script type="application/json" data-task-tray-floating-state>{!! json_encode($floatingState, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) !!}</script>
    <style>
        body{padding-bottom:92px}.task-tray{position:fixed;right:18px;bottom:16px;z-index:1000;display:flex;align-items:flex-end;gap:8px;width:min(440px,calc(100vw - 32px));font-family:inherit}.task-tray-details{min-width:0;flex:1;background:#fff;border:1px solid #d7e9e9;border-radius:15px;box-shadow:0 12px 38px #204b6126}.task-tray-details[open]{max-height:min(72vh,620px);display:flex;flex-direction:column}.task-tray-details summary{display:flex;align-items:center;gap:10px;min-height:70px;padding:11px 13px;list-style:none;cursor:pointer}.task-tray-details summary::-webkit-details-marker{display:none}.task-tray-indicator{flex:0 0 9px;width:9px;height:9px;border-radius:50%;background:{{ $taskTrayActiveEntry ? '#3c9b73' : '#8ecde2' }};box-shadow:0 0 0 4px {{ $taskTrayActiveEntry ? '#3c9b731a' : '#8ecde21a' }}}.task-tray-summary,.task-tray-current{display:grid;gap:3px;min-width:0}.task-tray-summary strong{color:#204b61;font-size:12px}.task-tray-summary small,.task-tray-current small{overflow:hidden;color:#718087;font-size:10px;text-overflow:ellipsis;white-space:nowrap}.task-tray-current{margin-left:auto;text-align:right}.task-tray-current strong{font-variant-numeric:tabular-nums;color:#204b61;font-size:15px}.task-tray-prompt{margin-left:auto;color:#718087;font-size:11px}.task-tray-chevron{color:#718087;font-size:17px}.task-tray-details[open] .task-tray-chevron{transform:rotate(180deg)}.task-tray-content{min-height:0;overflow:auto;border-top:1px solid #edf0ef;padding:12px 14px}.task-tray-content>p,.task-tray-more{margin:0 0 10px;color:#718087;font-size:11px;line-height:1.45}.task-tray-list{display:grid;gap:7px;list-style:none;padding:0;margin:0}.task-tray-list li{display:flex;align-items:center;gap:10px;padding:9px 0;border-top:1px solid #f0f3f2}.task-tray-list li>a{display:grid;gap:3px;min-width:0;flex:1;color:inherit;text-decoration:none}.task-tray-list li>a strong{overflow:hidden;color:#304952;font-size:12px;text-overflow:ellipsis;white-space:nowrap}.task-tray-list li>a small{overflow:hidden;color:#819096;font-size:10px;text-overflow:ellipsis;white-space:nowrap}.task-tray-action,.task-tray-quick-pause,.task-tray-floating-open{min-height:36px;padding:7px 11px;border:1px solid #d6e0e1;border-radius:9px;background:#fff;color:#38515b;font:inherit;font-size:11px;font-weight:700;cursor:pointer}.task-tray-action.is-start,.task-tray-quick-pause,.task-tray-floating-open{border-color:#204b61;background:#204b61;color:#fff}.task-tray-action.is-pause{border-color:#d6e0e1;background:#f4fbfc;color:#204b61}.task-tray-action:disabled,.task-tray-floating-open:disabled{opacity:.55;cursor:not-allowed}.task-tray-quick-pause{flex:0 0 auto;min-height:48px;padding:8px 13px;box-shadow:0 12px 38px #204b6126}.task-tray-floating-open{flex:0 0 auto;min-height:48px;max-width:104px;padding:7px 10px;white-space:normal}.task-tray-floating-status{display:none}.task-tray-floating-status.is-visible{position:absolute;right:0;bottom:calc(100% + 8px);display:block;width:min(320px,calc(100vw - 32px));margin:0;padding:9px 11px;border:1px solid #cfe2e5;border-radius:10px;background:#fff;color:#38515b;font-size:11px;line-height:1.4;box-shadow:0 8px 24px #204b6117}.task-tray-board-link,.task-tray-more a{color:#326c82;font-size:11px;font-weight:700}.task-tray-board-link{display:inline-block;margin-top:11px}.task-tray-more{margin:11px 0 0}@media(max-width:650px){body{padding-bottom:88px}.task-tray{right:10px;bottom:10px;width:calc(100vw - 20px)}.task-tray-details summary{min-height:64px;padding:9px 11px}.task-tray-prompt{max-width:105px;text-align:right}.task-tray-quick-pause,.task-tray-floating-open{min-height:44px;padding:7px 9px}.task-tray-floating-open{max-width:82px;font-size:10px}}
        .task-tray-list li>form{flex:0 0 auto}.task-tray-action.is-complete{border-color:#d5e7df;background:#eff8f3;color:#27684c}.task-tray-list li>a small.task-tray-waiting{height:auto;overflow:visible;color:#945b12;font-weight:800;text-overflow:clip;white-space:normal}
    </style>
    @vite('resources/js/task-tray-floating.js')
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
    <script>
        (() => {
            const renderAges = () => document.querySelectorAll('[data-assignment-age]').forEach((element) => {
                const seconds = Math.max(0, Math.floor((Date.now() - Date.parse(element.dataset.waitingSince)) / 1000));
                const days = Math.floor(seconds / 86400);
                const hours = Math.floor((seconds % 86400) / 3600);
                const minutes = Math.floor((seconds % 3600) / 60);
                const ageParts = [];
                if (days) ageParts.push(`${days} ${days === 1 ? 'dia' : 'dias'}`);
                if (hours) ageParts.push(`${hours} ${hours === 1 ? 'hora' : 'horas'}`);
                if (!days && minutes) ageParts.push(`${minutes} ${minutes === 1 ? 'minuto' : 'minutos'}`);
                if (!ageParts.length) ageParts.push('1 minuto');
                const age = ageParts.length > 1 ? `${ageParts.slice(0, -1).join(', ')} e ${ageParts.at(-1)}` : ageParts[0];
                element.textContent = `Na sua fila há ${age}. Inicie o cronômetro ou avise a gestão se estiver impedido.`;
            });
            renderAges();
            window.setInterval(renderAges, 60000);
        })();
    </script>
@endif
