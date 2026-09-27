@extends('layouts.app')

@section('title', 'Entrar · Plataforma Mix7')

@section('body')
<main class="login-page">
    <section class="login-card" aria-labelledby="login-title">
        <div class="brand login-brand"><img class="login-brand-image" src="{{ asset('images/mix7-logo-round.png') }}" alt="Logotipo Mix7 Marketing"><span>Mix7 <span style="font-weight:450;color:#6b8188">| Processos</span></span></div>
        <p class="eyebrow" style="margin-top:30px">Área da equipe</p>
        <h1 class="login-title" id="login-title">Acesse sua conta</h1>
        <p class="subheading">Entre para acompanhar demandas e aprovações da agência.</p>
        @if (session('status'))<p class="notice" role="status">{{ session('status') }}</p>@endif
        <form method="post" action="{{ url('/entrar') }}">
            @csrf
            <label class="field" for="email">E-mail
                <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="username" required autofocus>
                @error('email')<span class="error">{{ $message }}</span>@enderror
            </label>
            <label class="field" for="password">Senha
                <input id="password" name="password" type="password" autocomplete="current-password" required>
                @error('password')<span class="error">{{ $message }}</span>@enderror
            </label>
            <label class="field" for="remember" style="display:flex;align-items:center;gap:9px;font-weight:500"><input id="remember" name="remember" type="checkbox" value="1" style="width:16px;height:16px;margin:0"> Manter conectado neste dispositivo</label>
            <button class="primary" type="submit">Entrar</button>
        </form>
        <p class="security-note"><a href="{{ route('password.request') }}">Esqueci minha senha</a></p>
        <p class="security-note">O acesso é criado pela administração da Mix7. Não há cadastro público nesta versão.</p>
    </section>
</main>
@endsection
