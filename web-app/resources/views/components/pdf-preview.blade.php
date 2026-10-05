<div class="pdf-preview" data-pdf-preview data-pdf-url="{{ $pdfUrl }}" aria-label="Prévia de {{ $pdfName }}">
    <div class="pdf-toolbar" role="group" aria-label="Controles do PDF">
        <button type="button" data-pdf-previous disabled>Anterior</button>
        <span data-pdf-page aria-live="polite">Carregando páginas…</span>
        <button type="button" data-pdf-next disabled>Próxima</button>
        <span class="pdf-zoom-controls"><button type="button" data-pdf-zoom-out aria-label="Diminuir zoom">−</button><button type="button" data-pdf-zoom-in aria-label="Aumentar zoom">+</button></span>
    </div>
    <p class="pdf-status" data-pdf-status role="status" aria-live="polite"></p>
    <div class="pdf-canvas-wrap"><canvas aria-label="Página do documento PDF"></canvas></div>
</div>
