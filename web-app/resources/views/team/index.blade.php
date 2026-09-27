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
            <p class="subheading">Envie um convite; cada pessoa cria a própria senha para acessar seu espaço.</p>
            @include('partials.flash')
            @error('invitation')<p class="error" role="alert">{{ $message }}</p>@enderror

            <section class="panel team-panel">
                <div class="section-heading"><div><h2>Adicionar profissional</h2><p>A conta será vinculada à Mix7 e poderá receber tarefas.</p></div></div>
                <form method="post" action="{{ route('team-invitations.store') }}" class="team-form">@csrf<input type="hidden" name="role" value="professional">
                    <label class="field">Nome<input name="name" value="{{ old('name') }}" maxlength="160" required autocomplete="name">@error('name')<span class="error">{{ $message }}</span>@enderror</label>
                    <label class="field">E-mail de acesso<input name="email" type="email" value="{{ old('email') }}" maxlength="255" required autocomplete="email">@error('email')<span class="error">{{ $message }}</span>@enderror</label>
                    <p class="field-help">A pessoa receberá um link temporário e criará a própria senha.</p>
                    <div class="form-actions"><button class="primary-button" type="submit">Enviar convite profissional</button></div>
                </form>
            </section>

            <section class="panel team-panel">
                <div class="section-heading"><div><h2>Adicionar gerente de marketing</h2><p>A pessoa terá acesso às rotinas de operação autorizadas para a gerência.</p></div></div>
                <form method="post" action="{{ route('team-invitations.store') }}" class="team-form">@csrf<input type="hidden" name="role" value="marketing_manager">
                    <label class="field">Nome<input name="name" value="{{ old('name') }}" maxlength="160" required autocomplete="name">@error('name')<span class="error">{{ $message }}</span>@enderror</label>
                    <label class="field">E-mail de acesso<input name="email" type="email" value="{{ old('email') }}" maxlength="255" required autocomplete="email">@error('email')<span class="error">{{ $message }}</span>@enderror</label>
                    <p class="field-help">O convite vence em 72 horas. A pessoa cria a própria senha ao ativar a conta.</p>
                    <div class="form-actions"><button class="primary-button" type="submit">Enviar convite de gerente</button></div>
                </form>
            </section>

            <section class="panel team-panel">
                <div class="section-heading"><div><h2>Adicionar cliente</h2><p>O cliente acompanhará apenas as demandas que a equipe vincular a esta conta.</p></div></div>
                <form method="post" action="{{ route('team-invitations.store') }}" class="team-form">@csrf<input type="hidden" name="role" value="client">
                    <label class="field">Nome<input name="name" value="{{ old('name') }}" maxlength="160" required autocomplete="name">@error('name')<span class="error">{{ $message }}</span>@enderror</label>
                    <label class="field">E-mail de acesso<input name="email" type="email" value="{{ old('email') }}" maxlength="255" required autocomplete="email">@error('email')<span class="error">{{ $message }}</span>@enderror</label>
                    <p class="field-help">A pessoa receberá um link temporário e criará a própria senha. A aprovação de materiais continua acessível por link sem login.</p>
                    <div class="form-actions"><button class="primary-button" type="submit">Enviar convite ao cliente</button></div>
                </form>
            </section>

            <section class="panel team-panel">
                <div class="section-heading"><div><h2>Convites aguardando ativação <span class="count-badge">{{ $invitations->count() }}</span></h2><p>Links válidos por 72 horas; convites ainda não usados podem ser cancelados.</p></div></div>
                @forelse ($invitations as $invitation)
                    <article class="team-row"><span class="avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr($invitation->name, 0, 1)) }}</span><div><strong>{{ $invitation->name }} · {{ $invitation->role->label() }}</strong><span>{{ $invitation->email }} · expira {{ $invitation->expires_at->format('d/m/Y H:i') }}</span></div><form method="post" action="{{ route('team-invitations.revoke', $invitation) }}">@csrf @method('DELETE')<button class="secondary-button" type="submit">Cancelar convite</button></form></article>
                @empty<p class="empty-inline">Nenhum convite aguardando ativação.</p>@endforelse
            </section>

            <section class="panel team-panel">
                <div class="section-heading"><div><h2>Gerentes cadastrados <span class="count-badge">{{ $managers->total() }}</span></h2><p>Contas de gerência desta organização.</p></div></div>
                @forelse ($managers as $manager)
                    <article class="team-row"><span class="avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr($manager->name, 0, 1)) }}</span><div><strong>{{ $manager->name }}</strong><span>{{ $manager->email }}</span></div><span class="pill {{ $manager->is_active ? 'pill-completed' : 'pill-blocked' }}">{{ $manager->is_active ? 'Ativo' : 'Desativado' }}</span><form method="post" action="{{ route('team.members.access', $manager) }}">@csrf @method('PATCH')<button class="secondary-button" type="submit">{{ $manager->is_active ? 'Desativar acesso' : 'Restaurar acesso' }}</button></form></article>
                @empty<p class="empty-inline">Nenhum gerente cadastrado ainda.</p>@endforelse
                <div class="pagination-wrap">{{ $managers->links() }}</div>
            </section>

            <section class="panel team-panel">
                <div class="section-heading"><div><h2>Profissionais cadastrados <span class="count-badge">{{ $professionals->total() }}</span></h2><p>Contas da equipe desta organização.</p></div></div>
                @forelse ($professionals as $professional)
                    <article class="team-row team-row-professional"><span class="avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr($professional->name, 0, 1)) }}</span><div><strong>{{ $professional->name }}</strong><span>{{ $professional->email }}</span></div><span class="pill {{ $professional->is_active ? 'pill-completed' : 'pill-blocked' }}">{{ $professional->is_active ? 'Ativo' : 'Desativado' }}</span><form method="post" action="{{ route('team.members.specialties', $professional) }}" class="specialties-form">@csrf @method('PATCH')<label class="field">Especialidades para sugestões de responsável<textarea name="specialties" rows="2" maxlength="600" placeholder="Ex.: Design, edição de vídeo, redação">{{ old('specialties', implode(', ', $professional->specialties ?? [])) }}</textarea></label><span class="field-help">Separe por vírgula. Correspondências exatas ajudam a pré-selecionar uma pessoa durante a revisão da IA; nada é atribuído automaticamente.</span>@error('specialties')<span class="error">{{ $message }}</span>@enderror<button class="secondary-button" type="submit">Salvar especialidades</button></form><form method="post" action="{{ route('team.members.access', $professional) }}">@csrf @method('PATCH')<button class="secondary-button" type="submit">{{ $professional->is_active ? 'Desativar acesso' : 'Restaurar acesso' }}</button></form></article>
                @empty<p class="empty-inline">Nenhum profissional cadastrado ainda.</p>@endforelse
                <div class="pagination-wrap">{{ $professionals->links() }}</div>
            </section>

            <section class="panel team-panel">
                <div class="section-heading"><div><h2>Clientes cadastrados <span class="count-badge">{{ $clients->total() }}</span></h2><p>Vincule cada demanda à conta correta para liberar o acompanhamento mínimo.</p></div></div>
                @forelse ($clients as $client)
                    <article class="team-row"><span class="avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr($client->name, 0, 1)) }}</span><div><strong>{{ $client->name }}</strong><span>{{ $client->email }}</span></div><span class="pill {{ $client->is_active ? 'pill-completed' : 'pill-blocked' }}">{{ $client->is_active ? 'Ativo' : 'Desativado' }}</span><form method="post" action="{{ route('team.members.access', $client) }}">@csrf @method('PATCH')<button class="secondary-button" type="submit">{{ $client->is_active ? 'Desativar acesso' : 'Restaurar acesso' }}</button></form></article>
                @empty<p class="empty-inline">Nenhuma conta de cliente cadastrada ainda.</p>@endforelse
                <div class="pagination-wrap">{{ $clients->links() }}</div>
            </section>

            <section class="panel team-panel">
                <div class="section-heading"><div><h2>Histórico da equipe <span class="count-badge">{{ $accessEvents->count() }}</span></h2><p>As 50 alterações mais recentes, com a pessoa que fez cada mudança.</p></div></div>
                @forelse ($accessEvents as $event)
                    <article class="team-row"><span class="avatar" aria-hidden="true">{{ $event->event_type === 'access_revoked' ? '−' : ($event->event_type === 'specialties_updated' ? '✎' : '+') }}</span><div><strong>{{ match ($event->event_type) {'access_revoked' => 'Acesso desativado', 'specialties_updated' => 'Especialidades profissionais atualizadas', default => 'Acesso restaurado'} }} · {{ $event->member->name }}</strong><span>Por {{ $event->actor->name }} · {{ $event->created_at->format('d/m/Y H:i') }}</span></div></article>
                @empty<p class="empty-inline">Nenhuma alteração de acesso registrada.</p>@endforelse
            </section>

            <p class="footnote">Somente a direção pode convidar, desativar e restaurar gerentes, profissionais e clientes desta organização. Tarefas abertas permanecem atribuídas quando o acesso é desativado; revise e transfira o trabalho antes do desligamento definitivo. Aprovações continuam por link, sem exigir conta.</p>
        </div>
    </main>
</div>
@endsection
