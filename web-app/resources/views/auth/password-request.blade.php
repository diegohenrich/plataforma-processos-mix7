@extends('layouts.app')

@section('title', 'Redefinir senha · Plataforma Mix7')

@section('body')
<main class="login-page">
    <section class="login-card" aria-labelledby="password-request-title">
        <div class="brand login-brand"><img class="login-brand-image" src="{{ asset('images/mix7-logo-round.png') }}" alt="Logotipo Mix7 Marketing"><span>Mix7 <span style="font-weight:450;color:#6b8188">| Processos</span></span></div>
        <p class="eyebrow" style="margin-top:30px">Recuperação de acesso</p>
        <h1 class="login-title" id="password-request-title">Esqueceu sua senha?</h1>
        <p class="subheading">Informe o e-mail da sua conta ativa. Se ele estiver cadastrado, enviaremos um link para criar uma nova senha.</p>
        @if (session('status'))<p class="notice" role="status">{{ session('status') }}</p>@endif
        <form method="post" action="{{ route('password.email') }}">
            @csrf
            <label class="field" for="email">E-mail
                <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="username" required autofocus>
                @error('email')<span class="error">{{ $message }}</span>@enderror
            </label>
            <button class="primary" type="submit">Enviar link de redefinição</button>
        </form>
        <p class="security-note"><a href="{{ route('login') }}">Voltar para entrar</a></p>
    </section>
</main>
@endsection
