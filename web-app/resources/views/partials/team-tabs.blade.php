<nav class="workspace-tabs" aria-label="Seções da equipe">
    @can('viewAny', App\Models\User::class)
        <a href="{{ route('team.index') }}" @if (($teamView ?? '') === 'people') aria-current="page" @endif>Pessoas e acessos</a>
    @endcan
    @can('viewActivity', App\Models\User::class)
        <a href="{{ route('team.activity') }}" @if (($teamView ?? '') === 'activity') aria-current="page" @endif>{{ auth()->user()->role === App\Enums\UserRole::Professional ? 'Meu trabalho' : 'Produção da equipe' }}</a>
    @endcan
    @if (auth()->user()->role !== App\Enums\UserRole::Client)
        <a href="{{ route('team.capacity') }}" @if (($teamView ?? '') === 'capacity') aria-current="page" @endif>Disponibilidade</a>
    @endif
    @if (in_array(auth()->user()->role, [App\Enums\UserRole::AgencyOwner, App\Enums\UserRole::MarketingManager, App\Enums\UserRole::Professional], true))
        <a href="{{ route('performance-reviews.index') }}" @if (($teamView ?? '') === 'reviews') aria-current="page" @endif>Avaliações</a>
    @endif
</nav>
