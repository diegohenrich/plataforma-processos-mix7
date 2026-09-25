<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="color-scheme" content="light">
    <title>Revisão do cliente · {{ $reviewLink->demand->organization->name }}</title>
    <style>
        :root{font-family:Inter,ui-sans-serif,system-ui,-apple-system,"Segoe UI",sans-serif;color:#202e35;background:#f5f6f5;font-synthesis:none;text-rendering:optimizeLegibility}*{box-sizing:border-box}body{margin:0;min-height:100vh}.top{height:74px;background:linear-gradient(105deg,#202e35,#204b61);color:#fff;display:flex;align-items:center;padding:0 max(22px,calc((100% - 920px)/2));font-weight:750;letter-spacing:.02em}.top span{margin-left:10px;color:#b8deea}.page{width:min(100% - 36px,760px);margin:38px auto}.card{background:#fff;border:1px solid #e3e9e8;border-radius:20px;padding:30px;margin-bottom:18px;box-shadow:0 12px 38px #204b610a}.eyebrow{color:#52869a;text-transform:uppercase;letter-spacing:.12em;font-size:11px;font-weight:750;margin:0 0 9px}h1{font-size:28px;letter-spacing:-.03em;margin:0;color:#202e35}.muted{color:#718087;font-size:14px;line-height:1.6}.version{display:inline-flex;background:#eaf6fa;border-radius:999px;padding:7px 11px;color:#326c82;font-size:12px;font-weight:700;margin-top:16px}.material{display:flex;align-items:center;justify-content:space-between;gap:16px;padding:17px;border:1px solid #deeaeb;border-radius:14px;background:#f8fcfd}.material strong{font-size:14px}.button{display:inline-flex;align-items:center;justify-content:center;min-height:42px;padding:9px 14px;border-radius:10px;background:#204b61;color:#fff;text-decoration:none;font-size:13px;font-weight:700}.field{display:block;margin-top:17px;color:#3d555e;font-size:13px;font-weight:650}.field input,.field textarea,.field select{display:block;width:100%;margin-top:8px;border:1px solid #d6e0e1;border-radius:11px;padding:12px 13px;font:inherit;font-size:14px;color:#202e35;background:white}.field textarea{min-height:112px;resize:vertical}.field input:focus,.field textarea:focus,.field select:focus{outline:3px solid #8ecde244;border-color:#52869a}.actions{display:flex;flex-wrap:wrap;gap:10px;margin-top:18px}.actions button,.preview-controls button{min-height:44px;padding:10px 15px;border:1px solid #d6e0e1;border-radius:10px;background:#fff;color:#38515b;font:inherit;font-size:13px;font-weight:700;cursor:pointer}.actions button[type=submit][value=approved]{background:#204b61;color:#fff;border-color:#204b61}.notice{padding:13px 15px;border-radius:12px;background:#eaf6fa;color:#326c82;font-size:13px;line-height:1.5;margin:15px 0}.notice.error{background:#fff0ee;color:#963d36}.error-text{color:#a73737;font-size:12px}.responses{display:grid;gap:10px;margin-top:18px}.response{padding:14px;background:#f8faf9;border-radius:12px}.response strong{font-size:13px}.response p{font-size:13px;line-height:1.55;color:#52666e;white-space:pre-wrap;margin:8px 0 0}.response time{font-size:11px;color:#819096}.anchor{margin-top:9px;padding:8px 10px;background:#eaf6fa;border-radius:9px;color:#326c82;font-size:12px;overflow-wrap:anywhere}.anchor-fields[hidden],.material-preview[hidden],.area-marker[hidden]{display:none}.material-preview{position:relative;margin-top:14px;height:430px;border:1px solid #d6e0e1;border-radius:13px;overflow:hidden;background:#edf2f3}.material-preview iframe{display:block;width:100%;height:100%;border:0;background:#fff}.area-marker{position:absolute;inset:0;cursor:crosshair;background:#204b6110;touch-action:manipulation}.preview-controls{display:flex;align-items:center;gap:12px;flex-wrap:wrap;margin-top:10px}.preview-controls button[aria-pressed=true]{background:#204b61;color:#fff}.preview-status{font-size:12px;color:#326c82}.preview-note{margin:8px 0 0;font-size:12px;line-height:1.5;color:#718087}.privacy{font-size:12px;color:#718087;line-height:1.55;margin-top:18px}@media(max-width:560px){.top{height:62px;padding:0 18px}.page{margin:20px auto}.card{padding:21px;border-radius:16px}h1{font-size:24px}.material{align-items:flex-start;flex-direction:column}.actions{display:grid}.actions button{width:100%}.material-preview{height:480px}.preview-controls button{width:100%}}
    </style>
    <style>
        .attachment-preview{margin:14px 0 18px;padding:18px;border:1px solid #deeaeb;border-radius:14px;background:#fff}.attachment-preview h2{font-size:16px;margin:0 0 12px;color:#202e35}.attachment-preview iframe,.attachment-preview video,.attachment-preview img{display:block;width:100%;max-height:680px;border:1px solid #e3e9e8;border-radius:10px;background:#f5f6f5}.attachment-preview iframe{height:620px}.attachment-preview video{height:auto}.attachment-preview img{height:auto;object-fit:contain}.attachment-preview p{font-size:12px;line-height:1.5;color:#718087;margin:10px 0 0}.attachment-preview a{color:#326c82}@media(max-width:560px){.attachment-preview{padding:13px}.attachment-preview iframe{height:62vh;min-height:380px}}
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
        <div class="material"><strong>{{ $reviewLink->material_file_name ?: 'Material enviado para revisão' }}</strong><a class="button" href="{{ $materialUrl }}" target="_blank" rel="noopener noreferrer">Abrir material</a></div>
        @if ($reviewLink->material_file_path)
            <section class="attachment-preview" aria-labelledby="attachment-preview-title">
                <h2 id="attachment-preview-title">Prévia do material</h2>
                @if (str_starts_with((string) $reviewLink->material_mime, 'image/'))
                    <img src="{{ $materialUrl }}" alt="{{ $reviewLink->material_file_name }}">
                @elseif (str_starts_with((string) $reviewLink->material_mime, 'video/'))
                    <video controls preload="metadata" aria-label="{{ $reviewLink->material_file_name }}"><source src="{{ $materialUrl }}" type="{{ $reviewLink->material_mime }}">Seu navegador não reproduz este vídeo. <a href="{{ $materialUrl }}">Abrir material</a>.</video>
                @elseif ($reviewLink->material_mime === 'application/pdf')
                    @include('components.pdf-preview', ['pdfUrl' => $materialUrl, 'pdfName' => $reviewLink->material_file_name])
                @else
                    <p>Este tipo de arquivo não tem prévia nesta tela. Use “Abrir material” ou baixe o arquivo.</p>
                @endif
                <p>Se a prévia não abrir no seu navegador, <a href="{{ $materialUrl }}" target="_blank" rel="noopener noreferrer">abra em outra guia</a> ou <a href="{{ $materialUrl }}?download=1">baixe o arquivo</a>.</p>
            </section>
        @endif
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
                    <div class="anchor-fields" data-anchor="area" hidden><p class="muted">@if ($reviewLink->material_mime === 'application/pdf')Informe as coordenadas do ponto desejado. Para localizar uma página do PDF, escolha “Página do material”.@else Marque o ponto na prévia ou informe as coordenadas. Role a página antes de ativar a marcação. Se o site bloquear a prévia, abra o material em outra guia.@endif</p>@if ($reviewLink->material_mime !== 'application/pdf')<div class="preview-controls"><button id="toggle-area-marker" type="button" aria-pressed="false">Marcar área na prévia</button><span id="preview-status" class="preview-status" aria-live="polite"></span></div><div id="material-preview" class="material-preview" hidden><iframe src="{{ $materialUrl }}" title="Prévia do material para marcar uma área" sandbox="allow-scripts allow-forms" referrerpolicy="no-referrer" loading="lazy"></iframe><div id="area-marker" class="area-marker" role="button" tabindex="0" aria-label="Clique na posição que deseja comentar" hidden></div></div><p class="preview-note">A incorporação depende das regras do site revisado. A tela não injeta código na página; as coordenadas registradas se referem à área visível da prévia.</p>@endif<div style="display:grid;grid-template-columns:1fr 1fr;gap:12px"><label class="field">Horizontal (%)<input name="anchor_x" type="number" min="0" max="100" step="0.1" value="{{ old('anchor_x') }}"></label><label class="field">Vertical (%)<input name="anchor_y" type="number" min="0" max="100" step="0.1" value="{{ old('anchor_y') }}"></label></div></div>
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
@if ($reviewLink->material_mime === 'application/pdf')
    @vite('resources/js/pdf-preview.js')
@endif
<script>
    (() => {
        const select = document.getElementById('anchor-type');
        if (!select) return;
        const preview = document.getElementById('material-preview');
        const marker = document.getElementById('area-marker');
        const markerButton = document.getElementById('toggle-area-marker');
        const status = document.getElementById('preview-status');
        const xInput = document.querySelector('[name="anchor_x"]');
        const yInput = document.querySelector('[name="anchor_y"]');
        const setMarking = (enabled) => {
            marker.hidden = !enabled;
            markerButton.setAttribute('aria-pressed', String(enabled));
            markerButton.textContent = enabled ? 'Cancelar marcação' : 'Marcar área na prévia';
            preview.querySelector('iframe').style.pointerEvents = enabled ? 'none' : 'auto';
            if (enabled) status.textContent = 'Marcação ativa: clique no ponto desejado na prévia.';
        };
        const savePoint = (event) => {
            const bounds = marker.getBoundingClientRect();
            const x = Math.max(0, Math.min(100, ((event.clientX - bounds.left) / bounds.width) * 100));
            const y = Math.max(0, Math.min(100, ((event.clientY - bounds.top) / bounds.height) * 100));
            xInput.value = x.toFixed(1);
            yInput.value = y.toFixed(1);
            setMarking(false);
            status.textContent = `Ponto marcado: X ${x.toFixed(1)}%, Y ${y.toFixed(1)}%.`;
        };
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
            preview.hidden = select.value !== 'area';
            if (select.value !== 'area') setMarking(false);
        };
        select.addEventListener('change', sync);
        markerButton.addEventListener('click', () => setMarking(marker.hidden));
        marker.addEventListener('click', savePoint);
        marker.addEventListener('keydown', (event) => {
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                const bounds = marker.getBoundingClientRect();
                savePoint({clientX: bounds.left + bounds.width / 2, clientY: bounds.top + bounds.height / 2});
            }
        });
        sync();
    })();
</script>
</body>
</html>
