(() => {
    const records = document.getElementById('queue-live-records');
    const status = document.getElementById('queue-refresh-status');
    const interval = 20_000;
    const ready = status.textContent;
    let timer;
    let etag;

    async function refresh() {
        timer = null;
        try {
            if (document.hidden || records.contains(document.activeElement)) {
                return;
            }

            const response = await fetch(records.dataset.feed, {
                cache: 'no-store',
                headers: etag ? { 'If-None-Match': etag } : {},
                redirect: 'error',
                signal: AbortSignal.timeout(10_000),
            });
            if (response.status === 304) {
                status.textContent = ready;
                return;
            }
            if (!response.ok) {
                throw new Error('Queue refresh failed');
            }

            const html = await response.text();
            if (!records.contains(document.activeElement)) {
                const scrollLeft = records.querySelector('[data-queue-scroll]').scrollLeft;
                records.innerHTML = html;
                records.querySelector('[data-queue-scroll]').scrollLeft = scrollLeft;
                etag = response.headers.get('ETag');
            }
            status.textContent = ready;
        } catch {
            status.textContent = 'Live refresh is temporarily unavailable. Retrying automatically.';
        } finally {
            timer = setTimeout(refresh, interval);
        }
    }

    timer = setTimeout(refresh, interval);
    document.addEventListener('visibilitychange', () => {
        if (!document.hidden && timer) {
            clearTimeout(timer);
            timer = setTimeout(refresh, 0);
        }
    });
})();
