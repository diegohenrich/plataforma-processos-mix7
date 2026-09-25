@extends('layouts.app')

@section('title', 'Demandas · Plataforma Mix7')

@section('body')
<div class="shell">
    @include('layouts.navigation', ['active' => 'demands'])
    <main class="main">
        @include('layouts.topbar')
        <div class="content">
            <div class="page-heading">
                <div><p class="eyebrow">Trabalho da agência</p><h1 class="heading">Demandas</h1><p class="subheading">Briefings, responsáveis e etapas em um só lugar.</p></div>
                @can('create', App\Models\Demand::class)<a class="primary-link" href="{{ route('demands.create') }}">Nova demanda</a>@endcan
            </div>

            @include('partials.flash')

            @if ($demands->isEmpty())
                <section class="empty-state"><span class="empty-icon" aria-hidden="true">◷</span><h2>Nenhuma demanda por aqui</h2><p>Quando uma demanda for criada ou atribuída a você, ela aparecerá nesta lista.</p>@can('create', App\Models\Demand::class)<a class="primary-link" href="{{ route('demands.create') }}">Criar primeira demanda</a>@endcan</section>
            @else
                <section class="demand-list" aria-label="Lista de demandas">
                    @foreach ($demands as $demand)
                        <a class="demand-card" href="{{ route('demands.show', $demand) }}">
                            <div class="demand-card-main"><div class="demand-title-row"><h2>{{ $demand->title }}</h2><span class="pill">{{ $demand->status->label() }}</span></div><p>{{ \Illuminate\Support\Str::limit($demand->brief, 145) }}</p><span class="meta-line">Criada por {{ $demand->creator->name }} · {{ $demand->created_at->format('d/m/Y') }}</span></div>
                            <div class="demand-card-side"><strong>{{ $demand->tasks->count() }}</strong><span>{{ \Illuminate\Support\Str::plural('tarefa', $demand->tasks->count()) }}</span><span class="task-mini">{{ $demand->tasks->where('status.value', 'completed')->count() }} concluídas</span></div>
                        </a>
                    @endforeach
                </section>
                <div class="pagination-wrap">{{ $demands->links() }}</div>
            @endif
        </div>
    </main>
</div>
@endsection
