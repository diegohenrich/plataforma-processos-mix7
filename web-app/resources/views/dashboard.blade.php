@extends('layouts.app')

@section('title', 'Painel · Plataforma Mix7')

@section('body')
<div class="shell">
    <aside class="sidebar" aria-label="Navegação principal">
        <div class="brand"><span class="brand-mark" aria-hidden="true">M</span><span>Mix7 <span style="font-weight:450;color:#bed4dc">| Processos</span></span></div>
        <div>
            <p class="nav-label">Espaço de trabalho</p>
            <a class="nav-item active" href="{{ route('dashboard') }}"><span aria-hidden="true">▦</span> Visão geral</a>
            <span class="nav-item" aria-disabled="true" title="Em construção"><span aria-hidden="true">◷</span> Demandas <span class="nav-state">Em breve</span></span>
            <span class="nav-item" aria-disabled="true" title="Em construção"><span aria-hidden="true">✓</span> Aprovações <span class="nav-state">Em breve</span></span>
            <span class="nav-item" aria-disabled="true" title="Em construção"><span aria-hidden="true">♧</span> Equipe <span class="nav-state">Em breve</span></span>
            <span class="nav-item" aria-disabled="true" title="Em construção"><span aria-hidden="true">▤</span> Conhecimento <span class="nav-state">Em breve</span></span>
        </div>
        <div class="sidebar-note">A plataforma reúne o trabalho da agência e mantém cada etapa registrada.</div>
    </aside>
    <nav class="mobile-nav" aria-label="Mix7 Processos"><div class="brand"><span class="brand-mark" aria-hidden="true">M</span><span>Mix7 | Processos</span></div></nav>
    <main class="main">
        <header class="topbar">
            <div class="user-chip"><span class="avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr(auth()->user()->name, 0, 1)) }}</span><span>{{ auth()->user()->name }}<br><span style="font-size:11px;color:#819096">{{ auth()->user()->role->label() }}</span></span></div>
            <form method="post" action="{{ route('logout') }}">@csrf<button class="logout" type="submit">Sair</button></form>
        </header>
        <div class="content">
            <section class="welcome">
                <div><p class="eyebrow">Plataforma de processos</p><h1 class="heading">Olá, {{ auth()->user()->name }}</h1><p class="subheading">A base compartilhada da Mix7 está pronta para receber os módulos do trabalho.</p></div>
                <span class="status">Conta ativa</span>
            </section>
            <h2 class="section-title">Seu espaço de trabalho</h2>
            <section class="grid" aria-label="Áreas da plataforma">
                <article class="module"><span class="icon" aria-hidden="true">◷</span><h3>Demandas e tarefas</h3><p>Briefings, responsáveis, execução e andamento do trabalho.</p></article>
                <article class="module"><span class="icon" aria-hidden="true">✓</span><h3>Aprovações</h3><p>Revisões internas e do cliente, versões e comentários.</p></article>
                <article class="module"><span class="icon" aria-hidden="true">♧</span><h3>Equipe</h3><p>Atividades, tempo registrado e acompanhamento.</p></article>
                <article class="module"><span class="icon" aria-hidden="true">▤</span><h3>Conhecimento</h3><p>Referências, treinamentos e integração de pessoas.</p></article>
                <article class="module pending"><span class="icon" aria-hidden="true">✧</span><h3>Automação e sugestões</h3><p>Recursos entram após regras e revisão humana definidas.</p></article>
                <article class="module pending"><span class="icon" aria-hidden="true">⌁</span><h3>Indicadores de gestão</h3><p>Fórmulas e critérios aguardam definição antes de pontuar.</p></article>
            </section>
            <p class="footnote">Este painel inicial não contém demandas fictícias. As áreas são conectadas às próximas entregas de forma incremental.</p>
        </div>
    </main>
</div>
@endsection
