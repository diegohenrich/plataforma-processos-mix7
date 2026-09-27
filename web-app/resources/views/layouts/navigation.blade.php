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
@php
    $navigationRole = auth()->user()->role;
    $navigationItems = [
        ['label' => 'Visão geral', 'icon' => '▦', 'route' => 'dashboard', 'active' => 'dashboard', 'show' => true],
        ['label' => $navigationRole === App\Enums\UserRole::Client ? 'Minhas demandas' : 'Demandas', 'icon' => '◷', 'route' => 'demands.index', 'active' => 'demands', 'show' => true],
        ['label' => 'Notificações', 'icon' => '♧', 'route' => 'notifications.index', 'active' => 'notifications', 'show' => true],
        ['label' => 'Assistente da agência', 'icon' => '✦', 'route' => 'organization-assistant.index', 'active' => 'organization-assistant', 'show' => in_array($navigationRole, [App\Enums\UserRole::AgencyOwner, App\Enums\UserRole::MarketingManager], true)],
        ['label' => 'Quadro de tarefas', 'icon' => '▦', 'route' => 'demand-tasks.board', 'active' => 'task-board', 'show' => $navigationRole !== App\Enums\UserRole::Client],
        ['label' => 'Aprovações', 'icon' => '✓', 'route' => 'approvals.index', 'active' => 'approvals', 'show' => $navigationRole !== App\Enums\UserRole::Client],
        ['label' => 'Tipos de aprovação', 'icon' => '＋', 'route' => 'approval-modules.index', 'active' => 'approval-modules', 'show' => auth()->user()->can('create', App\Models\Demand::class)],
        ['label' => $navigationRole === App\Enums\UserRole::Professional ? 'Meu trabalho' : 'Produção da equipe', 'mobile_label' => $navigationRole === App\Enums\UserRole::Professional ? 'Meu trabalho' : 'Produção', 'icon' => '◷', 'route' => 'team.activity', 'active' => 'activity', 'show' => auth()->user()->can('viewActivity', App\Models\User::class)],
        ['label' => 'Disponibilidade', 'icon' => '◫', 'route' => 'team.capacity', 'active' => 'capacity', 'show' => $navigationRole !== App\Enums\UserRole::Client],
        ['label' => 'Avaliações', 'icon' => '◎', 'route' => 'performance-reviews.index', 'active' => 'performance-reviews', 'show' => in_array($navigationRole, [App\Enums\UserRole::AgencyOwner, App\Enums\UserRole::MarketingManager, App\Enums\UserRole::Professional], true)],
        ['label' => 'Equipe', 'icon' => '♧', 'route' => 'team.index', 'active' => 'team', 'show' => auth()->user()->can('viewAny', App\Models\User::class)],
        ['label' => 'Acessos de serviços', 'icon' => '⌑', 'route' => 'service-access.index', 'active' => 'service-access', 'show' => $navigationRole !== App\Enums\UserRole::Client],
        ['label' => 'Conhecimento', 'icon' => '▤', 'route' => 'knowledge.index', 'active' => 'knowledge', 'show' => auth()->user()->can('viewAny', App\Models\KnowledgeItem::class)],
        ['label' => 'Acessos da API', 'icon' => '⌘', 'route' => 'api-tokens.index', 'active' => 'integrations', 'show' => $navigationRole !== App\Enums\UserRole::Client],
    ];
@endphp
<aside class="sidebar" aria-label="Navegação principal">
    <div class="brand">
        <img class="brand-image" src="{{ asset('images/mix7-logo-round.png') }}" alt="Logotipo Mix7 Marketing">
        <span class="brand-copy"><strong>{{ auth()->user()->name }}</strong><small>{{ auth()->user()->organization->name }}</small></span>
    </div>
    <div>
        <p class="nav-label">Espaço de trabalho</p>
        @foreach ($navigationItems as $item)
            @if ($item['show'])<a class="nav-item {{ $active === $item['active'] ? 'active' : '' }}" href="{{ route($item['route']) }}"><span aria-hidden="true">{{ $item['icon'] }}</span> {{ $item['label'] }}</a>@endif
        @endforeach
    </div>
    <div class="sidebar-note">A plataforma reúne o trabalho da agência e mantém cada etapa registrada.</div>
</aside>
<nav class="mobile-nav" aria-label="Navegação para celular">
    <div class="brand">
        <img class="brand-image" src="{{ asset('images/mix7-logo-round.png') }}" alt="Logotipo Mix7 Marketing">
        <span class="brand-copy">Mix7 <small>| Processos</small></span>
    </div>
    <div class="mobile-nav-actions">
        @foreach ($navigationItems as $item)
            @if ($item['show'])<a href="{{ route($item['route']) }}">{{ $item['mobile_label'] ?? $item['label'] }}</a>@endif
        @endforeach
    </div>
</nav>
