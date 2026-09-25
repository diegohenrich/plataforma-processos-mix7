<header class="topbar">
    <div class="user-chip"><span class="avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr(auth()->user()->name, 0, 1)) }}</span><span>{{ auth()->user()->name }}<br><span style="font-size:11px;color:#819096">{{ auth()->user()->role->label() }}</span></span></div>
    <form method="post" action="{{ route('logout') }}">@csrf<button class="logout" type="submit">Sair</button></form>
</header>
