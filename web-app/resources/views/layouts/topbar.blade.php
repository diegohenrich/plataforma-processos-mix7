<header class="topbar">
    @php($unreadNotifications = auth()->user()->unreadNotifications()->count())
    @php($activeEntry = auth()->user()->activeTimeEntry()->with('task.demand')->first())
    @if ($activeEntry)
        @php($elapsedSeconds = $activeEntry->task->timeEntries()->whereNotNull('ended_at')->get()->sum(fn ($entry) => $entry->ended_at->getTimestamp() - $entry->started_at->getTimestamp()))
        <a class="active-timer" href="{{ route('demands.show', $activeEntry->task->demand) }}" aria-label="Cronômetro ativo na tarefa {{ $activeEntry->task->title }}">
            <span class="timer-indicator" aria-hidden="true"></span><span><strong data-timer data-started-at="{{ $activeEntry->started_at->toISOString() }}" data-elapsed-seconds="{{ $elapsedSeconds }}">{{ gmdate('H:i:s', $elapsedSeconds) }}</strong><small>{{ $activeEntry->task->title }}</small></span>
        </a>
    @endif
    <a class="notification-link" href="{{ route('notifications.index') }}" aria-label="Notificações{{ $unreadNotifications ? ', '.$unreadNotifications.' não lidas' : '' }}">Avisos @if ($unreadNotifications > 0)<span>{{ $unreadNotifications > 99 ? '99+' : $unreadNotifications }}</span>@endif</a>
    <div class="user-chip"><span class="avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr(auth()->user()->name, 0, 1)) }}</span><span>{{ auth()->user()->name }}<br><span style="font-size:11px;color:#819096">{{ auth()->user()->role->label() }}</span></span></div>
    <form method="post" action="{{ route('logout') }}">@csrf<button class="logout" type="submit">Sair</button></form>
</header>
<style>.notification-link{display:inline-flex;align-items:center;gap:6px;padding:8px 10px;border:1px solid #d6e0e1;border-radius:999px;background:#fff;color:#38515b;text-decoration:none;font-size:11px;font-weight:700}.notification-link span{display:grid;place-items:center;min-width:18px;height:18px;padding:0 4px;border-radius:99px;background:#204b61;color:#fff;font-size:9px}</style>
@if ($activeEntry)
<script>
    (() => {
        const timer = document.querySelector('[data-timer]');
        if (!timer) return;
        const heartbeatUrl = @json(route('demand-tasks.timer.heartbeat'));
        const heartbeatInterval = @json(\App\Services\TaskTimerHeartbeat::INTERVAL_SECONDS * 1000);
        const csrfToken = @json(csrf_token());
        const sendHeartbeat = async () => {
            try {
                const response = await fetch(heartbeatUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken},
                });
                const result = await response.json();
                if (response.ok && result.data?.active === false) window.location.reload();
            } catch (_) {
                // A short network interruption is tolerated; stale sessions close on the next request.
            }
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
