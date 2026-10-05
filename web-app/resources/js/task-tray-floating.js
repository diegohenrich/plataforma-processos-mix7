const tray = document.querySelector('[data-task-tray-floating]');

if (tray) {
    const button = tray.querySelector('[data-task-tray-floating-open]');
    const status = tray.querySelector('[data-task-tray-floating-status]');
    const stateElement = document.querySelector('[data-task-tray-floating-state]');
    const supportsFloatingWindow = window.isSecureContext
        && 'documentPictureInPicture' in window
        && typeof window.documentPictureInPicture?.requestWindow === 'function';

    if (button && status && stateElement) {
        const state = JSON.parse(stateElement.textContent);
        let floatingWindow = null;
        let elapsedInterval = null;
        let waitingInterval = null;
        let heartbeatInterval = null;

        if (!supportsFloatingWindow) {
            button.disabled = true;
            button.title = 'Seu navegador não permite abrir uma janela separada. Use Minhas tarefas no CRM.';
            status.textContent = button.title;
            status.classList.add('is-visible');
        }

        const element = (documentRef, tag, text, className) => {
            const node = documentRef.createElement(tag);
            if (text !== undefined) node.textContent = text;
            if (className) node.className = className;
            return node;
        };

        const formatElapsed = (seconds) => {
            const wholeSeconds = Math.max(0, Math.floor(seconds));
            const hours = String(Math.floor(wholeSeconds / 3600)).padStart(2, '0');
            const minutes = String(Math.floor((wholeSeconds % 3600) / 60)).padStart(2, '0');
            const remainder = String(wholeSeconds % 60).padStart(2, '0');

            return `${hours}:${minutes}:${remainder}`;
        };

        const formatWaiting = (timestamp) => {
            const seconds = Math.max(0, Math.floor((Date.now() - Date.parse(timestamp)) / 1000));
            const days = Math.floor(seconds / 86400);
            const hours = Math.floor((seconds % 86400) / 3600);
            const minutes = Math.floor((seconds % 3600) / 60);

            const parts = [];
            if (days) parts.push(`${days} ${days === 1 ? 'dia' : 'dias'}`);
            if (hours) parts.push(`${hours} ${hours === 1 ? 'hora' : 'horas'}`);
            if (!days && minutes) parts.push(`${minutes} ${minutes === 1 ? 'minuto' : 'minutos'}`);
            if (!parts.length) parts.push('1 minuto');

            return parts.length > 1 ? `${parts.slice(0, -1).join(', ')} e ${parts.at(-1)}` : parts[0];
        };

        const buttonFor = (documentRef, label, action, task, className = '') => {
            const control = element(documentRef, 'button', label, `mix7-control ${className}`.trim());
            control.type = 'button';
            control.addEventListener('click', () => performAction(action, task));

            return control;
        };

        const waitingFor = (documentRef, timestamp) => {
            const node = element(documentRef, 'small', '', 'mix7-waiting');
            node.dataset.waitingSince = timestamp;
            node.textContent = `Na sua fila há ${formatWaiting(timestamp)}. Inicie o cronômetro ou avise a gestão se estiver impedido.`;

            return node;
        };

        const activeTask = () => state.tasks.find((task) => task.id === state.active?.taskId) ?? null;

        const render = () => {
            if (!floatingWindow || floatingWindow.closed) return;

            const documentRef = floatingWindow.document;
            const root = documentRef.querySelector('[data-mix7-floating-root]');
            if (!root) return;
            root.replaceChildren();

            const heading = element(documentRef, 'header', undefined, 'mix7-header');
            heading.append(element(documentRef, 'span', 'MIX7 · MEU TRABALHO', 'mix7-eyebrow'));
            heading.append(element(documentRef, 'h1', 'Minhas tarefas', 'mix7-heading'));
            root.append(heading);

            const message = element(documentRef, 'p', '', 'mix7-message');
            message.setAttribute('role', 'status');
            message.setAttribute('aria-live', 'polite');
            root.append(message);

            const active = activeTask();
            if (active) {
                const card = element(documentRef, 'section', undefined, 'mix7-active-card');
                card.append(element(documentRef, 'span', 'EM ANDAMENTO', 'mix7-eyebrow'));
                card.append(element(documentRef, 'h2', active.title, 'mix7-task-title'));
                card.append(element(documentRef, 'p', active.demandTitle, 'mix7-demand-title'));
                const timer = element(documentRef, 'strong', '', 'mix7-timer');
                timer.dataset.taskTrayFloatingTimer = 'true';
                card.append(timer);

                const actions = element(documentRef, 'div', undefined, 'mix7-actions');
                actions.append(buttonFor(documentRef, 'Pausar', 'pause', active, 'mix7-secondary'));
                if (active.canComplete) actions.append(buttonFor(documentRef, 'Concluir tarefa', 'complete', active, 'mix7-primary'));
                card.append(actions);
                root.append(card);

                const otherTasks = state.tasks.filter((task) => task.id !== active.id && task.statusLabel !== 'Concluída');
                if (otherTasks.length) {
                    const listDetails = element(documentRef, 'details', undefined, 'mix7-other-tasks');
                    listDetails.append(element(documentRef, 'summary', `Outras tarefas atribuídas (${otherTasks.length})`));
                    const list = element(documentRef, 'ul', undefined, 'mix7-task-list');
                    for (const task of otherTasks) {
                        const item = element(documentRef, 'li', undefined, 'mix7-task');
                        const details = element(documentRef, 'div', undefined, 'mix7-task-details');
                        const openTask = element(documentRef, 'button', task.title, 'mix7-task-link');
                        openTask.type = 'button';
                        openTask.addEventListener('click', () => {
                            if (window.closed) return;
                            window.location.href = task.demandUrl;
                            window.focus();
                        });
                        details.append(openTask);
                        details.append(element(documentRef, 'small', `${task.demandTitle} · ${task.statusLabel}`, 'mix7-demand-title'));
                        if (task.waitingSince) details.append(waitingFor(documentRef, task.waitingSince));
                        item.append(details);
                        if (task.blocked) item.append(element(documentRef, 'span', 'Bloqueada', 'mix7-muted'));
                        list.append(item);
                    }
                    listDetails.append(list);
                    root.append(listDetails);
                }

                const updateTimer = () => {
                    if (!floatingWindow || floatingWindow.closed) return;
                    const output = floatingWindow.document.querySelector('[data-task-tray-floating-timer]');
                    if (!output || !state.active) return;
                    const started = Date.parse(state.active.startedAt);
                    const elapsed = Number(state.active.elapsedSeconds) + Math.max(0, (Date.now() - started) / 1000);
                    output.textContent = formatElapsed(elapsed);
                };
                updateTimer();
                if (elapsedInterval) floatingWindow.clearInterval(elapsedInterval);
                elapsedInterval = floatingWindow.setInterval(updateTimer, 1000);
            } else {
                if (elapsedInterval && floatingWindow) floatingWindow.clearInterval(elapsedInterval);
                elapsedInterval = null;
                const description = element(documentRef, 'p', 'Inicie uma tarefa para acompanhar o tempo mesmo com o CRM minimizado.', 'mix7-intro');
                root.append(description);

                const list = element(documentRef, 'ul', undefined, 'mix7-task-list');
                for (const task of state.tasks.filter((candidate) => candidate.statusLabel !== 'Concluída')) {
                    const item = element(documentRef, 'li', undefined, 'mix7-task');
                    const details = element(documentRef, 'div', undefined, 'mix7-task-details');
                    const openTask = element(documentRef, 'button', task.title, 'mix7-task-link');
                    openTask.type = 'button';
                    openTask.addEventListener('click', () => {
                            if (window.closed) return;
                            window.location.href = task.demandUrl;
                            window.focus();
                    });
                    details.append(openTask);
                    details.append(element(documentRef, 'small', `${task.demandTitle} · ${task.statusLabel}`, 'mix7-demand-title'));
                    if (task.waitingSince) details.append(waitingFor(documentRef, task.waitingSince));
                    item.append(details);

                    if (task.blocked) {
                        const blocked = element(documentRef, 'span', 'Bloqueada', 'mix7-muted');
                        blocked.title = 'Conclua as tarefas anteriores primeiro.';
                        item.append(blocked);
                    } else if (task.canStart) {
                        item.append(buttonFor(documentRef, 'Iniciar', 'start', task, 'mix7-primary'));
                    } else if (task.statusLabel === 'Em execução') {
                        item.append(element(documentRef, 'span', 'Em execução', 'mix7-muted'));
                    }

                    if (task.canComplete) item.append(buttonFor(documentRef, 'Concluir', 'complete', task, 'mix7-secondary'));
                    list.append(item);
                }

                if (list.childElementCount) {
                    root.append(list);
                } else {
                    root.append(element(documentRef, 'p', 'Nenhuma tarefa aberta atribuída a você.', 'mix7-intro'));
                }
                root.append(element(documentRef, 'a', 'Abrir meu quadro completo', 'mix7-board-link'));
                root.lastElementChild.href = state.boardUrl;
                root.lastElementChild.target = '_blank';
                root.lastElementChild.rel = 'noopener noreferrer';
            }

            if (waitingInterval && floatingWindow) floatingWindow.clearInterval(waitingInterval);
            waitingInterval = floatingWindow.setInterval(() => {
                floatingWindow.document.querySelectorAll('[data-waiting-since]').forEach((node) => {
                    node.textContent = `Na sua fila há ${formatWaiting(node.dataset.waitingSince)}. Inicie o cronômetro ou avise a gestão se estiver impedido.`;
                });
            }, 60_000);
            if (heartbeatInterval && floatingWindow) floatingWindow.clearInterval(heartbeatInterval);
            heartbeatInterval = state.active
                ? floatingWindow.setInterval(sendHeartbeat, 20_000)
                : null;
        };

        const showMessage = (message, isError = false) => {
            if (!floatingWindow || floatingWindow.closed) return;
            const node = floatingWindow.document.querySelector('[role="status"]');
            if (node) {
                node.textContent = message;
                node.classList.toggle('is-error', isError);
            }
            status.textContent = message;
            status.classList.add('is-visible');
        };

        const sendHeartbeat = async () => {
            if (!state.active) return;
            try {
                const response = await fetch(state.heartbeatUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': state.csrfToken,
                    },
                });
                const result = await response.json();
                if (response.ok && result.data?.active === false) {
                    state.active = null;
                    render();
                    showMessage('O cronômetro foi encerrado após perder contato com o servidor.');
                }
            } catch (_) {
                showMessage('Sem conexão momentânea. O último sinal confirmado será usado.', true);
            }
        };

        const performAction = async (action, task) => {
            const url = action === 'start' ? task.startUrl : action === 'pause' ? task.pauseUrl : task.completeUrl;
            const method = action === 'complete' ? 'PATCH' : 'POST';
            const body = action === 'complete' ? JSON.stringify({ status: 'completed' }) : undefined;
            try {
                const response = await fetch(url, {
                    method,
                    credentials: 'same-origin',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': state.csrfToken,
                    },
                    body,
                });
                const result = await response.json();
                if (!response.ok) throw new Error(result.message || 'Não foi possível atualizar a tarefa.');

                const updated = state.tasks.find((candidate) => candidate.id === task.id);
                let actionMessage = '';
                if (action === 'start') {
                    state.active = {
                        taskId: task.id,
                        startedAt: result.data.timer.started_at,
                        elapsedSeconds: Number(updated.elapsedSeconds || 0),
                    };
                    updated.statusLabel = result.data.status_label;
                    updated.canStart = false;
                    for (const candidate of state.tasks) candidate.canStart = false;
                    actionMessage = 'Cronômetro iniciado.';
                } else if (action === 'pause') {
                    updated.elapsedSeconds = Number(state.active?.elapsedSeconds || 0) + Number(result.data.timer?.duration_seconds || 0);
                    updated.statusLabel = result.data.status_label;
                    updated.canStart = true;
                    state.active = null;
                    for (const candidate of state.tasks) candidate.canStart = !candidate.blocked && candidate.statusLabel !== 'Concluída';
                    actionMessage = 'Tempo salvo e tarefa pausada.';
                } else {
                    updated.statusLabel = result.data.status_label;
                    updated.canStart = false;
                    updated.canComplete = false;
                    if (state.active?.taskId === task.id) state.active = null;
                    for (const candidate of state.tasks) candidate.canStart = !state.active && !candidate.blocked && candidate.statusLabel !== 'Concluída';
                    actionMessage = 'Tarefa concluída e tempo encerrado.';
                }

                render();
                showMessage(actionMessage);
                try { window.location.reload(); } catch (_) { /* O painel flutuante permanece funcional. */ }
            } catch (error) {
                showMessage(error instanceof Error ? error.message : 'Não foi possível atualizar a tarefa.', true);
            }
        };

        const openFloatingWindow = async () => {
            if (!supportsFloatingWindow) return;
            button.disabled = true;
            status.textContent = 'Abrindo uma janela separada para suas tarefas…';
            try {
                floatingWindow = window.documentPictureInPicture.window;
                if (!floatingWindow || floatingWindow.closed) {
                    floatingWindow = await window.documentPictureInPicture.requestWindow({ width: 380, height: 520 });
                }
                const documentRef = floatingWindow.document;
                documentRef.title = 'Mix7 · Minhas tarefas';
                documentRef.head.replaceChildren();
                documentRef.body.replaceChildren();
                const viewport = element(documentRef, 'meta');
                viewport.name = 'viewport';
                viewport.content = 'width=device-width, initial-scale=1';
                documentRef.head.append(viewport);
                const style = element(documentRef, 'style', `
                    :root{color-scheme:light;font-family:Inter,Segoe UI,Arial,sans-serif;color:#273b43;background:#f4f9fb}
                    *{box-sizing:border-box}body{margin:0;padding:18px;background:#f4f9fb}
                    .mix7-header{padding:3px 2px 16px;border-bottom:1px solid #dce9ed}.mix7-eyebrow{display:block;color:#39758b;font-size:10px;font-weight:800;letter-spacing:.1em}
                    .mix7-heading{margin:6px 0 0;color:#204b61;font-size:21px}.mix7-message{min-height:18px;margin:8px 1px;color:#397050;font-size:11px}.mix7-message.is-error{color:#a14e4e}
                    .mix7-intro{color:#667b83;font-size:12px;line-height:1.55}.mix7-active-card,.mix7-task{padding:14px;border:1px solid #d7e9e9;border-radius:13px;background:#fff;box-shadow:0 5px 18px #204b610d}.mix7-waiting{color:#945b12;font-size:10px;font-weight:800;line-height:1.4}
                    .mix7-task-title{margin:8px 0 4px;color:#304952;font-size:15px;line-height:1.35}.mix7-demand-title{display:block;margin:4px 0 0;color:#718087;font-size:11px;line-height:1.4;overflow-wrap:anywhere}
                    .mix7-timer{display:block;margin:20px 0;color:#204b61;font-size:38px;font-variant-numeric:tabular-nums;letter-spacing:.02em;text-align:center}
                    .mix7-actions{display:flex;gap:8px}.mix7-control{min-height:38px;padding:8px 11px;border:1px solid #d6e0e1;border-radius:9px;background:#fff;color:#38515b;font:inherit;font-size:11px;font-weight:750;cursor:pointer}.mix7-primary{border-color:#204b61;background:#204b61;color:#fff}.mix7-secondary{border-color:#cfe2e5;background:#f4fbfc;color:#204b61}
                    .mix7-task-list{display:grid;gap:9px;margin:12px 0;padding:0;list-style:none}.mix7-task{display:flex;align-items:center;gap:8px;padding:11px}.mix7-task-details{display:grid;flex:1;gap:3px;min-width:0}.mix7-task-link{padding:0;border:0;background:none;color:#304952;font:inherit;font-size:12px;font-weight:750;text-align:left;cursor:pointer}.mix7-muted{color:#8a969a;font-size:10px}.mix7-board-link{display:inline-block;margin-top:8px;color:#326c82;font-size:11px;font-weight:750}
                `);
                documentRef.head.append(style);
                const root = element(documentRef, 'main');
                root.dataset.mix7FloatingRoot = 'true';
                documentRef.body.append(root);
                render();
                floatingWindow.addEventListener('pagehide', () => {
                    if (elapsedInterval) floatingWindow.clearInterval(elapsedInterval);
                    if (heartbeatInterval) floatingWindow.clearInterval(heartbeatInterval);
                    elapsedInterval = null;
                    heartbeatInterval = null;
                    floatingWindow = null;
                    button.disabled = false;
                    status.textContent = 'Janela separada fechada. Minhas tarefas continua disponível no CRM.';
                }, { once: true });
                status.textContent = 'Janela aberta. Suas tarefas e o cronômetro continuam visíveis ao minimizar o CRM.';
                status.classList.add('is-visible');
            } catch (_) {
                status.textContent = 'Não foi possível abrir a janela separada. Use Minhas tarefas no CRM.';
                status.classList.add('is-visible');
            } finally {
                button.disabled = false;
            }
        };

        button?.addEventListener('click', openFloatingWindow);
    }
}
