<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="color-scheme" content="light">
    <title>Revisão do cliente · {{ $reviewLink->demand->organization->name }}</title>
    <style>
        :root{font-family:Inter,ui-sans-serif,system-ui,-apple-system,"Segoe UI",sans-serif;color:#202e35;background:#f5f6f5;font-synthesis:none;text-rendering:optimizeLegibility}*{box-sizing:border-box}body{margin:0;min-height:100vh}.top{height:74px;background:linear-gradient(105deg,#202e35,#204b61);color:#fff;display:flex;align-items:center;padding:0 max(22px,calc((100% - 920px)/2));font-weight:750;letter-spacing:.02em}.top span{margin-left:10px;color:#b8deea}.page{width:min(100% - 36px,760px);margin:38px auto}.card{background:#fff;border:1px solid #e3e9e8;border-radius:20px;padding:30px;margin-bottom:18px;box-shadow:0 12px 38px #204b610a}.eyebrow{color:#52869a;text-transform:uppercase;letter-spacing:.12em;font-size:11px;font-weight:750;margin:0 0 9px}h1{font-size:28px;letter-spacing:-.03em;margin:0;color:#202e35}.muted{color:#718087;font-size:14px;line-height:1.6}.version{display:inline-flex;background:#eaf6fa;border-radius:999px;padding:7px 11px;color:#326c82;font-size:12px;font-weight:700;margin-top:16px}.material{display:flex;align-items:center;justify-content:space-between;gap:16px;padding:17px;border:1px solid #deeaeb;border-radius:14px;background:#f8fcfd}.material strong{font-size:14px}.button{display:inline-flex;align-items:center;justify-content:center;min-height:42px;padding:9px 14px;border-radius:10px;background:#204b61;color:#fff;text-decoration:none;font-size:13px;font-weight:700}.field{display:block;margin-top:17px;color:#3d555e;font-size:13px;font-weight:650}.field input,.field textarea,.field select{display:block;width:100%;margin-top:8px;border:1px solid #d6e0e1;border-radius:11px;padding:12px 13px;font:inherit;font-size:14px;color:#202e35;background:white}.field textarea{min-height:112px;resize:vertical}.field input:focus,.field textarea:focus,.field select:focus{outline:3px solid #8ecde244;border-color:#52869a}.actions{display:flex;flex-wrap:wrap;gap:10px;margin-top:18px}.actions button{min-height:44px;padding:10px 15px;border:1px solid #d6e0e1;border-radius:10px;background:#fff;color:#38515b;font:inherit;font-size:13px;font-weight:700;cursor:pointer}.actions button[type=submit][value=approved]{background:#204b61;color:#fff;border-color:#204b61}.notice{padding:13px 15px;border-radius:12px;background:#eaf6fa;color:#326c82;font-size:13px;line-height:1.5;margin:15px 0}.notice.error{background:#fff0ee;color:#963d36}.error-text{color:#a73737;font-size:12px}.responses{display:grid;gap:10px;margin-top:18px}.response{padding:14px;background:#f8faf9;border-radius:12px}.response strong{font-size:13px}.response p{font-size:13px;line-height:1.55;color:#52666e;white-space:pre-wrap;margin:8px 0 0}.response time{font-size:11px;color:#819096}.anchor{margin-top:9px;padding:8px 10px;background:#eaf6fa;border-radius:9px;color:#326c82;font-size:12px;overflow-wrap:anywhere}.anchor-fields[hidden]{display:none}.privacy{font-size:12px;color:#718087;line-height:1.55;margin-top:18px}@media(max-width:560px){.top{height:62px;padding:0 18px}.page{margin:20px auto}.card{padding:21px;border-radius:16px}h1{font-size:24px}.material{align-items:flex-start;flex-direction:column}.actions{display:grid}.actions button{width:100%}}
    </style>
</head>
<body>
<header class="top">MIX7 <span>· revisão do cliente</span></header>
<main class="page">
    <section class="card">
        <p class="eyebrow">{{ $reviewLink->demand->organization->name }} · Demanda {{ $reviewLink->demand_id }}</p>
        <h1>{{ $reviewLink->demand->title }}</h1>
        <p class="muted">Revise o material desta entrega. Você pode comentar, anotar um ponto específico, aprovar ou pedir ajustes.</p>
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
                <fieldset style="border:0;padding:0;margin:0"><legend class="field">Ancorar este comentário em</legend><label class="field"><span style="position:absolute;left:-9999px">Tipo de referência</span><select id="anchor-type" name="anchor_type"><option value="">Sem localização específica</option><option value="text" @selected(old('anchor_type') === 'text')>Trecho de texto</option><option value="area" @selected(old('anchor_type') === 'area')>Área da página (posição %)</option><option value="time" @selected(old('anchor_type') === 'time')>Instante de vídeo</option><option value="page" @selected(old('anchor_type') === 'page')>Página do material</option></select></label>
                    <div class="anchor-fields" data-anchor="text" hidden><label class="field">Trecho do material<textarea name="anchor_text" maxlength="1000" placeholder="Cole o texto exato ao qual o comentário se refere">{{ old('anchor_text') }}</textarea></label></div>
                    <div class="anchor-fields" data-anchor="area" hidden><p class="muted">Informe a posição aproximada no material, em porcentagem (0 a 100). As coordenadas ficam ligadas a esta versão.</p><div style="display:grid;grid-template-columns:1fr 1fr;gap:12px"><label class="field">Horizontal (%)<input name="anchor_x" type="number" min="0" max="100" step="0.1" value="{{ old('anchor_x') }}"></label><label class="field">Vertical (%)<input name="anchor_y" type="number" min="0" max="100" step="0.1" value="{{ old('anchor_y') }}"></label></div></div>
                    <div class="anchor-fields" data-anchor="time" hidden><label class="field">Instante do vídeo (HH:MM:SS)<input name="anchor_time" type="text" inputmode="numeric" pattern="[0-9]{2}:[0-5][0-9]:[0-5][0-9]" placeholder="00:01:25" value="{{ old('anchor_time') }}"></label></div>
                    <div class="anchor-fields" data-anchor="page" hidden><label class="field">Página do material<input name="anchor_page" type="number" min="1" step="1" placeholder="1" value="{{ old('anchor_page') }}"></label></div>
                </fieldset>
                @error('reviewer_name')<span class="error-text">{{ $message }}</span>@enderror @error('comment')<span class="error-text">{{ $message }}</span>@enderror @error('type')<span class="error-text">{{ $message }}</span>@enderror @error('anchor_type')<span class="error-text">{{ $message }}</span>@enderror
                <div class="actions"><button name="type" value="comment" data-review-action type="submit">Enviar comentário</button><button name="type" value="changes_requested" data-review-action type="submit">Pedir ajustes</button><button name="type" value="approved" data-review-action type="submit">Aprovar entrega</button><button name="type" value="annotation" data-annotation-action type="submit" hidden>Enviar anotação</button></div>
            </form>
        @endif
        <p class="privacy">Seu nome e sua resposta serão registrados junto desta versão para que a equipe saiba o que você decidiu.</p>
    </section>
    @if ($reviewLink->responses->isNotEmpty())
        <section class="card"><p class="eyebrow">Histórico desta versão</p><div class="responses">@foreach ($reviewLink->responses as $response)<article class="response"><strong>{{ $response->reviewer_name }} · {{ match($response->type) {'approved' => 'Aprovou', 'changes_requested' => 'Pediu ajustes', 'annotation' => 'Anotou no material', default => 'Comentou'} }}</strong>@if ($response->anchor_type && $response->anchor_data)<div class="anchor">{{ match($response->anchor_type) {'text' => 'Trecho: “'.($response->anchor_data['text'] ?? '').'”', 'area' => 'Área aproximada: X '.($response->anchor_data['x'] ?? '?').'%, Y '.($response->anchor_data['y'] ?? '?').'%', 'time' => 'Vídeo em '.($response->anchor_data['time'] ?? ''), 'page' => 'Página '.($response->anchor_data['page'] ?? ''), default => ''} }} @if (!empty($response->anchor_data['url'])) · {{ $response->anchor_data['url'] }} @endif</div>@endif<p>{{ $response->comment ?: 'Sem comentário adicional.' }}</p><time>{{ $response->created_at->format('d/m/Y H:i') }}</time></article>@endforeach</div></section>
    @endif
</main>
<script>
    (() => {
        const select = document.getElementById('anchor-type');
        if (!select) return;
        const sync = () => {
            document.querySelectorAll('[data-anchor]').forEach((section) => {
                const active = section.dataset.anchor === select.value;
                section.hidden = !active;
                section.querySelectorAll('input,textarea').forEach((field) => {
                    field.required = active;
                    field.disabled = !active;
                });
            });
            select.closest('form').querySelectorAll('[data-review-action]').forEach((button) => {
                button.disabled = Boolean(select.value);
            });
            select.closest('form').querySelector('[data-annotation-action]').hidden = !select.value;
        };
        select.addEventListener('change', sync);
        sync();
    })();
</script>
</body>
</html>
