(() => {
    document.querySelectorAll('[data-loading-form]').forEach(form => {
        form.addEventListener('submit', () => {
            form.setAttribute('aria-busy', 'true');
            form.querySelectorAll('button[type="submit"]').forEach(button => {
                button.disabled = true;
                button.textContent = button.dataset.busyLabel || 'Saving...';
            });
        });
    });
    const inbox = document.querySelector('[data-reports-inbox]');
    if (!inbox) return;
    const button = inbox.querySelector('[data-refresh-reports]');
    const status = inbox.querySelector('[data-report-sync]');
    const error = inbox.querySelector('[data-report-error]');
    let busy = false;
    const refresh = async () => {
        if (busy || document.hidden) return;
        busy = true;
        button.disabled = true;
        inbox.setAttribute('aria-busy', 'true');
        status.textContent = 'Loading reports...';
        const controller = new AbortController();
        const timeout = setTimeout(() => controller.abort(), 15000);
        try {
            const response = await fetch(window.location.href, { headers: { 'X-Requested-With': 'XMLHttpRequest' }, cache: 'no-store', signal: controller.signal });
            if (!response.ok || response.redirected) throw new Error('Report refresh failed');
            const html = new DOMParser().parseFromString(await response.text(), 'text/html');
            const results = html.querySelector('[data-report-results]');
            if (!results) throw new Error('Reports unavailable');
            inbox.querySelector('[data-report-results]').replaceWith(results);
            error.classList.add('d-none');
            status.textContent = `Reports updated at ${new Date().toLocaleTimeString()}. Refreshes every 30 seconds.`;
        } catch {
            error.classList.remove('d-none');
            status.textContent = 'Report refresh failed.';
        } finally {
            clearTimeout(timeout);
            inbox.removeAttribute('aria-busy');
            button.disabled = false;
            busy = false;
        }
    };
    button.addEventListener('click', refresh);
    window.addEventListener('focus', refresh);
    setInterval(refresh, 30000);
})();
