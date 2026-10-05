@extends('layouts.app')
@section('title', 'Token criado · Mix7')
@section('body')
<div class="shell">
    @include('layouts.navigation', ['active' => 'integrations'])
    <main class="main">
        @include('layouts.topbar')
        <div class="content narrow-content">
            <p class="eyebrow">Integrações</p>
            <h1 class="heading">Token criado</h1>
            <p class="subheading">Copie agora para <strong>{{ $tokenName }}</strong>. Por segurança, esta é a única exibição do segredo.</p>
            <section class="panel token-created" aria-labelledby="token-value-title">
                <h2 id="token-value-title">Seu token secreto</h2>
                <label class="field" for="api-token-value">Copie e guarde no aplicativo autorizado</label>
                <div class="token-value-row"><input id="api-token-value" type="password" value="{{ $token }}" readonly autocomplete="off" spellcheck="false"><button class="secondary-button" id="reveal-api-token" type="button" aria-pressed="false">Mostrar</button><button class="secondary-button" id="copy-api-token" type="button">Copiar</button></div>
                <p>Expira em {{ $expiresAt->format('d/m/Y H:i') }}. Esta página não é armazenada em cache. Feche-a depois de copiar e não compartilhe o segredo.</p>
                <a class="primary-button" href="{{ route('api-tokens.index') }}">Voltar aos acessos da API</a>
            </section>
        </div>
    </main>
</div>
<script>
    (() => {
        const field = document.getElementById('api-token-value');
        const reveal = document.getElementById('reveal-api-token');
        const copy = document.getElementById('copy-api-token');
        reveal.addEventListener('click', () => {
            const visible = field.type === 'password';
            field.type = visible ? 'text' : 'password';
            reveal.textContent = visible ? 'Ocultar' : 'Mostrar';
            reveal.setAttribute('aria-pressed', String(visible));
        });
        copy.addEventListener('click', async () => {
            try {
                await navigator.clipboard.writeText(field.value);
                copy.textContent = 'Copiado';
            } catch {
                field.type = 'text';
                field.select();
                copy.textContent = 'Selecione e copie';
            }
        });
    })();
</script>
@endsection
