@extends('layouts.app')

@section('title', 'Equipe e clientes · Plataforma Mix7')

@section('body')
<div class="shell">
    @include('layouts.navigation', ['active' => 'team'])
    <main class="main">
        @include('layouts.topbar')
        <div class="content narrow-content">
            <p class="eyebrow">Acesso da agência</p>
            <h1 class="heading">Equipe e clientes</h1>
            <p class="subheading">Crie contas e mantenha cada pessoa dentro do seu espaço de acesso.</p>
            @include('partials.flash')

            <section class="panel team-panel">
                <div class="section-heading"><div><h2>Adicionar profissional</h2><p>A conta será vinculada à Mix7 e poderá receber tarefas.</p></div></div>
                <form method="post" action="{{ route('team.store') }}" class="team-form">@csrf
                    <label class="field">Nome<input name="name" value="{{ old('name') }}" maxlength="160" required autocomplete="name">@error('name')<span class="error">{{ $message }}</span>@enderror</label>
                    <label class="field">E-mail de acesso<input name="email" type="email" value="{{ old('email') }}" maxlength="255" required autocomplete="email">@error('email')<span class="error">{{ $message }}</span>@enderror</label>
                    <label class="field">Senha inicial<input name="password" type="password" minlength="12" maxlength="200" required autocomplete="new-password"><span class="field-help">Use pelo menos 12 caracteres e entregue a senha diretamente à pessoa.</span>@error('password')<span class="error">{{ $message }}</span>@enderror</label>
                    <div class="form-actions"><button class="primary-button" type="submit">Criar conta profissional</button></div>
                </form>
            </section>

            <section class="panel team-panel">
                <div class="section-heading"><div><h2>Adicionar cliente</h2><p>O cliente acompanhará apenas as demandas que a equipe vincular a esta conta.</p></div></div>
                <form method="post" action="{{ route('team.clients.store') }}" class="team-form">@csrf
                    <label class="field">Nome<input name="client_name" value="{{ old('client_name') }}" maxlength="160" required autocomplete="name">@error('client_name')<span class="error">{{ $message }}</span>@enderror</label>
                    <label class="field">E-mail de acesso<input name="client_email" type="email" value="{{ old('client_email') }}" maxlength="255" required autocomplete="email">@error('client_email')<span class="error">{{ $message }}</span>@enderror</label>
                    <label class="field">Senha inicial<input name="client_password" type="password" minlength="12" maxlength="200" required autocomplete="new-password"><span class="field-help">Defina uma senha forte e entregue por canal seguro. A aprovação dos materiais continua acessível por link sem login.</span>@error('client_password')<span class="error">{{ $message }}</span>@enderror</label>
                    <div class="form-actions"><button class="primary-button" type="submit">Criar conta de cliente</button></div>
                </form>
            </section>

            <section class="panel team-panel">
                <div class="section-heading"><div><h2>Profissionais cadastrados <span class="count-badge">{{ $professionals->total() }}</span></h2><p>Contas da equipe desta organização.</p></div></div>
                @forelse ($professionals as $professional)
                    <article class="team-row"><span class="avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr($professional->name, 0, 1)) }}</span><div><strong>{{ $professional->name }}</strong><span>{{ $professional->email }}</span></div><span class="pill {{ $professional->is_active ? 'pill-completed' : 'pill-blocked' }}">{{ $professional->is_active ? 'Ativo' : 'Desativado' }}</span></article>
                @empty<p class="empty-inline">Nenhum profissional cadastrado ainda.</p>@endforelse
                <div class="pagination-wrap">{{ $professionals->links() }}</div>
            </section>

            <section class="panel team-panel">
                <div class="section-heading"><div><h2>Clientes cadastrados <span class="count-badge">{{ $clients->total() }}</span></h2><p>Vincule cada demanda à conta correta para liberar o acompanhamento mínimo.</p></div></div>
                @forelse ($clients as $client)
                    <article class="team-row"><span class="avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr($client->name, 0, 1)) }}</span><div><strong>{{ $client->name }}</strong><span>{{ $client->email }}</span></div><span class="pill {{ $client->is_active ? 'pill-completed' : 'pill-blocked' }}">{{ $client->is_active ? 'Ativo' : 'Desativado' }}</span></article>
                @empty<p class="empty-inline">Nenhuma conta de cliente cadastrada ainda.</p>@endforelse
                <div class="pagination-wrap">{{ $clients->links() }}</div>
            </section>

            <p class="footnote">Nesta etapa, somente a direção da agência cria contas. Vínculos de cliente são limitados à mesma organização; revisão e aprovação do material continuam disponíveis por link sem exigir conta.</p>
        </div>
    </main>
</div>
@endsection
