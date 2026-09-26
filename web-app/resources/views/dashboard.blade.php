@extends('layouts.app')

@section('title', 'Painel · Plataforma Mix7')

@section('body')
<div class="shell">
    @include('layouts.navigation', ['active' => 'dashboard'])
    <main class="main">
        @include('layouts.topbar')
        <div class="content">
            @include('partials.flash')
            @if (auth()->user()->role === App\Enums\UserRole::Client)
                <section class="welcome"><div><p class="eyebrow">Área do cliente</p><h1 class="heading">Olá, {{ auth()->user()->name }}</h1><p class="subheading">Acompanhe as demandas que a Mix7 vinculou à sua conta.</p></div><span class="status">Conta ativa</span></section>
                <section class="grid" aria-label="Área do cliente"><a class="module module-link" href="{{ route('demands.index') }}"><span class="icon" aria-hidden="true">◷</span><h3>Minhas demandas</h3><p>Veja o nome e a etapa atual dos seus trabalhos.</p><span class="module-action">Abrir minhas demandas →</span></a></section>
                <p class="footnote">Para revisar um material, use o link seguro enviado pela equipe. Esse link funciona sem entrar na conta.</p>
            @else
            <section class="welcome">
                <div><p class="eyebrow">Plataforma de processos</p><h1 class="heading">Olá, {{ auth()->user()->name }}</h1><p class="subheading">Acompanhe o trabalho da agência e registre cada etapa.</p></div>
                <span class="status">Conta ativa</span>
            </section>
            <h2 class="section-title">Seu espaço de trabalho</h2>
            <section class="grid" aria-label="Áreas da plataforma">
                <a class="module module-link" href="{{ route('demands.index') }}"><span class="icon" aria-hidden="true">◷</span><h3>Demandas e tarefas</h3><p>Briefings, responsáveis, execução e andamento do trabalho.</p><span class="module-action">Abrir demandas →</span></a>
                <article class="module pending"><span class="icon" aria-hidden="true">✓</span><h3>Aprovações</h3><p>Revisões internas e do cliente, versões e comentários.</p><span class="module-state">Em construção</span></article>
                @can('viewAny', App\Models\User::class)
                    <a class="module module-link" href="{{ route('team.index') }}"><span class="icon" aria-hidden="true">♧</span><h3>Equipe</h3><p>Cadastre profissionais para distribuir tarefas.</p><span class="module-action">Abrir equipe →</span></a>
                @else
                    <article class="module pending"><span class="icon" aria-hidden="true">♧</span><h3>Equipe</h3><p>Atividades, tempo registrado e acompanhamento.</p><span class="module-state">Em construção</span></article>
                @endcan
                <a class="module module-link" href="{{ route('knowledge.index') }}"><span class="icon" aria-hidden="true">▤</span><h3>Conhecimento</h3><p>Referências, treinamentos e integração de pessoas.</p><span class="module-action">Abrir conhecimento →</span></a>
                <article class="module pending"><span class="icon" aria-hidden="true">✧</span><h3>Automação e sugestões</h3><p>Recursos entram após regras e revisão humana definidas.</p></article>
                <article class="module pending"><span class="icon" aria-hidden="true">⌁</span><h3>Indicadores de gestão</h3><p>Fórmulas e critérios aguardam definição antes de pontuar.</p></article>
            </section>
            <p class="footnote">Conhecimento e demandas já estão disponíveis nesta etapa. Outros módulos ainda estão sendo construídos.</p>
            @endif
        </div>
    </main>
</div>
@endsection
