@extends('layouts.app')

@section('title', 'Aprovações · Plataforma Mix7')

@section('body')
<style>
    .approval-list{display:grid;gap:13px}.approval-card{padding:18px 20px;background:#fff;border:1px solid #e3e9e8;border-radius:16px}.approval-card header{display:flex;align-items:flex-start;justify-content:space-between;gap:16px}.approval-title{margin:0;color:#204b61;font-size:15px}.approval-title a{color:inherit;text-decoration:none}.approval-title a:hover{text-decoration:underline}.approval-meta{display:flex;flex-wrap:wrap;gap:7px;margin-top:9px;color:#718087;font-size:11px}.approval-meta span{padding:5px 8px;border-radius:999px;background:#f1f7f8}.approval-state{display:inline-flex;align-items:center;padding:7px 10px;border-radius:999px;background:#eaf6fa;color:#326c82;font-size:11px;font-weight:750;white-space:nowrap}.approval-state.approved{background:#eaf6ed;color:#267050}.approval-state.changes_requested,.approval-state.expired,.approval-state.revoked{background:#fff2ef;color:#9a5148}.approval-responses{display:grid;gap:8px;margin:15px 0 0;padding:13px 14px;border-radius:12px;background:#f8faf9}.approval-responses h2{margin:0 0 2px;color:#52666e;font-size:11px}.approval-response{display:grid;gap:4px;padding-top:8px;border-top:1px solid #e7eeee}.approval-response strong{color:#52666e;font-size:11px}.approval-response p{margin:0;color:#64777d;font-size:12px;line-height:1.5;white-space:pre-wrap;overflow-wrap:anywhere}.approval-response time{color:#8b989c;font-size:10px}.approval-footer{display:flex;justify-content:flex-end;margin-top:14px}.approval-empty{max-width:600px}.approval-help{margin:-8px 0 18px;color:#718087;font-size:12px;line-height:1.55}@media(max-width:650px){.approval-card{padding:15px}.approval-card header{align-items:flex-start;flex-direction:column}.approval-footer .secondary-link{width:100%}}
</style>
<div class="shell">
    @include('layouts.navigation', ['active' => 'demands'])
    <main class="main">
        @include('layouts.topbar')
        <div class="content">
            <div class="page-heading"><div><p class="eyebrow">Espaço de demandas</p><h1 class="heading">Aprovações</h1><p class="subheading">Acompanhe as versões enviadas ao cliente e leia os comentários recebidos.</p></div></div>
            @include('partials.work-tabs', ['workView' => 'approvals'])
            @include('partials.flash')
            <p class="approval-help">Cada versão mantém seu próprio estado. Para criar ou enviar um link, abra a demanda na etapa “Aprovação do cliente”.</p>
            @if ($links->isEmpty())
                <section class="empty-state approval-empty"><span class="empty-icon" aria-hidden="true">✓</span><h2>Nenhum link de revisão enviado</h2><p>Quando a equipe enviar uma versão ao cliente, ela aparecerá aqui com comentários, pedidos de ajuste ou aprovação.</p><a class="primary-link" href="{{ route('demands.index') }}">Ver demandas</a></section>
            @else
                <section class="approval-list" aria-label="Links de revisão por versão">
                    @foreach ($links as $link)
                        <article class="approval-card">
                            <header>
                                <div><h2 class="approval-title"><a href="{{ route('demands.show', $link->demand) }}">{{ $link->demand->title }}</a></h2><div class="approval-meta"><span>Versão {{ $link->version }}</span><span>Enviado por {{ $link->creator->name }}</span><span>{{ $link->created_at->format('d/m/Y H:i') }}</span><span>Válido até {{ $link->expires_at->format('d/m/Y H:i') }}</span></div></div>
                                <span class="approval-state {{ $link->queue_state }}">{{ $link->queue_label }}</span>
                            </header>
                            @if ($link->responses->isNotEmpty())
                                <section class="approval-responses" aria-label="Últimas respostas do cliente"><h2>Últimas respostas</h2>
                                    @foreach ($link->responses as $response)
                                        <article class="approval-response">
                                            <strong>{{ $response->reviewer_name }} · {{ match($response->type) {'approved' => 'Aprovou', 'changes_requested' => 'Pediu ajustes', 'annotation' => 'Comentou com marcação', default => 'Comentou'} }}</strong>
                                            @if ($response->comment)
                                                <p>{{ $response->comment }}</p>
                                            @endif
                                            @if ($response->anchor_type)
                                                <p>{{ match($response->anchor_type) {'text' => 'Trecho: '.($response->anchor_data['text'] ?? ''), 'time' => 'Vídeo em '.($response->anchor_data['time'] ?? ''), 'page' => 'Página '.($response->anchor_data['page'] ?? ''), 'area' => 'Marcação visual na página', default => 'Marcação no material'} }}</p>
                                            @endif
                                            <time datetime="{{ $response->created_at->toISOString() }}">{{ $response->created_at->format('d/m/Y H:i') }}</time>
                                        </article>
                                    @endforeach
                                </section>
                            @endif
                            <footer class="approval-footer"><a class="secondary-link" href="{{ route('demands.show', $link->demand) }}">Abrir demanda e histórico</a></footer>
                        </article>
                    @endforeach
                </section>
            @endif
        </div>
    </main>
</div>
@endsection
