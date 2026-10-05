import * as pdfjsLib from 'pdfjs-dist';
import pdfWorkerUrl from 'pdfjs-dist/build/pdf.worker.mjs?url';
import * as mammoth from 'mammoth';

pdfjsLib.GlobalWorkerOptions.workerSrc = pdfWorkerUrl;

const MAX_FILE_BYTES = 5 * 1024 * 1024;
const MAX_EXTRACTED_CHARACTERS = 12000;

document.querySelectorAll('[data-briefing-assistant]').forEach((root) => {
    const input = root.querySelector('#briefing-document');
    const status = root.querySelector('[data-briefing-file-status]');
    const remove = root.querySelector('[data-briefing-remove-file]');
    if (!input || !status || !remove) return;

    root.briefingDocument = null;
    const setStatus = (message, isError = false) => {
        status.textContent = message;
        status.classList.toggle('is-error', isError);
    };
    const extractText = async (file) => {
        const extension = file.name.split('.').pop()?.toLocaleLowerCase('pt-BR');
        if (['txt', 'md', 'csv'].includes(extension)) return file.text();
        if (extension === 'docx') {
            const result = await mammoth.extractRawText({arrayBuffer: await file.arrayBuffer()});
            return result.value;
        }
        if (extension === 'pdf') {
            const pdf = await pdfjsLib.getDocument({data: new Uint8Array(await file.arrayBuffer())}).promise;
            const pages = [];
            for (let pageNumber = 1; pageNumber <= pdf.numPages; pageNumber++) {
                const page = await pdf.getPage(pageNumber);
                const content = await page.getTextContent();
                pages.push(content.items.map((item) => item.str ?? '').join(' '));
                if (pages.join('\n').length >= MAX_EXTRACTED_CHARACTERS) break;
            }
            return pages.join('\n');
        }
        throw new Error('Use um arquivo PDF, DOCX ou texto (TXT, MD, CSV).');
    };

    input.addEventListener('change', async () => {
        const file = input.files?.[0];
        root.briefingDocument = null;
        remove.hidden = true;
        if (!file) {
            setStatus('PDF, Word ou texto · até 5 MB. O arquivo é lido no navegador.');
            return;
        }
        if (file.size > MAX_FILE_BYTES) {
            input.value = '';
            setStatus('O arquivo ultrapassa 5 MB. Escolha um documento menor.', true);
            return;
        }

        setStatus(`Lendo ${file.name}…`);
        input.disabled = true;
        try {
            const text = (await extractText(file)).replace(/\u0000/g, '').trim();
            if (!text) throw new Error('Não encontrei texto selecionável. Se for um PDF escaneado, transcreva ou aplique OCR antes.');
            root.briefingDocument = {
                name: file.name.slice(0, 180),
                text: text.slice(0, MAX_EXTRACTED_CHARACTERS),
                truncated: text.length > MAX_EXTRACTED_CHARACTERS,
            };
            setStatus(`${file.name} pronto para consulta${text.length > MAX_EXTRACTED_CHARACTERS ? ' · texto limitado aos primeiros 12 mil caracteres' : ''}.`);
            remove.hidden = false;
        } catch (error) {
            root.briefingDocument = null;
            input.value = '';
            setStatus(error.message || 'Não foi possível ler este documento.', true);
        } finally {
            input.disabled = false;
        }
    });

    remove.addEventListener('click', () => {
        input.value = '';
        root.briefingDocument = null;
        remove.hidden = true;
        setStatus('Documento removido. Ele não será usado na conversa.');
    });
});
