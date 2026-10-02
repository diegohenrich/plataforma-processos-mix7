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
                <a class="module module-link" href="{{ route('demands.index') }}"><span class="icon" aria-hidden="true">◷</span><h3>Demandas</h3><p>Briefings, tarefas, responsáveis, aprovações e andamento do trabalho.</p><span class="module-action">Abrir espaço de demandas →</span></a>
                @can('viewAny', App\Models\User::class)
                    <a class="module module-link" href="{{ route('team.index') }}"><span class="icon" aria-hidden="true">♧</span><h3>Equipe</h3><p>Pessoas, produção, disponibilidade e avaliações no mesmo espaço.</p><span class="module-action">Abrir equipe →</span></a>
                @elseif (auth()->user()->role === App\Enums\UserRole::Professional)
                    <a class="module module-link" href="{{ route('team.activity') }}"><span class="icon" aria-hidden="true">◷</span><h3>Meu trabalho</h3><p>Veja suas tarefas, acompanhe o tempo e acione o cronômetro.</p><span class="module-action">Abrir minhas tarefas →</span></a>
                @elseif (auth()->user()->role === App\Enums\UserRole::MarketingManager)
                    <a class="module module-link" href="{{ route('team.activity') }}"><span class="icon" aria-hidden="true">◷</span><h3>Equipe</h3><p>Acompanhe o que cada profissional está executando, o timer e a carga de trabalho.</p><span class="module-action">Abrir visão da equipe →</span></a>
                @endcan
                <a class="module module-link" href="{{ route('knowledge.index') }}"><span class="icon" aria-hidden="true">▤</span><h3>Conhecimento</h3><p>Referências, treinamentos e integração de pessoas.</p><span class="module-action">Abrir conhecimento →</span></a>
                <a class="module module-link" href="{{ route('notifications.index') }}"><span class="icon" aria-hidden="true">♧</span><h3>Notificações</h3><p>Acompanhe atualizações e respostas ligadas ao seu trabalho.</p><span class="module-action">Abrir notificações →</span></a>
                @if (auth()->user()->role !== App\Enums\UserRole::Client)
                    <a class="module module-link" href="{{ route('service-access.index') }}"><span class="icon" aria-hidden="true">⌑</span><h3>Acessos de serviços</h3><p>Consulte instruções e solicitações de acesso usadas pela equipe.</p><span class="module-action">Abrir acessos de serviços →</span></a>
                    <a class="module module-link" href="{{ route('api-tokens.index') }}"><span class="icon" aria-hidden="true">⌘</span><h3>Acessos da API</h3><p>Gerencie os tokens pessoais usados para integrações.</p><span class="module-action">Abrir acessos da API →</span></a>
                @endif
                @if (in_array(auth()->user()->role, [App\Enums\UserRole::AgencyOwner, App\Enums\UserRole::MarketingManager], true))
                    <a class="module module-link" href="{{ route('organization-assistant.index') }}"><span class="icon" aria-hidden="true">✧</span><h3>Assistente da agência</h3><p>Consulte sugestões e informações com revisão humana. A conexão externa depende de configuração e política de dados aprovada.</p><span class="module-action">Abrir assistente →</span></a>
                @endif
            </section>
            <p class="footnote">As áreas disponíveis dependem do seu perfil. Avaliações não calculam nota; o assistente de IA requer configuração e política de dados aprovadas.</p>
            @endif
        </div>
    </main>
</div>
@endsection
