@extends('layouts.app')

@section('title', 'Configurações · Plataforma Mix7')

@section('body')
<div class="shell">
    @include('layouts.navigation', ['active' => 'settings'])
    <main class="main">
        @include('layouts.topbar')
        <div class="content narrow-content">
            <p class="eyebrow">Agência Mix7</p>
            <h1 class="heading">Configurações</h1>
            <p class="subheading">Organize os tipos de trabalho e os serviços usados pela agência.</p>
            @include('partials.flash')

            <section class="settings-grid" aria-label="Opções de configuração">
                <article class="panel settings-card">
                    <span class="settings-card-icon" aria-hidden="true">▤</span>
                    <div><h2>Tipos de aprovação</h2><p>Configure áreas de trabalho, campos próprios e etapas internas de conferência.</p></div>
                    <a class="secondary-link" href="{{ route('approval-modules.index') }}">Configurar tipos de aprovação →</a>
                </article>

                @if ($canManageAi)
                    <article class="panel settings-card">
                        <span class="settings-card-icon" aria-hidden="true">✦</span>
                        <div><h2>Inteligência artificial</h2><p>Consulte e ajuste a conexão central dos assistentes da Mix7.</p></div>
                        <a class="secondary-link" href="{{ route('ai-settings.index') }}">Configurar conexão de IA →</a>
                    </article>
                @endif
            </section>
        </div>
    </main>
</div>
<style>
    .settings-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,280px),1fr));gap:16px;margin-top:22px}.settings-card{display:grid;gap:14px;padding:22px;align-content:start}.settings-card-icon{display:grid;place-items:center;width:42px;height:42px;border-radius:12px;background:#eaf6fa;color:#204b61;font-size:20px}.settings-card h2{margin:0 0 7px;color:#202e35;font-size:16px}.settings-card p{margin:0;color:#687a80;font-size:13px;line-height:1.55}.settings-card .secondary-link{width:max-content;max-width:100%;margin-top:3px}
</style>
@endsection
