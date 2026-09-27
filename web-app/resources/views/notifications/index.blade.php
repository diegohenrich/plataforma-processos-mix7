@extends('layouts.app')

@section('title', 'Notificações · Plataforma Mix7')

@section('body')
<div class="shell">
    @include('layouts.navigation', ['active' => 'notifications'])
    <main class="main">
        @include('layouts.topbar')
        <div class="content narrow-content notification-page">
            <p class="eyebrow">Acompanhamento</p>
            <div class="page-heading"><div><h1 class="heading">Notificações</h1><p class="subheading">Comentários e decisões recentes dos clientes nas demandas da equipe.</p></div>
                @if ($unreadCount > 0)<form method="post" action="{{ route('notifications.read-all') }}">@csrf<button class="secondary-button" type="submit">Marcar todas como lidas</button></form>@endif
            </div>
            @include('partials.flash')
            @if ($notifications->isEmpty())
                <section class="empty-state"><span class="empty-icon" aria-hidden="true">✓</span><h2>Nenhuma notificação ainda</h2><p>Quando um cliente comentar, aprovar ou pedir ajustes, a equipe responsável verá o aviso aqui.</p></section>
            @else
                <section class="notification-list" aria-label="Avisos recebidos">
                    @foreach ($notifications as $notification)
                        <article class="notification-card {{ $notification->read_at ? 'is-read' : 'is-unread' }}">
                            <span class="notification-dot" aria-hidden="true"></span>
                            <div class="notification-content"><p>{{ $notification->data['message'] ?? 'Há uma nova atividade em uma demanda.' }}</p>
                                @if (!empty($notification->data['comment']))<blockquote>{{ $notification->data['comment'] }}</blockquote>@endif
                                <time datetime="{{ $notification->created_at->toISOString() }}">{{ $notification->created_at->format('d/m/Y H:i') }}</time>
                            </div>
                            <a class="secondary-link" href="{{ route('notifications.open', $notification->id) }}">Abrir demanda</a>
                        </article>
                    @endforeach
                </section>
                <div class="pagination-wrap">{{ $notifications->links() }}</div>
            @endif
        </div>
    </main>
</div>
<style>
.notification-list{display:grid;gap:10px}.notification-card{display:flex;align-items:flex-start;gap:13px;padding:16px;background:white;border:1px solid #e3e9e8;border-radius:14px}.notification-card.is-unread{border-color:#a9d7e3;background:#f8fcfd}.notification-dot{flex:0 0 9px;width:9px;height:9px;margin-top:5px;border-radius:50%;background:#39758b}.is-read .notification-dot{background:#d1dcde}.notification-content{min-width:0;flex:1}.notification-content p{margin:0;color:#304952;font-size:13px;line-height:1.5}.notification-content blockquote{margin:8px 0;padding:8px 11px;border-left:3px solid #8ecde2;background:#f4f8f9;color:#52666e;font-size:12px;white-space:pre-wrap;overflow-wrap:anywhere}.notification-content time{display:block;margin-top:7px;color:#819096;font-size:10px}.notification-card>.secondary-link{flex-shrink:0}@media(max-width:640px){.notification-card{flex-wrap:wrap}.notification-card>.secondary-link{margin-left:22px}}
</style>
@endsection
