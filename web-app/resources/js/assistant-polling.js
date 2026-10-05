(() => {
    const runs = [...document.querySelectorAll('[data-assistant-run][data-status-url]')]
        .filter((run) => run.dataset.statusUrl);

    if (!runs.length) return;

    const startedAt = Date.now();
    const maximumWait = 10 * 60 * 1000;
    let timer = null;
    let checking = false;
    let reloading = false;

    const scheduleNextCheck = () => {
        const elapsed = Date.now() - startedAt;
        const pendingRuns = runs.filter((run) => run.dataset.statusUrl);

        if (!pendingRuns.length || reloading) return;

        if (elapsed >= maximumWait) {
            pendingRuns.forEach((run) => {
                const hint = run.querySelector('[data-assistant-poll-hint]');
                if (hint) hint.hidden = false;
            });

            return;
        }

        let delay = elapsed < 15000 ? 2500 : elapsed < 45000 ? 5000 : elapsed < 120000 ? 10000 : 15000;
        if (document.hidden) delay = Math.max(delay, 30000);
        timer = window.setTimeout(checkStatuses, delay);
    };

    const checkStatuses = async () => {
        if (checking || reloading) return;

        checking = true;
        const pendingRuns = runs.filter((run) => run.dataset.statusUrl);

        await Promise.all(pendingRuns.map(async (run) => {
            try {
                const response = await fetch(run.dataset.statusUrl, {
                    headers: { Accept: 'application/json' },
                    credentials: 'same-origin',
                });

                if (!response.ok) return;

                const result = await response.json();
                if (['completed', 'failed'].includes(result.status)) {
                    reloading = true;
                    return;
                }

                const state = run.querySelector('[data-assistant-state]');
                if (state && ['queued', 'running'].includes(result.status)) {
                    state.textContent = result.status === 'running' ? 'Consultando' : 'Na fila';
                }
            } catch (_) {
                // Falhas transitórias são verificadas novamente com intervalo progressivo.
            }
        }));

        checking = false;

        if (reloading) {
            window.location.reload();
            return;
        }

        scheduleNextCheck();
    };

    timer = window.setTimeout(checkStatuses, 2500);

    document.addEventListener('visibilitychange', () => {
        if (document.hidden || !runs.some((run) => run.dataset.statusUrl) || reloading) return;

        window.clearTimeout(timer);
        timer = window.setTimeout(checkStatuses, 0);
    });
})();
