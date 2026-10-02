<header class="topbar">
    @php($unreadNotifications = auth()->user()->unreadNotifications()->count())
    <a class="notification-link" href="{{ route('notifications.index') }}" aria-label="Notificações{{ $unreadNotifications ? ', '.$unreadNotifications.' não lidas' : '' }}">Avisos @if ($unreadNotifications > 0)<span>{{ $unreadNotifications > 99 ? '99+' : $unreadNotifications }}</span>@endif</a>
    <div class="user-chip"><span class="avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr(auth()->user()->name, 0, 1)) }}</span><span>{{ auth()->user()->name }}<br><span style="font-size:11px;color:#819096">{{ auth()->user()->role->label() }}</span></span></div>
    <form method="post" action="{{ route('logout') }}">@csrf<button class="logout" type="submit">Sair</button></form>
</header>
<style>.notification-link{display:inline-flex;align-items:center;gap:6px;padding:8px 10px;border:1px solid #d6e0e1;border-radius:999px;background:#fff;color:#38515b;text-decoration:none;font-size:11px;font-weight:700}.notification-link span{display:grid;place-items:center;min-width:18px;height:18px;padding:0 4px;border-radius:99px;background:#204b61;color:#fff;font-size:9px}</style>
@include('layouts.task-tray')
