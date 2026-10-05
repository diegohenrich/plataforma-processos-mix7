@extends('layouts.app')

@section('title', 'Convite indisponível · Plataforma Mix7')

@section('body')
<main class="auth-shell">
    <section class="auth-card">
        <p class="eyebrow">Convite da equipe</p>
        <h1 class="heading">Este convite não está disponível</h1>
        <p class="subheading">O link expirou, foi cancelado ou já foi utilizado. Peça à direção da agência para enviar um novo convite.</p>
        <a class="primary-link" href="{{ route('login') }}">Ir para a entrada</a>
    </section>
</main>
<style>.auth-shell{min-height:100vh;display:grid;place-items:center;padding:24px;background:#f5f6f5}.auth-card{width:min(100%,480px);padding:34px;background:#fff;border:1px solid #e3e9e8;border-radius:20px;box-shadow:0 18px 50px #204b6112}.auth-card .heading{font-size:28px}.auth-card .primary-link{margin-top:22px}</style>
@endsection
