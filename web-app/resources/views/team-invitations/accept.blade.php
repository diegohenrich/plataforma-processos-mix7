@extends('layouts.app')

@section('title', 'Ativar convite · Plataforma Mix7')

@section('body')
<main class="auth-shell">
    <section class="auth-card">
        <p class="eyebrow">Convite da equipe</p>
        <h1 class="heading">Ative sua conta</h1>
        <p class="subheading">Olá, {{ $invitation->name }}. Você foi convidado(a) como {{ $invitation->role->label() }}. Crie sua senha para continuar.</p>
        @include('partials.flash')
        @error('invitation')<p class="error" role="alert">{{ $message }}</p>@enderror
        <form method="post" action="{{ route('team-invitations.accept', $token) }}" class="auth-form">@csrf
            <label class="field">Nova senha<input name="password" type="password" minlength="12" maxlength="200" required autocomplete="new-password"><span class="field-help">Use pelo menos 12 caracteres.</span>@error('password')<span class="error">{{ $message }}</span>@enderror</label>
            <label class="field">Confirme a senha<input name="password_confirmation" type="password" minlength="12" maxlength="200" required autocomplete="new-password"></label>
            <button class="primary-button" type="submit">Ativar conta</button>
        </form>
    </section>
</main>
<style>.auth-shell{min-height:100vh;display:grid;place-items:center;padding:24px;background:#f5f6f5}.auth-card{width:min(100%,480px);padding:34px;background:#fff;border:1px solid #e3e9e8;border-radius:20px;box-shadow:0 18px 50px #204b6112}.auth-card .heading{font-size:28px}.auth-form{display:grid;gap:18px;margin-top:24px}.auth-form .field{display:grid;gap:8px;color:#40565e;font-size:13px;font-weight:650}.auth-form input{width:100%;border:1px solid #d6e0e1;border-radius:11px;padding:12px 13px;font:inherit;font-size:14px;color:#202e35}.auth-form .primary-button{justify-self:start}.error{color:#a33b3b;font-size:12px}</style>
@endsection
