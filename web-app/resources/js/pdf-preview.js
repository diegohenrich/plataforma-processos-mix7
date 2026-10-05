import * as pdfjsLib from 'pdfjs-dist';
import pdfWorkerUrl from 'pdfjs-dist/build/pdf.worker.mjs?url';
import '../css/pdf-preview.css';

pdfjsLib.GlobalWorkerOptions.workerSrc = pdfWorkerUrl;

document.querySelectorAll('[data-pdf-preview]').forEach((viewer) => {
    const canvas = viewer.querySelector('canvas');
    const context = canvas.getContext('2d');
    const status = viewer.querySelector('[data-pdf-status]');
    const pageLabel = viewer.querySelector('[data-pdf-page]');
    const previous = viewer.querySelector('[data-pdf-previous]');
    const next = viewer.querySelector('[data-pdf-next]');
    const zoomOut = viewer.querySelector('[data-pdf-zoom-out]');
    const zoomIn = viewer.querySelector('[data-pdf-zoom-in]');
    let documentHandle;
    let pageNumber = 1;
    let zoom = 1;
    let rendering = false;
    let rerender = false;
    let initialized = false;

    const render = async () => {
        if (!documentHandle) return;
        if (rendering) {
            rerender = true;
            return;
        }

        rendering = true;
        try {
            const page = await documentHandle.getPage(pageNumber);
            const baseViewport = page.getViewport({ scale: 1 });
            const availableWidth = Math.max(240, viewer.clientWidth - 32);
            const pixelRatio = Math.min(window.devicePixelRatio || 1, 2);
            const pixelAreaLimit = Math.sqrt(16_000_000) / Math.sqrt(baseViewport.width * baseViewport.height) / pixelRatio;
            const scale = Math.min(2, (availableWidth / baseViewport.width) * zoom, 4096 / baseViewport.width, 4096 / baseViewport.height, pixelAreaLimit);
            const viewport = page.getViewport({ scale });
            canvas.width = Math.ceil(viewport.width * pixelRatio);
            canvas.height = Math.ceil(viewport.height * pixelRatio);
            canvas.style.width = `${Math.ceil(viewport.width)}px`;
            canvas.style.height = `${Math.ceil(viewport.height)}px`;
            context.setTransform(pixelRatio, 0, 0, pixelRatio, 0, 0);
            await page.render({ canvasContext: context, viewport }).promise;
            pageLabel.textContent = `Página ${pageNumber} de ${documentHandle.numPages}`;
            previous.disabled = pageNumber <= 1;
            next.disabled = pageNumber >= documentHandle.numPages;
            status.textContent = '';
        } catch (error) {
            status.textContent = 'Não foi possível mostrar a prévia. Use o link para abrir o arquivo em outra guia ou baixe-o.';
            console.error('Falha ao renderizar a prévia do PDF.', error);
        } finally {
            rendering = false;
            if (rerender) {
                rerender = false;
                render();
            }
        }
    };

    previous.addEventListener('click', () => {
        if (pageNumber > 1) {
            pageNumber -= 1;
            render();
        }
    });
    next.addEventListener('click', () => {
        if (documentHandle && pageNumber < documentHandle.numPages) {
            pageNumber += 1;
            render();
        }
    });
    zoomOut.addEventListener('click', () => {
        zoom = Math.max(0.7, zoom - 0.15);
        render();
    });
    zoomIn.addEventListener('click', () => {
        zoom = Math.min(2, zoom + 0.15);
        render();
    });

    const initialize = () => {
        if (initialized) return;
        initialized = true;
        const resizeObserver = new ResizeObserver(() => render());
        resizeObserver.observe(viewer);
        status.textContent = 'Carregando prévia do PDF…';
        pdfjsLib.getDocument({ url: viewer.dataset.pdfUrl }).promise
            .then((pdf) => {
                documentHandle = pdf;
                render();
            })
            .catch((error) => {
                status.textContent = 'Não foi possível mostrar a prévia. Use o link para abrir o arquivo em outra guia ou baixe-o.';
                console.error('Falha ao carregar o PDF.', error);
            });
    };

    const details = viewer.closest('details');
    if (details && !details.open) {
        details.addEventListener('toggle', () => {
            if (details.open) initialize();
        });
    } else {
        initialize();
    }
});
