<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="color-scheme" content="light">
    <title>Revisão do cliente · {{ $reviewLink->demand->organization->name }}</title>
    <style>
        :root{font-family:Inter,ui-sans-serif,system-ui,-apple-system,"Segoe UI",sans-serif;color:#202e35;background:#f5f6f5;font-synthesis:none;text-rendering:optimizeLegibility}*{box-sizing:border-box}body{margin:0;min-height:100vh}.top{height:74px;background:linear-gradient(105deg,#202e35,#204b61);color:#fff;display:flex;align-items:center;padding:0 max(22px,calc((100% - 920px)/2));font-weight:750;letter-spacing:.02em}.top span{margin-left:10px;color:#b8deea}.page{width:min(100% - 36px,760px);margin:38px auto}.card{background:#fff;border:1px solid #e3e9e8;border-radius:20px;padding:30px;margin-bottom:18px;box-shadow:0 12px 38px #204b610a}.eyebrow{color:#52869a;text-transform:uppercase;letter-spacing:.12em;font-size:11px;font-weight:750;margin:0 0 9px}h1{font-size:28px;letter-spacing:-.03em;margin:0;color:#202e35}.muted{color:#718087;font-size:14px;line-height:1.6}.version{display:inline-flex;background:#eaf6fa;border-radius:999px;padding:7px 11px;color:#326c82;font-size:12px;font-weight:700;margin-top:16px}.material{display:flex;align-items:center;justify-content:space-between;gap:16px;padding:17px;border:1px solid #deeaeb;border-radius:14px;background:#f8fcfd}.material strong{font-size:14px}.button{display:inline-flex;align-items:center;justify-content:center;min-height:42px;padding:9px 14px;border-radius:10px;background:#204b61;color:#fff;text-decoration:none;font-size:13px;font-weight:700}.field{display:block;margin-top:17px;color:#3d555e;font-size:13px;font-weight:650}.field input,.field textarea,.field select{display:block;width:100%;margin-top:8px;border:1px solid #d6e0e1;border-radius:11px;padding:12px 13px;font:inherit;font-size:14px;color:#202e35;background:white}.field textarea{min-height:112px;resize:vertical}.field input:focus,.field textarea:focus,.field select:focus{outline:3px solid #8ecde244;border-color:#52869a}.actions{display:flex;flex-wrap:wrap;gap:10px;margin-top:18px}.actions button,.preview-controls button{min-height:44px;padding:10px 15px;border:1px solid #d6e0e1;border-radius:10px;background:#fff;color:#38515b;font:inherit;font-size:13px;font-weight:700;cursor:pointer}.actions button[type=submit][value=approved]{background:#204b61;color:#fff;border-color:#204b61}.notice{padding:13px 15px;border-radius:12px;background:#eaf6fa;color:#326c82;font-size:13px;line-height:1.5;margin:15px 0}.notice.error{background:#fff0ee;color:#963d36}.error-text{color:#a73737;font-size:12px}.responses{display:grid;gap:10px;margin-top:18px}.response{padding:14px;background:#f8faf9;border-radius:12px}.response strong{font-size:13px}.response p{font-size:13px;line-height:1.55;color:#52666e;white-space:pre-wrap;margin:8px 0 0}.response time{font-size:11px;color:#819096}.anchor{margin-top:9px;padding:8px 10px;background:#eaf6fa;border-radius:9px;color:#326c82;font-size:12px;overflow-wrap:anywhere}.anchor-fields[hidden],.material-preview[hidden],.area-marker[hidden],.selection-rectangle[hidden]{display:none}.material-preview{position:relative;margin-top:14px;height:430px;border:1px solid #d6e0e1;border-radius:13px;overflow:hidden;background:#edf2f3}.material-preview iframe{display:block;width:100%;height:100%;border:0;background:#fff}.area-marker{position:absolute;inset:0;cursor:crosshair;background:#204b6108;touch-action:none;user-select:none}.selection-rectangle{position:absolute;border:2px solid #204b61;background:#8ecde255;pointer-events:none}.preview-controls{display:flex;align-items:center;gap:12px;flex-wrap:wrap;margin-top:10px}.preview-controls button[aria-pressed=true]{background:#204b61;color:#fff}.preview-status{font-size:12px;color:#326c82}.preview-note{margin:8px 0 0;font-size:12px;line-height:1.5;color:#718087}.privacy{font-size:12px;color:#718087;line-height:1.55;margin-top:18px}@media(max-width:560px){.top{height:62px;padding:0 18px}.page{margin:20px auto}.card{padding:21px;border-radius:16px}h1{font-size:24px}.material{align-items:flex-start;flex-direction:column}.actions{display:grid}.actions button{width:100%}.material-preview{height:480px}.preview-controls button{width:100%}}
    </style>
    <style>
        .attachment-preview{margin:14px 0 18px;padding:18px;border:1px solid #deeaeb;border-radius:14px;background:#fff}.attachment-preview h2{font-size:16px;margin:0 0 12px;color:#202e35}.attachment-preview iframe,.attachment-preview video,.attachment-preview img{display:block;width:100%;max-height:680px;border:1px solid #e3e9e8;border-radius:10px;background:#f5f6f5}.attachment-preview iframe{height:620px}.attachment-preview video{height:auto}.attachment-preview img{height:auto;object-fit:contain}.attachment-preview p{font-size:12px;line-height:1.5;color:#718087;margin:10px 0 0}.attachment-preview a{color:#326c82}@media(max-width:560px){.attachment-preview{padding:13px}.attachment-preview iframe{height:62vh;min-height:380px}}
    </style>
    <style>
        .timestamp-button{margin-top:10px;min-height:42px;padding:10px 13px;border:1px solid #d6e0e1;border-radius:10px;background:#fff;color:#38515b;font:inherit;font-size:13px;font-weight:700;cursor:pointer}.timestamp-button:focus-visible{outline:3px solid #8ecde288;outline-offset:2px}.drawing-overlay{position:absolute;inset:0;width:100%;height:100%;pointer-events:none}.drawing-overlay polyline[hidden]{display:none}.drawing-preview{display:block;width:min(100%,320px);height:130px;margin-top:10px;border:1px solid #d6e0e1;border-radius:9px;background:#f3fbfd}.drawing-preview polyline{fill:none;stroke:#204b61;stroke-width:1.2;stroke-linecap:round;stroke-linejoin:round;vector-effect:non-scaling-stroke}
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
                    <video id="review-video" controls preload="metadata" aria-label="{{ $reviewLink->material_file_name }}"><source src="{{ $materialUrl }}" type="{{ $reviewLink->material_mime }}">Seu navegador não reproduz este vídeo. <a href="{{ $materialUrl }}">Abrir material</a>.</video>
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
                    <div class="anchor-fields" data-anchor="text" hidden>@if ($reviewLink->material_url)<p class="preview-note">Abra o site, selecione e copie o trecho. Volte aqui e use o botão para colar no comentário.</p><p><a class="button" href="{{ $reviewLink->material_url }}" target="_blank" rel="noopener noreferrer nofollow" referrerpolicy="no-referrer">Abrir site para selecionar texto</a></p>@endif<label class="field">Trecho do material<textarea name="anchor_text" maxlength="1000" placeholder="Cole o texto exato ao qual o comentário se refere">{{ old('anchor_text') }}</textarea></label><button id="paste-selected-text" class="button paste-selection-button" type="button">Colar trecho copiado</button><span id="selection-paste-status" class="preview-note" role="status" aria-live="polite">Se a leitura da área de transferência estiver bloqueada, clique no campo e use Ctrl+V.</span></div>
                    <div class="anchor-fields" data-anchor="area" hidden><p class="muted">@if ($reviewLink->material_mime === 'application/pdf')Informe o ponto central e, se necessário, largura e altura aproximadas. Para localizar uma página do PDF, escolha “Página do material”.@else Marque uma área retangular ou faça um rabisco livre sobre a prévia. Se o site bloquear a incorporação, abra o material em outra guia e use as coordenadas manuais.@endif</p>@if ($reviewLink->material_mime !== 'application/pdf')<div class="preview-controls"><button id="toggle-area-marker" type="button" aria-pressed="false">Selecionar retângulo</button><button id="toggle-drawing-marker" type="button" aria-pressed="false">Rabiscar livremente</button><span id="preview-status" class="preview-status" aria-live="polite"></span></div><div id="material-preview" class="material-preview" hidden><iframe src="{{ $materialUrl }}" title="Prévia do material para anotar uma área" sandbox="allow-scripts allow-forms" referrerpolicy="no-referrer" loading="lazy"></iframe><span id="selection-rectangle" class="selection-rectangle" hidden aria-hidden="true"></span><svg class="drawing-overlay" viewBox="0 0 100 100" preserveAspectRatio="none" aria-hidden="true"><polyline id="drawing-path" points="" hidden></polyline></svg><div id="area-marker" class="area-marker" role="button" tabindex="0" aria-label="Pressione Enter para marcar o centro; use o ponteiro para selecionar ou desenhar" hidden></div></div><p class="preview-note">O desenho e as coordenadas referem-se à área visível da prévia. A incorporação depende das regras do site; esta tela não injeta código nele.</p>@endif<label class="field" style="position:absolute;left:-9999px">Traçado do rabisco<input id="anchor-path" name="anchor_path" type="hidden" data-optional="true" value="{{ old('anchor_path') }}"></label><div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:0 12px"><label class="field">Centro horizontal (%)<input name="anchor_x" type="number" min="0" max="100" step="0.1" value="{{ old('anchor_x') }}"></label><label class="field">Centro vertical (%)<input name="anchor_y" type="number" min="0" max="100" step="0.1" value="{{ old('anchor_y') }}"></label><label class="field">Largura (%)<input name="anchor_width" type="number" data-optional="true" min="0" max="100" step="0.1" value="{{ old('anchor_width') }}"></label><label class="field">Altura (%)<input name="anchor_height" type="number" data-optional="true" min="0" max="100" step="0.1" value="{{ old('anchor_height') }}"></label></div></div>
                    <div class="anchor-fields" data-anchor="time" hidden><label class="field">Instante do vídeo (HH:MM:SS)<input name="anchor_time" type="text" inputmode="numeric" pattern="[0-9]{2}:[0-5][0-9]:[0-5][0-9]" placeholder="00:01:25" value="{{ old('anchor_time') }}"></label>@if ($reviewLink->material_file_path && str_starts_with((string) $reviewLink->material_mime, 'video/'))<button id="use-video-time" class="timestamp-button" type="button">Usar instante pausado</button><p class="preview-note" id="video-time-status" aria-live="polite">Pause o vídeo no ponto desejado e use este botão para preencher o instante.</p>@endif</div>
                    <div class="anchor-fields" data-anchor="page" hidden><label class="field">Página do material<input name="anchor_page" type="number" min="1" step="1" placeholder="1" value="{{ old('anchor_page') }}"></label></div>
                </fieldset>
                @error('anchor_x')<span class="error-text">Informe o centro horizontal entre 0 e 100%.</span>@enderror @error('anchor_y')<span class="error-text">Informe o centro vertical entre 0 e 100%.</span>@enderror @error('anchor_width')<span class="error-text">Informe uma largura entre 0 e 100% e preencha também a altura.</span>@enderror @error('anchor_height')<span class="error-text">Informe uma altura entre 0 e 100% e preencha também a largura.</span>@enderror @error('anchor_path')<span class="error-text">{{ $message }}</span>@enderror
                @error('reviewer_name')<span class="error-text">{{ $message }}</span>@enderror @error('comment')<span class="error-text">{{ $message }}</span>@enderror @error('type')<span class="error-text">{{ $message }}</span>@enderror @error('anchor_type')<span class="error-text">{{ $message }}</span>@enderror
                <div class="actions"><button name="type" value="comment" data-review-action type="submit">Enviar comentário</button><button name="type" value="changes_requested" data-review-action type="submit">Pedir ajustes</button><button name="type" value="approved" data-review-action type="submit">Aprovar entrega</button><button name="type" value="annotation" data-annotation-action type="submit" hidden>Enviar anotação</button></div>
            </form>
        @endif
        <p class="privacy">Seu nome e sua resposta serão registrados junto desta versão para que a equipe saiba o que você decidiu.</p>
    </section>
    @if ($reviewLink->responses->isNotEmpty())
        <section class="card"><p class="eyebrow">Histórico desta versão</p><div class="responses">@foreach ($reviewLink->responses as $response)<article class="response"><strong>{{ $response->reviewer_name }} · {{ match($response->type) {'approved' => 'Aprovou', 'changes_requested' => 'Pediu ajustes', 'annotation' => 'Anotou no material', default => 'Comentou'} }}</strong>@if ($response->anchor_type && $response->anchor_data)<div class="anchor">{{ match($response->anchor_type) {'text' => 'Trecho: “'.($response->anchor_data['text'] ?? '').'”', 'area' => 'Área: centro X '.($response->anchor_data['x'] ?? '?').'%, centro Y '.($response->anchor_data['y'] ?? '?').'%' . (isset($response->anchor_data['width'], $response->anchor_data['height']) ? ', largura '.$response->anchor_data['width'].'%, altura '.$response->anchor_data['height'].'%' : ''), 'time' => 'Vídeo em '.($response->anchor_data['time'] ?? ''), 'page' => 'Página '.($response->anchor_data['page'] ?? ''), default => ''} }} @if (!empty($response->anchor_data['url'])) · {{ $response->anchor_data['url'] }} @endif</div>@endif<p>{{ $response->comment ?: 'Sem comentário adicional.' }}</p><time>{{ $response->created_at->format('d/m/Y H:i') }}</time></article>@endforeach</div></section>
    @endif
    @if ($reviewLink->responses->contains(fn ($response) => ! empty($response->anchor_data['path'] ?? [])))
        <section class="card"><p class="eyebrow">Rabiscos ligados aos comentários</p><div class="responses">@foreach ($reviewLink->responses as $response)@if (!empty($response->anchor_data['path'] ?? []))<article class="response"><strong>{{ $response->reviewer_name }} · versão {{ $reviewLink->version }}</strong><p>{{ $response->comment }}</p><x-anchor-drawing :points="$response->anchor_data['path']" /></article>@endif @endforeach</div></section>
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
        const rectangle = document.getElementById('selection-rectangle');
        const markerButton = document.getElementById('toggle-area-marker');
        const drawingButton = document.getElementById('toggle-drawing-marker');
        const drawing = document.getElementById('drawing-path');
        const pathInput = document.getElementById('anchor-path');
        const videoTimeButton = document.getElementById('use-video-time');
        const reviewVideo = document.getElementById('review-video');
        const videoTimeInput = document.querySelector('[name="anchor_time"]');
        const videoTimeStatus = document.getElementById('video-time-status');
        const pasteSelectedTextButton = document.getElementById('paste-selected-text');
        const anchorText = document.querySelector('[name="anchor_text"]');
        const selectionPasteStatus = document.getElementById('selection-paste-status');
        const status = document.getElementById('preview-status');
        const xInput = document.querySelector('[name="anchor_x"]');
        const yInput = document.querySelector('[name="anchor_y"]');
        const widthInput = document.querySelector('[name="anchor_width"]');
        const heightInput = document.querySelector('[name="anchor_height"]');
        let dragStart = null;
        let activePath = [];
        let markingMode = null;
        pasteSelectedTextButton?.addEventListener('click', async () => {
            try {
                const copiedText = await navigator.clipboard.readText();
                if (!copiedText.trim()) {
                    selectionPasteStatus.textContent = 'A área de transferência está vazia. Copie um trecho do site primeiro.';
                    return;
                }

                anchorText.value = copiedText.slice(0, 1000);
                anchorText.dispatchEvent(new Event('input', {bubbles: true}));
                selectionPasteStatus.textContent = copiedText.length > 1000
                    ? 'O trecho foi colado e limitado aos primeiros 1.000 caracteres.'
                    : 'Trecho colado. Confira o texto antes de enviar.';
            } catch {
                selectionPasteStatus.textContent = 'O navegador bloqueou a leitura da área de transferência. Clique no campo e use Ctrl+V.';
                anchorText.focus();
            }
        });
        const setMarking = (mode, preserveOverlay = false) => {
            if (!marker || !markerButton || !drawingButton || !preview) return;
            const startingMode = Boolean(mode) && mode !== markingMode;
            if (startingMode) {
                pathInput.value = '';
                [xInput, yInput, widthInput, heightInput].forEach((field) => { field.value = ''; });
                rectangle.hidden = true;
                drawing.hidden = true;
            }
            markingMode = mode;
            dragStart = null;
            activePath = [];
            marker.hidden = !mode;
            if (!mode && !preserveOverlay) {
                rectangle.hidden = true;
                drawing.hidden = true;
            }
            markerButton.setAttribute('aria-pressed', String(mode === 'rectangle'));
            drawingButton.setAttribute('aria-pressed', String(mode === 'drawing'));
            markerButton.textContent = mode === 'rectangle' ? 'Cancelar seleção' : 'Selecionar retângulo';
            drawingButton.textContent = mode === 'drawing' ? 'Cancelar rabisco' : 'Rabiscar livremente';
            preview.querySelector('iframe').style.pointerEvents = mode ? 'none' : 'auto';
            if (mode === 'rectangle') {
                status.textContent = 'Arraste sobre a prévia para selecionar uma área; um clique marca um ponto.';
            } else if (mode === 'drawing') {
                status.textContent = 'Mantenha o botão pressionado e desenhe sobre o trecho que deseja comentar.';
            }
        };
        const relativePoint = (event) => {
            const bounds = marker.getBoundingClientRect();
            return {
                x: Math.max(0, Math.min(100, ((event.clientX - bounds.left) / bounds.width) * 100)),
                y: Math.max(0, Math.min(100, ((event.clientY - bounds.top) / bounds.height) * 100)),
            };
        };
        const renderRectangle = (area) => {
            const bounds = preview.getBoundingClientRect();
            const left = area.x - area.width / 2;
            const top = area.y - area.height / 2;
            rectangle.style.left = `${left * bounds.width / 100}px`;
            rectangle.style.top = `${top * bounds.height / 100}px`;
            rectangle.style.width = `${area.width * bounds.width / 100}px`;
            rectangle.style.height = `${area.height * bounds.height / 100}px`;
            rectangle.hidden = false;
        };
        const renderDrawing = (points) => {
            drawing.setAttribute('points', points.map((point) => `${point.x},${point.y}`).join(' '));
            drawing.hidden = false;
        };
        const drawRectangle = (start, end) => {
            const left = Math.min(start.x, end.x);
            const top = Math.min(start.y, end.y);
            const width = Math.abs(end.x - start.x);
            const height = Math.abs(end.y - start.y);
            const area = {x: left + width / 2, y: top + height / 2, width, height};
            renderRectangle(area);
            return area;
        };
        const saveArea = (area) => {
            pathInput.value = '';
            xInput.value = area.x.toFixed(1);
            yInput.value = area.y.toFixed(1);
            widthInput.value = area.width.toFixed(1);
            heightInput.value = area.height.toFixed(1);
            setMarking(null, true);
            status.textContent = area.width > 0.5 || area.height > 0.5
                ? `Área selecionada: centro X ${xInput.value}%, Y ${yInput.value}%; ${widthInput.value}% × ${heightInput.value}%.`
                : `Ponto marcado: X ${xInput.value}%, Y ${yInput.value}%.`;
        };
        const saveDrawing = (points) => {
            const path = points.length > 1 ? points : [points[0], points[0]];
            const xs = path.map((point) => point.x);
            const ys = path.map((point) => point.y);
            const left = Math.min(...xs);
            const top = Math.min(...ys);
            const width = Math.max(...xs) - left;
            const height = Math.max(...ys) - top;
            pathInput.value = JSON.stringify(path.map((point) => ({x: Number(point.x.toFixed(1)), y: Number(point.y.toFixed(1))})));
            saveArea({x: left + width / 2, y: top + height / 2, width, height});
            pathInput.value = JSON.stringify(path.map((point) => ({x: Number(point.x.toFixed(1)), y: Number(point.y.toFixed(1))})));
            rectangle.hidden = true;
            renderDrawing(JSON.parse(pathInput.value));
            status.textContent = `Rabisco registrado com ${path.length} pontos; a área aproximada também foi vinculada ao comentário.`;
        };
        const sync = () => {
            document.querySelectorAll('[data-anchor]').forEach((section) => {
                const active = section.dataset.anchor === select.value;
                section.hidden = !active;
                section.querySelectorAll('input,textarea').forEach((field) => {
                    field.required = active && field.dataset.optional !== 'true';
                    field.disabled = !active;
                });
            });
            select.closest('form').querySelectorAll('[data-review-action]').forEach((button) => {
                button.disabled = Boolean(select.value);
            });
            select.closest('form').querySelector('[data-annotation-action]').hidden = !select.value;
            if (preview) {
                preview.hidden = select.value !== 'area';
                if (select.value === 'area' && pathInput.value) {
                    try {
                        const points = JSON.parse(pathInput.value);
                        if (Array.isArray(points) && points.length >= 2) renderDrawing(points);
                    } catch {
                        drawing.hidden = true;
                    }
                } else if (select.value === 'area' && xInput.value && yInput.value) {
                    renderRectangle({
                        x: Number(xInput.value),
                        y: Number(yInput.value),
                        width: Number(widthInput.value || 0),
                        height: Number(heightInput.value || 0),
                    });
                }
            }
            if (select.value !== 'area' && marker && markerButton) setMarking(null);
        };
        select.addEventListener('change', sync);
        [xInput, yInput, widthInput, heightInput].forEach((field) => {
            field.addEventListener('input', () => {
                if (!preview || select.value !== 'area' || !xInput.value || !yInput.value) return;
                renderRectangle({
                    x: Number(xInput.value),
                    y: Number(yInput.value),
                    width: Number(widthInput.value || 0),
                    height: Number(heightInput.value || 0),
                });
            });
        });
        if (marker && markerButton && drawingButton) {
            markerButton.addEventListener('click', () => setMarking(markingMode === 'rectangle' ? null : 'rectangle'));
            drawingButton.addEventListener('click', () => setMarking(markingMode === 'drawing' ? null : 'drawing'));
            marker.addEventListener('pointerdown', (event) => {
                if (event.button !== 0) return;
                event.preventDefault();
                dragStart = relativePoint(event);
                activePath = [dragStart];
                marker.setPointerCapture(event.pointerId);
                if (markingMode === 'drawing') renderDrawing(activePath);
                else drawRectangle(dragStart, dragStart);
            });
            marker.addEventListener('pointermove', (event) => {
                if (!dragStart) return;
                const point = relativePoint(event);
                if (markingMode === 'rectangle') {
                    drawRectangle(dragStart, point);
                    return;
                }
                const previous = activePath[activePath.length - 1];
                const bounds = marker.getBoundingClientRect();
                const distance = Math.hypot((point.x - previous.x) * bounds.width / 100, (point.y - previous.y) * bounds.height / 100);
                if (distance < 2) return;
                activePath.push(point);
                if (activePath.length > 256) activePath = activePath.filter((_, index) => index % 2 === 0);
                renderDrawing(activePath);
            });
            marker.addEventListener('pointerup', (event) => {
                if (!dragStart) return;
                const point = relativePoint(event);
                if (markingMode === 'drawing') {
                    const previous = activePath[activePath.length - 1];
                    if (Math.hypot(point.x - previous.x, point.y - previous.y) > 0.1) activePath.push(point);
                    if (activePath.length > 256) activePath = activePath.filter((_, index) => index % 2 === 0);
                    dragStart = null;
                    saveDrawing(activePath);
                    return;
                }
                const area = drawRectangle(dragStart, point);
                dragStart = null;
                saveArea(area);
            });
            marker.addEventListener('pointercancel', () => {
                dragStart = null;
                activePath = [];
                if (markingMode === 'drawing') drawing.hidden = true;
                else rectangle.hidden = true;
            });
            marker.addEventListener('keydown', (event) => {
                if (event.key === 'Enter' || event.key === ' ') {
                    event.preventDefault();
                    if (markingMode === 'drawing') saveDrawing([{x: 50, y: 50}]);
                    else saveArea({x: 50, y: 50, width: 0, height: 0});
                }
            });
        }
        videoTimeButton?.addEventListener('click', () => {
            if (!reviewVideo || !Number.isFinite(reviewVideo.currentTime)) {
                videoTimeStatus.textContent = 'O vídeo ainda não informou a posição atual. Tente novamente.';
                return;
            }
            if (!reviewVideo.paused) {
                videoTimeStatus.textContent = 'Pause o vídeo no ponto desejado antes de capturar o instante.';
                return;
            }

            const totalSeconds = Math.floor(reviewVideo.currentTime);
            const hours = Math.floor(totalSeconds / 3600);
            if (hours > 23) {
                videoTimeStatus.textContent = 'Este campo aceita vídeos com até 23:59:59 para localizar o instante.';
                return;
            }

            const minutes = Math.floor((totalSeconds % 3600) / 60);
            const seconds = totalSeconds % 60;
            videoTimeInput.value = [hours, minutes, seconds].map((part) => String(part).padStart(2, '0')).join(':');
            videoTimeStatus.textContent = `Instante preenchido: ${videoTimeInput.value}.`;
        });
        sync();
    })();
</script>
</body>
</html>
