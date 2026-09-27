<style>
    .sidebar .brand{flex-direction:column;gap:12px;padding:8px 10px 26px;margin-bottom:0;border-bottom:1px solid #ffffff14;text-align:center}
    .brand-image{display:block;width:76px;height:76px;flex:none;object-fit:cover;border:1px solid #ffffff32;border-radius:50%;box-shadow:0 8px 18px #00000030}
    .brand-copy{display:grid;gap:6px;min-width:0}
    .brand-copy strong{color:#fff;font-size:15px;line-height:1.4;overflow-wrap:anywhere}
    .brand-copy small{color:#bed4dc;font-size:13px;line-height:1.5;overflow-wrap:anywhere}
    .mobile-nav .brand-image{width:38px;height:38px;border-radius:50%}
    .mobile-nav .brand-copy{display:block;color:#fff;font-size:16px;font-weight:750}
    @media(max-width:650px){.mobile-nav .brand{gap:10px;padding:0}.mobile-nav .brand-copy small{display:none}}
</style>
<aside class="sidebar" aria-label="Navegação principal">
    <div class="brand">
        <img class="brand-image" src="{{ asset('images/mix7-logo-round.png') }}" alt="Logotipo Mix7 Marketing">
        <span class="brand-copy"><strong>{{ auth()->user()->name }}</strong><small>{{ auth()->user()->organization->name }}</small></span>
    </div>
    <div>
        <p class="nav-label">Espaço de trabalho</p>
        <a class="nav-item {{ $active === 'dashboard' ? 'active' : '' }}" href="{{ route('dashboard') }}"><span aria-hidden="true">▦</span> Visão geral</a>
        <a class="nav-item {{ $active === 'demands' ? 'active' : '' }}" href="{{ route('demands.index') }}"><span aria-hidden="true">◷</span> {{ auth()->user()->role === App\Enums\UserRole::Client ? 'Minhas demandas' : 'Demandas' }}</a>
        <a class="nav-item {{ $active === 'notifications' ? 'active' : '' }}" href="{{ route('notifications.index') }}"><span aria-hidden="true">♧</span> Notificações</a>
        @if (auth()->user()->role !== App\Enums\UserRole::Client)
            @if (in_array(auth()->user()->role, [App\Enums\UserRole::AgencyOwner, App\Enums\UserRole::MarketingManager], true))<a class="nav-item {{ $active === 'organization-assistant' ? 'active' : '' }}" href="{{ route('organization-assistant.index') }}"><span aria-hidden="true">✦</span> Assistente da agência</a>@endif
            <a class="nav-item {{ $active === 'task-board' ? 'active' : '' }}" href="{{ route('demand-tasks.board') }}"><span aria-hidden="true">▦</span> Quadro de tarefas</a>
            <a class="nav-item {{ $active === 'approvals' ? 'active' : '' }}" href="{{ route('approvals.index') }}"><span aria-hidden="true">✓</span> Aprovações</a>
            @can('create', App\Models\Demand::class)<a class="nav-item {{ $active === 'approval-modules' ? 'active' : '' }}" href="{{ route('approval-modules.index') }}"><span aria-hidden="true">＋</span> Tipos de aprovação</a>@endcan
            @can('viewActivity', App\Models\User::class)<a class="nav-item {{ $active === 'activity' ? 'active' : '' }}" href="{{ route('team.activity') }}"><span aria-hidden="true">◷</span> {{ auth()->user()->role === App\Enums\UserRole::Professional ? 'Meu trabalho' : 'Produção da equipe' }}</a>@endcan
            <a class="nav-item {{ $active === 'capacity' ? 'active' : '' }}" href="{{ route('team.capacity') }}"><span aria-hidden="true">◫</span> Disponibilidade</a>
            @if (in_array(auth()->user()->role, [App\Enums\UserRole::AgencyOwner, App\Enums\UserRole::MarketingManager, App\Enums\UserRole::Professional], true))<a class="nav-item {{ $active === 'performance-reviews' ? 'active' : '' }}" href="{{ route('performance-reviews.index') }}"><span aria-hidden="true">◎</span> Avaliações</a>@endif
            @can('viewAny', App\Models\User::class)<a class="nav-item {{ $active === 'team' ? 'active' : '' }}" href="{{ route('team.index') }}"><span aria-hidden="true">♧</span> Equipe</a>@endcan
            <a class="nav-item {{ $active === 'service-access' ? 'active' : '' }}" href="{{ route('service-access.index') }}"><span aria-hidden="true">⌑</span> Acessos de serviços</a>
        @endif
        @can('viewAny', App\Models\KnowledgeItem::class)<a class="nav-item {{ $active === 'knowledge' ? 'active' : '' }}" href="{{ route('knowledge.index') }}"><span aria-hidden="true">▤</span> Conhecimento</a>@endcan
        @if (auth()->user()->role !== App\Enums\UserRole::Client)<a class="nav-item {{ $active === 'integrations' ? 'active' : '' }}" href="{{ route('api-tokens.index') }}"><span aria-hidden="true">⌘</span> Acessos da API</a>@endif
    </div>
    <div class="sidebar-note">A plataforma reúne o trabalho da agência e mantém cada etapa registrada.</div>
</aside>
<nav class="mobile-nav" aria-label="Navegação para celular">
    <div class="brand">
        <img class="brand-image" src="{{ asset('images/mix7-logo-round.png') }}" alt="Logotipo Mix7 Marketing">
        <span class="brand-copy">Mix7 <small>| Processos</small></span>
    </div>
    <div class="mobile-nav-actions">
        <a href="{{ route('dashboard') }}">Início</a>
        <a href="{{ route('demands.index') }}">{{ auth()->user()->role === App\Enums\UserRole::Client ? 'Minhas demandas' : 'Demandas' }}</a>
        <a href="{{ route('notifications.index') }}">Notificações</a>
        @if (auth()->user()->role !== App\Enums\UserRole::Client)@if (in_array(auth()->user()->role, [App\Enums\UserRole::AgencyOwner, App\Enums\UserRole::MarketingManager], true))<a href="{{ route('organization-assistant.index') }}">Assistente da agência</a>@endif<a href="{{ route('demand-tasks.board') }}">Quadro de tarefas</a><a href="{{ route('approvals.index') }}">Aprovações</a>@can('create', App\Models\Demand::class)<a href="{{ route('approval-modules.index') }}">Tipos de aprovação</a>@endcan @endif
        @can('viewActivity', App\Models\User::class)<a href="{{ route('team.activity') }}">{{ auth()->user()->role === App\Enums\UserRole::Professional ? 'Meu trabalho' : 'Produção' }}</a>@endcan
        <a href="{{ route('team.capacity') }}">Disponibilidade</a>
        @if (in_array(auth()->user()->role, [App\Enums\UserRole::AgencyOwner, App\Enums\UserRole::MarketingManager, App\Enums\UserRole::Professional], true))<a href="{{ route('performance-reviews.index') }}">Avaliações</a>@endif
        @can('viewAny', App\Models\User::class)<a href="{{ route('team.index') }}">Equipe</a>@endcan
        @if (auth()->user()->role !== App\Enums\UserRole::Client)<a href="{{ route('service-access.index') }}">Acessos de serviços</a>@endif
        @can('viewAny', App\Models\KnowledgeItem::class)<a href="{{ route('knowledge.index') }}">Conhecimento</a>@endcan
        @if (auth()->user()->role !== App\Enums\UserRole::Client)<a href="{{ route('api-tokens.index') }}">Acessos da API</a>@endif
    </div>
</nav>
