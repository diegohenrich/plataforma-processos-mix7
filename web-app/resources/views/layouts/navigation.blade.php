<aside class="sidebar" aria-label="Navegação principal">
    <div class="brand"><span class="brand-mark" aria-hidden="true">M</span><span>Mix7 <span style="font-weight:450;color:#bed4dc">| Processos</span></span></div>
    <div>
        <p class="nav-label">Espaço de trabalho</p>
        <a class="nav-item {{ $active === 'dashboard' ? 'active' : '' }}" href="{{ route('dashboard') }}"><span aria-hidden="true">▦</span> Visão geral</a>
        <a class="nav-item {{ $active === 'demands' ? 'active' : '' }}" href="{{ route('demands.index') }}"><span aria-hidden="true">◷</span> {{ auth()->user()->role === App\Enums\UserRole::Client ? 'Minhas demandas' : 'Demandas' }}</a>
        @if (auth()->user()->role !== App\Enums\UserRole::Client)
            <a class="nav-item {{ $active === 'task-board' ? 'active' : '' }}" href="{{ route('demand-tasks.board') }}"><span aria-hidden="true">▦</span> Quadro de tarefas</a>
            <a class="nav-item {{ $active === 'approvals' ? 'active' : '' }}" href="{{ route('approvals.index') }}"><span aria-hidden="true">✓</span> Aprovações</a>
            @can('viewActivity', App\Models\User::class)<a class="nav-item {{ $active === 'activity' ? 'active' : '' }}" href="{{ route('team.activity') }}"><span aria-hidden="true">◷</span> {{ auth()->user()->role === App\Enums\UserRole::Professional ? 'Meu trabalho' : 'Produção da equipe' }}</a>@endcan
            @can('viewAny', App\Models\User::class)<a class="nav-item {{ $active === 'team' ? 'active' : '' }}" href="{{ route('team.index') }}"><span aria-hidden="true">♧</span> Equipe</a>@else<span class="nav-item" aria-disabled="true" title="Em construção"><span aria-hidden="true">♧</span> Equipe <span class="nav-state">Em breve</span></span>@endcan
        @endif
        @can('viewAny', App\Models\KnowledgeItem::class)<a class="nav-item {{ $active === 'knowledge' ? 'active' : '' }}" href="{{ route('knowledge.index') }}"><span aria-hidden="true">▤</span> Conhecimento</a>@endcan
    </div>
    <div class="sidebar-note">A plataforma reúne o trabalho da agência e mantém cada etapa registrada.</div>
</aside>
<nav class="mobile-nav" aria-label="Navegação para celular">
    <div class="brand"><span class="brand-mark" aria-hidden="true">M</span><span>Mix7 | Processos</span></div>
    <div class="mobile-nav-actions">
        <a href="{{ route('dashboard') }}">Início</a>
        <a href="{{ route('demands.index') }}">{{ auth()->user()->role === App\Enums\UserRole::Client ? 'Minhas demandas' : 'Demandas' }}</a>
        @if (auth()->user()->role !== App\Enums\UserRole::Client)<a href="{{ route('demand-tasks.board') }}">Quadro de tarefas</a><a href="{{ route('approvals.index') }}">Aprovações</a>@endif
        @can('viewActivity', App\Models\User::class)<a href="{{ route('team.activity') }}">{{ auth()->user()->role === App\Enums\UserRole::Professional ? 'Meu trabalho' : 'Produção' }}</a>@endcan
        @can('viewAny', App\Models\User::class)<a href="{{ route('team.index') }}">Equipe</a>@endcan
        @can('viewAny', App\Models\KnowledgeItem::class)<a href="{{ route('knowledge.index') }}">Conhecimento</a>@endcan
    </div>
</nav>
