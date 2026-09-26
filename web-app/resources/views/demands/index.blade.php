@extends('layouts.app')

@section('title', 'Demandas · Plataforma Mix7')

@section('body')
<div class="shell">
    @include('layouts.navigation', ['active' => 'demands'])
    <main class="main">
        @include('layouts.topbar')
        <div class="content">
            <div class="page-heading">
                <div><p class="eyebrow">{{ auth()->user()->role === App\Enums\UserRole::Client ? 'Área do cliente' : 'Trabalho da agência' }}</p><h1 class="heading">{{ auth()->user()->role === App\Enums\UserRole::Client ? 'Minhas demandas' : 'Demandas' }}</h1><p class="subheading">{{ auth()->user()->role === App\Enums\UserRole::Client ? 'Acompanhe a etapa atual dos trabalhos vinculados à sua conta.' : 'Briefings, responsáveis e etapas em um só lugar.' }}</p></div>
                @can('create', App\Models\Demand::class)<a class="primary-link" href="{{ route('demands.create') }}">Nova demanda</a>@endcan
            </div>

            @include('partials.flash')

            @if ($demands->isEmpty())
                <section class="empty-state"><span class="empty-icon" aria-hidden="true">◷</span><h2>{{ auth()->user()->role === App\Enums\UserRole::Client ? 'Nenhuma demanda vinculada' : 'Nenhuma demanda por aqui' }}</h2><p>{{ auth()->user()->role === App\Enums\UserRole::Client ? 'A Mix7 ainda não vinculou demandas à sua conta.' : 'Quando uma demanda for criada ou atribuída a você, ela aparecerá nesta lista.' }}</p>@can('create', App\Models\Demand::class)<a class="primary-link" href="{{ route('demands.create') }}">Criar primeira demanda</a>@endcan</section>
            @else
                <section class="demand-list" aria-label="Lista de demandas">
                    @foreach ($demands as $demand)
                        <a class="demand-card" href="{{ route('demands.show', $demand) }}">
                            @if (auth()->user()->role === App\Enums\UserRole::Client)
                                <div class="demand-card-main"><div class="demand-title-row"><h2>{{ $demand->title }}</h2><span class="pill">{{ $demand->status->label() }}</span></div><p>Acompanhe a etapa atual. A aprovação de materiais será enviada em um link separado.</p><span class="meta-line">Atualizada em {{ $demand->updated_at->format('d/m/Y') }}</span></div>
                            @else
                            <div class="demand-card-main"><div class="demand-title-row"><h2>{{ $demand->title }}</h2><span class="pill">{{ $demand->status->label() }}</span></div><p>{{ \Illuminate\Support\Str::limit($demand->brief, 145) }}</p><span class="meta-line">Criada por {{ $demand->creator->name }} · {{ $demand->created_at->format('d/m/Y') }}</span></div>
                            <div class="demand-card-side"><strong>{{ $demand->tasks->count() }}</strong><span>{{ \Illuminate\Support\Str::plural('tarefa', $demand->tasks->count()) }}</span><span class="task-mini">{{ $demand->tasks->where('status.value', 'completed')->count() }} concluídas</span></div>
                            @endif
                        </a>
                    @endforeach
                </section>
                <div class="pagination-wrap">{{ $demands->links() }}</div>
            @endif
        </div>
    </main>
</div>
@endsection
