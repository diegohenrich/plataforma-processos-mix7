@extends('layouts.app')
@section('title', 'Acessos da API · Mix7')
@section('body')
<div class="shell">
    @include('layouts.navigation', ['active' => 'integrations'])
    <main class="main">
        @include('layouts.topbar')
        <div class="content narrow-content">
            <p class="eyebrow">Integrações</p>
            <h1 class="heading">Acessos da API</h1>
            <p class="subheading">Crie um token para conectar um aplicativo autorizado. Ele vale por até 90 dias e usa as permissões da sua conta.</p>
            @include('partials.flash')
            <section class="panel token-panel" aria-labelledby="create-token-title">
                <h2 id="create-token-title">Criar token</h2>
                <p>O token aparecerá uma única vez. Copie-o no aplicativo que vai usar. Nunca o envie por mensagem nem o salve no código do projeto.</p>
                <form method="post" action="{{ route('api-tokens.store') }}" class="token-form">
                    @csrf
                    <label class="field">Nome para identificar o aplicativo<input name="name" required maxlength="80" value="{{ old('name') }}" placeholder="Ex.: aplicativo de desktop"></label>
                    <label class="field">Validade<select name="expires_in_days" required><option value="30" @selected(old('expires_in_days', '30') === '30')>30 dias</option><option value="7" @selected(old('expires_in_days') === '7')>7 dias</option><option value="90" @selected(old('expires_in_days') === '90')>90 dias</option></select></label>
                    <label class="field">Confirme sua senha atual<input name="current_password" type="password" required autocomplete="current-password"></label>
                    @error('name')<span class="error">{{ $message }}</span>@enderror
                    @error('expires_in_days')<span class="error">Escolha 7, 30 ou 90 dias.</span>@enderror
                    @error('current_password')<span class="error">A senha atual não confere.</span>@enderror
                    <button class="primary-button" type="submit">Criar e mostrar token</button>
                </form>
            </section>
            <section class="token-list" aria-labelledby="tokens-title">
                <div class="section-heading"><div><h2 id="tokens-title">Seus tokens</h2><p>Tokens expirados ou revogados não autorizam chamadas. Revogue qualquer acesso que não reconheça.</p></div></div>
                @forelse ($tokens as $token)
                    @php($expired = $token->expires_at && $token->expires_at->isPast())
                    <article class="panel token-row">
                        <div><strong>{{ $token->name }}</strong><span>Criado {{ $token->created_at->format('d/m/Y H:i') }}@if ($token->last_used_at) · Usado {{ $token->last_used_at->format('d/m/Y H:i') }}@else · Ainda não usado @endif</span><span @class(['token-expired' => $expired])>@if ($expired) Expirado @else Expira {{ $token->expires_at?->format('d/m/Y H:i') ?? 'sem prazo' }} @endif</span></div>
                        <form method="post" action="{{ route('api-tokens.destroy', $token->id) }}" onsubmit="return confirm('Revogar este token agora? O aplicativo deixará de acessar a API.');">@csrf @method('DELETE')<button class="secondary-button" type="submit">Revogar</button></form>
                    </article>
                @empty
                    <p class="empty-inline">Você ainda não criou tokens para aplicativos.</p>
                @endforelse
            </section>
            <p class="token-help">A API aplica o papel, o estado da conta e as regras da organização em cada rota. Convites e links de aprovação continuam sendo credenciais separadas.</p>
        </div>
    </main>
</div>
@endsection
