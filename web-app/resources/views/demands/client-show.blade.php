@extends('layouts.app')

@section('title', $demand->title.' · Área do cliente · Mix7')

@section('body')
<div class="shell">
    @include('layouts.navigation', ['active' => 'demands'])
    <main class="main">
        @include('layouts.topbar')
        <div class="content narrow-content">
            <a class="back-link" href="{{ route('demands.index') }}">← Voltar às minhas demandas</a>
            <p class="eyebrow">Área do cliente · Demanda #{{ $demand->id }}</p>
            <h1 class="heading">{{ $demand->title }}</h1>
            <section class="panel client-demand-status">
                <div class="section-heading"><div><h2>Etapa atual</h2><p>A equipe atualizará esta etapa conforme o trabalho avançar.</p></div></div>
                <span class="pill pill-large">{{ $demand->status->label() }}</span>
                <p class="field-help">Atualizada em {{ $demand->updated_at->format('d/m/Y H:i') }}</p>
            </section>
            <section class="panel"><h2>Revisão do material</h2><p>Quando a equipe enviar uma versão para aprovação, você receberá um link seguro por mensagem. Você poderá comentar ou aprovar por esse link, sem precisar entrar nesta conta.</p></section>
            <p class="footnote">Esta área mostra apenas o nome e a etapa das demandas vinculadas à sua conta. Briefing interno, tarefas da equipe, arquivos de referência e histórico interno ficam restritos à Mix7.</p>
        </div>
    </main>
</div>
<style>.client-demand-status{display:grid;gap:12px}.client-demand-status .pill{justify-self:start}.client-demand-status .field-help{margin:0}</style>
@endsection
