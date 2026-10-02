@extends('layouts.app')

@section('title', 'Criar nova senha · Plataforma Mix7')

@section('body')
<main class="login-page">
    <section class="login-card" aria-labelledby="password-reset-title">
        <div class="brand login-brand"><img class="login-brand-image" src="{{ asset('images/mix7-logo-round.png') }}" alt="Logotipo Mix7 Marketing"><span>Mix7 <span style="font-weight:450;color:#6b8188">| Processos</span></span></div>
        <p class="eyebrow" style="margin-top:30px">Recuperação de acesso</p>
        <h1 class="login-title" id="password-reset-title">Crie uma nova senha</h1>
        <p class="subheading">Use uma senha com pelo menos 12 caracteres. O link pode ser usado uma única vez.</p>
        <form method="post" action="{{ route('password.update') }}">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">
            <label class="field" for="email">E-mail
                <input id="email" name="email" type="email" value="{{ old('email', $email) }}" autocomplete="username" required>
                @error('email')<span class="error">{{ $message }}</span>@enderror
            </label>
            <label class="field" for="password">Nova senha
                <input id="password" name="password" type="password" autocomplete="new-password" minlength="12" required>
                @error('password')<span class="error">{{ $message }}</span>@enderror
            </label>
            <label class="field" for="password_confirmation">Confirme a nova senha
                <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" minlength="12" required>
            </label>
            <button class="primary" type="submit">Salvar nova senha</button>
        </form>
    </section>
</main>
@endsection
