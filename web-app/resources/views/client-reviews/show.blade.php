<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="color-scheme" content="light">
    <title>Revisão do cliente · {{ $reviewLink->demand->organization->name }}</title>
    <style>
        :root{font-family:Inter,ui-sans-serif,system-ui,-apple-system,"Segoe UI",sans-serif;color:#202e35;background:#f5f6f5;font-synthesis:none;text-rendering:optimizeLegibility}*{box-sizing:border-box}body{margin:0;min-height:100vh}.top{height:74px;background:linear-gradient(105deg,#202e35,#204b61);color:#fff;display:flex;align-items:center;padding:0 max(22px,calc((100% - 920px)/2));font-weight:750;letter-spacing:.02em}.top span{margin-left:10px;color:#b8deea}.page{width:min(100% - 36px,760px);margin:38px auto}.card{background:#fff;border:1px solid #e3e9e8;border-radius:20px;padding:30px;margin-bottom:18px;box-shadow:0 12px 38px #204b610a}.eyebrow{color:#52869a;text-transform:uppercase;letter-spacing:.12em;font-size:11px;font-weight:750;margin:0 0 9px}h1{font-size:28px;letter-spacing:-.03em;margin:0;color:#202e35}.muted{color:#718087;font-size:14px;line-height:1.6}.version{display:inline-flex;background:#eaf6fa;border-radius:999px;padding:7px 11px;color:#326c82;font-size:12px;font-weight:700;margin-top:16px}.material{display:flex;align-items:center;justify-content:space-between;gap:16px;padding:17px;border:1px solid #deeaeb;border-radius:14px;background:#f8fcfd}.material strong{font-size:14px}.button{display:inline-flex;align-items:center;justify-content:center;min-height:42px;padding:9px 14px;border-radius:10px;background:#204b61;color:#fff;text-decoration:none;font-size:13px;font-weight:700}.field{display:block;margin-top:17px;color:#3d555e;font-size:13px;font-weight:650}.field input,.field textarea{display:block;width:100%;margin-top:8px;border:1px solid #d6e0e1;border-radius:11px;padding:12px 13px;font:inherit;font-size:14px;color:#202e35}.field textarea{min-height:112px;resize:vertical}.field input:focus,.field textarea:focus{outline:3px solid #8ecde244;border-color:#52869a}.actions{display:flex;flex-wrap:wrap;gap:10px;margin-top:18px}.actions button{min-height:44px;padding:10px 15px;border:1px solid #d6e0e1;border-radius:10px;background:#fff;color:#38515b;font:inherit;font-size:13px;font-weight:700;cursor:pointer}.actions button[type=submit][value=approved]{background:#204b61;color:#fff;border-color:#204b61}.notice{padding:13px 15px;border-radius:12px;background:#eaf6fa;color:#326c82;font-size:13px;line-height:1.5;margin:15px 0}.notice.error{background:#fff0ee;color:#963d36}.error-text{color:#a73737;font-size:12px}.responses{display:grid;gap:10px;margin-top:18px}.response{padding:14px;background:#f8faf9;border-radius:12px}.response strong{font-size:13px}.response p{font-size:13px;line-height:1.55;color:#52666e;white-space:pre-wrap;margin:8px 0 0}.response time{font-size:11px;color:#819096}.privacy{font-size:12px;color:#718087;line-height:1.55;margin-top:18px}@media(max-width:560px){.top{height:62px;padding:0 18px}.page{margin:20px auto}.card{padding:21px;border-radius:16px}h1{font-size:24px}.material{align-items:flex-start;flex-direction:column}.actions{display:grid}.actions button{width:100%}}
    </style>
</head>
<body>
<header class="top">MIX7 <span>· revisão do cliente</span></header>
<main class="page">
    <section class="card">
        <p class="eyebrow">{{ $reviewLink->demand->organization->name }} · Demanda {{ $reviewLink->demand_id }}</p>
        <h1>{{ $reviewLink->demand->title }}</h1>
        <p class="muted">Revise o material desta entrega. Você pode deixar um comentário, aprovar ou pedir ajustes.</p>
        <span class="version">Versão {{ $reviewLink->version }}</span>
    </section>
    <section class="card">
        <div class="material"><strong>Material enviado para revisão</strong><a class="button" href="{{ $reviewLink->material_url }}" target="_blank" rel="noopener noreferrer">Abrir material</a></div>
        @if (session('success'))<div class="notice">{{ session('success') }}</div>@endif
        @if ($errors->any())<div class="notice error">Confira os campos e tente novamente.</div>@endif
        @if ($hasDecision)
            <div class="notice">Esta versão já recebeu uma decisão final. As respostas abaixo permanecem registradas.</div>
        @else
            <form method="post" action="{{ route('client-reviews.respond', ['token' => request()->route('token')]) }}">@csrf
                <label class="field">Seu nome<input name="reviewer_name" required minlength="2" maxlength="120" autocomplete="name" value="{{ old('reviewer_name') }}"></label>
                <label class="field">Comentário<textarea name="comment" maxlength="5000" placeholder="Conte o que devemos saber. Para comentários curtos, escolha ‘Enviar comentário’. Para pedir ajustes, descreva o que precisa mudar.">{{ old('comment') }}</textarea></label>
                @error('reviewer_name')<span class="error-text">{{ $message }}</span>@enderror @error('comment')<span class="error-text">{{ $message }}</span>@enderror @error('type')<span class="error-text">{{ $message }}</span>@enderror
                <div class="actions"><button name="type" value="comment" type="submit">Enviar comentário</button><button name="type" value="changes_requested" type="submit">Pedir ajustes</button><button name="type" value="approved" type="submit">Aprovar entrega</button></div>
            </form>
        @endif
        <p class="privacy">Seu nome e sua resposta serão registrados junto desta versão para que a equipe saiba o que você decidiu.</p>
    </section>
    @if ($reviewLink->responses->isNotEmpty())
        <section class="card"><p class="eyebrow">Histórico desta versão</p><div class="responses">@foreach ($reviewLink->responses as $response)<article class="response"><strong>{{ $response->reviewer_name }} · {{ match($response->type) {'approved' => 'Aprovou', 'changes_requested' => 'Pediu ajustes', default => 'Comentou'} }}</strong><p>{{ $response->comment ?: 'Sem comentário adicional.' }}</p><time>{{ $response->created_at->format('d/m/Y H:i') }}</time></article>@endforeach</div></section>
    @endif
</main>
</body>
</html>
