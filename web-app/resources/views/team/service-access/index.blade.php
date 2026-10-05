@extends('layouts.app')
@section('title', 'Acessos de serviços · Plataforma Mix7')
@section('body')
<div class="shell">
    @include('layouts.navigation', ['active' => 'service-access'])
    <main class="main">
        @include('layouts.topbar')
        <div class="content narrow-content">
            <p class="eyebrow">Equipe e ferramentas</p>
            <h1 class="heading">Acessos de serviços</h1>
            <p class="subheading">Veja como pedir acesso às ferramentas de trabalho. Cada pessoa entra com a própria conta do serviço.</p>
            @include('partials.flash')
            @if ($errors->any())<div class="notice notice-error" role="alert">{{ $errors->first() }}</div>@endif

            <div class="notice notice-info access-safety"><strong>Senhas não ficam aqui.</strong> A plataforma guarda apenas instruções, solicitações e o estado informado pela direção. A concessão e a revogação precisam ser feitas também no serviço externo. Não escreva senhas, tokens ou chaves nas instruções.</div>

            @if ($canManage)
                <section class="panel access-create">
                    <div class="section-heading"><div><h2>Cadastrar ferramenta</h2><p>Registre um caminho seguro para a equipe solicitar acesso individual.</p></div></div>
                    <form method="post" action="{{ route('service-access.store') }}" class="access-form">@csrf
                        <label class="field">Nome do serviço<input name="name" maxlength="160" required value="{{ old('name') }}" placeholder="Ex.: ferramenta de anúncios"></label>
                        <label class="field">Link oficial (opcional)<input name="service_url" type="url" maxlength="2048" value="{{ old('service_url') }}" placeholder="https://..."></label>
                        <label class="field">Como a pessoa entra<select name="access_method" required>@foreach ($methods as $value => $label)<option value="{{ $value }}" @selected(old('access_method') === $value)>{{ $label }}</option>@endforeach</select></label>
                        <label class="field">Revisar acessos em (opcional)<input name="review_due_on" type="date" value="{{ old('review_due_on') }}"></label>
                        <label class="field access-instructions">Instruções sem credenciais<textarea name="instructions" rows="3" maxlength="6000" required placeholder="Explique onde pedir uma conta e quem aprova. Nunca inclua senha ou token.">{{ old('instructions') }}</textarea></label>
                        <button class="primary-button" type="submit">Adicionar serviço</button>
                    </form>
                </section>
            @endif

            <section class="access-list" aria-labelledby="access-list-heading">
                <div class="section-heading"><div><h2 id="access-list-heading">Ferramentas cadastradas <span class="count-badge">{{ $services->whereNull('archived_at')->count() }}</span></h2><p>{{ $canManage ? 'A direção vê a matriz completa e o histórico.' : 'Você vê somente suas próprias solicitações.' }}</p></div></div>
                @forelse ($services as $service)
                    @php
                        $ownRequests = $service->requests->where('user_id', auth()->id());
                        $activeRequest = $ownRequests->first(fn ($item) => in_array($item->status, ['pending', 'granted'], true));
                    @endphp
                    <article class="panel access-card {{ $service->archived_at ? 'is-archived' : '' }}">
                        <div class="access-card-head">
                            <div><div class="access-type">{{ $methods[$service->access_method] ?? 'Método de acesso' }} @if($service->archived_at)<span class="access-state access-state-archived">Arquivado</span>@endif</div><h3>{{ $service->name }}</h3></div>
                            @if ($service->service_url)<a class="secondary-button" href="{{ $service->service_url }}" target="_blank" rel="noopener noreferrer">Abrir serviço</a>@endif
                        </div>
                        <p class="access-instruction-text">{{ $service->instructions }}</p>
                        @if ($service->review_due_on)<p class="access-review-date">Revisar acessos em {{ $service->review_due_on->format('d/m/Y') }}</p>@endif

                        @if (! $service->archived_at && ! $activeRequest)
                            <form method="post" action="{{ route('service-access.request', $service) }}">@csrf<button class="primary-button" type="submit">Solicitar meu acesso</button></form>
                        @elseif ($activeRequest)
                            <p class="access-own-state">Seu acesso: <strong>{{ ['pending' => 'aguardando análise', 'granted' => 'registrado como concedido'][$activeRequest->status] }}</strong></p>
                            @if ($activeRequest->status === 'pending')<form method="post" action="{{ route('service-access.withdraw', $activeRequest) }}">@csrf @method('DELETE')<button class="secondary-button" type="submit">Retirar solicitação</button></form>@endif
                        @endif

                        @if ($canManage)
                            <details class="access-details"><summary>Solicitações e histórico ({{ $service->requests->count() }})</summary>
                                @forelse ($service->requests as $accessRequest)
                                    <div class="access-request-row">
                                        <div><strong>{{ $accessRequest->user->name }}</strong><span>{{ ['pending' => 'Aguardando', 'granted' => 'Concedido no serviço', 'denied' => 'Negado', 'withdrawn' => 'Retirado', 'revoked' => 'Revogado'][$accessRequest->status] ?? $accessRequest->status }} · solicitado {{ $accessRequest->requested_at->format('d/m/Y H:i') }}</span>@if($accessRequest->reviewer)<small>Tratado por {{ $accessRequest->reviewer->name }} · {{ $accessRequest->reviewed_at?->format('d/m/Y H:i') }}</small>@endif</div>
                                        @if (! $service->archived_at && $accessRequest->status === 'pending')
                                            <div class="access-actions"><form method="post" action="{{ route('service-access.decide', $accessRequest) }}">@csrf @method('PATCH')<input type="hidden" name="status" value="granted"><button class="secondary-button" type="submit">Registrar concessão</button></form><form method="post" action="{{ route('service-access.decide', $accessRequest) }}">@csrf @method('PATCH')<input type="hidden" name="status" value="denied"><button class="secondary-button" type="submit">Negar</button></form></div>
                                        @elseif ($accessRequest->status === 'granted')
                                            <form method="post" action="{{ route('service-access.revoke', $accessRequest) }}">@csrf<button class="secondary-button" type="submit">Registrar revogação</button></form>
                                        @endif
                                    </div>
                                @empty<p class="empty-inline">Ainda não há solicitações para este serviço.</p>@endforelse
                                @if ($service->events->isNotEmpty())<h4>Atividade recente</h4><ul class="access-events">@foreach ($service->events as $event)<li>{{ ['service_created' => 'Serviço cadastrado', 'service_updated' => 'Instruções atualizadas', 'service_archived' => 'Serviço arquivado', 'service_restored' => 'Serviço restaurado', 'access_requested' => 'Acesso solicitado', 'access_granted' => 'Concessão registrada', 'access_denied' => 'Solicitação negada', 'access_revoked' => 'Revogação registrada', 'request_withdrawn' => 'Solicitação retirada'][$event->event_type] ?? 'Ação registrada' }} · {{ $event->actor?->name ?? 'Conta removida' }} · {{ $event->created_at->format('d/m/Y H:i') }}</li>@endforeach</ul>@endif
                            </details>
                            @if (! $service->archived_at)
                                <details class="access-details"><summary>Editar instruções</summary><form method="post" action="{{ route('service-access.update', $service) }}" class="access-form">@csrf @method('PUT')
                                    <label class="field">Nome<input name="name" maxlength="160" required value="{{ $service->name }}"></label><label class="field">Link oficial<input name="service_url" type="url" maxlength="2048" value="{{ $service->service_url }}"></label><label class="field">Método<select name="access_method" required>@foreach ($methods as $value => $label)<option value="{{ $value }}" @selected($service->access_method === $value)>{{ $label }}</option>@endforeach</select></label><label class="field">Revisar em<input name="review_due_on" type="date" value="{{ $service->review_due_on?->format('Y-m-d') }}"></label><label class="field access-instructions">Instruções sem credenciais<textarea name="instructions" rows="3" maxlength="6000" required>{{ $service->instructions }}</textarea></label><button class="secondary-button" type="submit">Salvar</button></form></details>
                                <form method="post" action="{{ route('service-access.archive', $service) }}">@csrf @method('DELETE')<button class="secondary-button" type="submit">Arquivar serviço</button></form>
                            @else
                                <form method="post" action="{{ route('service-access.restore', $service) }}">@csrf<button class="secondary-button" type="submit">Restaurar serviço</button></form>
                            @endif
                        @endif
                    </article>
                @empty
                    <p class="empty-inline">Nenhum serviço foi cadastrado. A direção pode adicionar a primeira ferramenta.</p>
                @endforelse
            </section>
        </div>
    </main>
</div>
<style>
.access-safety{margin:18px 0}.access-create,.access-card{margin-top:16px}.access-form{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;margin-top:14px}.access-form .field{display:grid;gap:6px}.access-form input,.access-form select,.access-form textarea{width:100%;min-width:0;border:1px solid #d6e0e1;border-radius:10px;padding:10px 12px;font:inherit;color:#202e35;background:#fff}.access-instructions{grid-column:1/-1}.access-form button{justify-self:start}.access-card{padding:18px}.access-card-head{display:flex;justify-content:space-between;align-items:flex-start;gap:14px}.access-card h3{margin:6px 0 0;color:#204b61;font-size:19px}.access-type,.access-review-date,.access-own-state{font-size:12px;color:#687b82}.access-instruction-text{white-space:pre-wrap;line-height:1.6;color:#43565d}.access-state{display:inline-flex;padding:3px 8px;border-radius:999px;font-size:10px}.access-state-archived{background:#edf0f1;color:#56676d}.access-details{margin-top:16px;border-top:1px solid #e8eeee;padding-top:12px}.access-details>summary{cursor:pointer;color:#204b61;font-weight:650}.access-request-row{display:flex;justify-content:space-between;align-items:center;gap:12px;padding:12px 0;border-bottom:1px solid #edf1f2}.access-request-row>div:first-child{display:grid;gap:4px}.access-request-row span,.access-request-row small,.access-events{font-size:12px;color:#667980}.access-actions{display:flex;gap:8px;flex-wrap:wrap}.access-events{padding-left:20px;line-height:1.7}.access-card>form{margin-top:10px}@media(max-width:650px){.access-form{grid-template-columns:1fr}.access-instructions{grid-column:auto}.access-card-head,.access-request-row{align-items:stretch;flex-direction:column}.access-card-head .secondary-button{align-self:flex-start}.access-actions{align-items:stretch;flex-direction:column}}
</style>
@endsection
