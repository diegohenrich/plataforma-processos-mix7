<header class="topbar">
    @php($activeEntry = auth()->user()->activeTimeEntry()->with('task.demand')->first())
    @if ($activeEntry)
        @php($elapsedSeconds = $activeEntry->task->timeEntries()->whereNotNull('ended_at')->get()->sum(fn ($entry) => $entry->ended_at->getTimestamp() - $entry->started_at->getTimestamp()))
        <a class="active-timer" href="{{ route('demands.show', $activeEntry->task->demand) }}" aria-label="Cronômetro ativo na tarefa {{ $activeEntry->task->title }}">
            <span class="timer-indicator" aria-hidden="true"></span><span><strong data-timer data-started-at="{{ $activeEntry->started_at->toISOString() }}" data-elapsed-seconds="{{ $elapsedSeconds }}">{{ gmdate('H:i:s', $elapsedSeconds) }}</strong><small>{{ $activeEntry->task->title }}</small></span>
        </a>
    @endif
    <div class="user-chip"><span class="avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr(auth()->user()->name, 0, 1)) }}</span><span>{{ auth()->user()->name }}<br><span style="font-size:11px;color:#819096">{{ auth()->user()->role->label() }}</span></span></div>
    <form method="post" action="{{ route('logout') }}">@csrf<button class="logout" type="submit">Sair</button></form>
</header>
@if ($activeEntry)
<script>
    (() => {
        const timer = document.querySelector('[data-timer]');
        if (!timer) return;
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
