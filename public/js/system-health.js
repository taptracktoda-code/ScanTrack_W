(() => {
    'use strict';

    const endpoint = document.currentScript.dataset.systemHealthUrl;
    const element = (id) => document.getElementById(id);
    const refresh = element('refresh-system-health');
    const action = element('system-health-action-btn');
    const freshness = element('system-health-freshness');
    const errorBox = element('system-health-error');
    const metricFields = {
        server: 'server-status', database: 'database-status', api: 'api-status',
        storage: 'storage-status', drivers: 'drivers-count', vehicles: 'vehicles-count',
        qr_codes: 'qr-codes-count', today_attendance: 'attendance-count', total_logs: 'total-logs-count',
    };
    let timer;
    let watchdog;
    let activeRequest;
    let lastSuccess = null;
    let lastChecked = null;
    let responseTime = null;
    let unavailable = true;
    let stopped = false;
    let sessionExpired = false;

    const setStatus = (status) => {
        const color = { Healthy: 'success', Critical: 'danger', Unavailable: 'secondary' }[status] || 'secondary';
        element('overall-status-label').textContent = `Overall System Status: ${status}`;
        const pill = element('overall-status-pill');
        pill.textContent = status;
        pill.className = `dashboard-mini-pill bg-${color} text-white`;
        action.className = `btn btn-${color}`;
        action.textContent = 'Check now';
    };

    const renderFreshness = () => {
        const paused = document.hidden ? 'Auto-refresh paused while this tab is hidden. ' : '';
        if (lastSuccess === null) {
            freshness.textContent = `${paused}No successful check yet.`;
            return;
        }
        const age = Math.max(0, Math.floor((Date.now() - lastSuccess) / 1000));
        freshness.textContent = `${paused}${unavailable ? 'Last successful check' : 'Updated'}: ${lastChecked.toLocaleString()} (${age}s ago). Check took ${responseTime} ms.${unavailable ? ' Current status is unverified.' : ''}`;
    };

    const markUnavailable = (message) => {
        unavailable = true;
        element('system-health-loading').classList.add('d-none');
        setStatus('Unavailable');
        Object.values(metricFields).forEach((id) => { element(id).textContent = 'Unavailable'; });
        ['last-scan-driver', 'last-scan-type', 'last-scan-time'].forEach((id) => { element(id).textContent = 'Unavailable'; });
        element('system-health-reasons').replaceChildren();
        errorBox.textContent = message;
        errorBox.classList.remove('d-none');
        element('system-health-content').classList.remove('d-none');
        renderFreshness();
    };

    const renderHealth = (data) => {
        if (!['Healthy', 'Critical'].includes(data.overall_status)
            || !Number.isFinite(Date.parse(data.checked_at))
            || !Array.isArray(data.reasons)
            || !Number.isFinite(data.response_time_ms)
            || !Object.keys(metricFields).every((key) => Object.hasOwn(data, key))) {
            throw new Error('Invalid health response. Retrying automatically.');
        }
        Object.entries(metricFields).forEach(([key, id]) => {
            element(id).textContent = data[key] ?? 'Unavailable';
        });
        element('last-scan-driver').textContent = data.last_scan?.driver ?? (data.database === 'Connected' ? 'No QR scans recorded' : 'Unavailable');
        element('last-scan-type').textContent = data.last_scan?.type ?? '—';
        element('last-scan-time').textContent = data.last_scan?.time ? new Date(data.last_scan.time).toLocaleString() : '—';
        const reasons = element('system-health-reasons');
        reasons.replaceChildren();
        (data.reasons.length ? data.reasons : ['No issues detected in the checks above']).forEach((reason) => {
            const badge = document.createElement('div');
            badge.className = `dashboard-mini-pill${data.overall_status === 'Critical' ? ' bg-danger text-white' : ''}`;
            badge.textContent = reason;
            reasons.appendChild(badge);
        });
        lastSuccess = Date.now();
        lastChecked = new Date(data.checked_at);
        responseTime = data.response_time_ms;
        unavailable = false;
        errorBox.classList.add('d-none');
        element('system-health-content').classList.remove('d-none');
        setStatus(data.overall_status);
        renderFreshness();
    };

    const loadHealth = async () => {
        clearTimeout(timer);
        if (activeRequest || stopped || document.hidden || sessionExpired) return;
        if (!navigator.onLine) {
            markUnavailable('You are offline. Checks will resume when your connection returns.');
            return;
        }
        const controller = new AbortController();
        activeRequest = controller;
        const timeout = setTimeout(() => controller.abort(), 10000);
        refresh.disabled = true;
        action.disabled = true;
        if (lastSuccess === null) element('system-health-loading').classList.remove('d-none');

        try {
            const response = await fetch(endpoint, {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                cache: 'no-store', credentials: 'same-origin', signal: controller.signal,
            });
            if (controller.signal.aborted) return;
            if (response.status === 401 || response.status === 403 || response.redirected) {
                sessionExpired = true;
                throw new Error('Your session expired or access was denied. Reload this page and sign in again.');
            }
            if (!response.ok) throw new Error('The health endpoint is unavailable. Retrying automatically.');
            const data = await response.json();
            if (!controller.signal.aborted) renderHealth(data);
        } catch (error) {
            if (!stopped && !document.hidden) {
                markUnavailable(error.name === 'AbortError' ? 'The health check timed out. Retrying automatically.' : error.message);
            }
        } finally {
            clearTimeout(timeout);
            activeRequest = null;
            element('system-health-loading').classList.add('d-none');
            refresh.disabled = sessionExpired;
            action.disabled = sessionExpired;
            if (!stopped && !document.hidden && !sessionExpired) timer = setTimeout(loadHealth, 5000);
        }
    };

    const tick = () => {
        if (!document.hidden && lastSuccess !== null && Date.now() - lastSuccess > 15000 && !unavailable) {
            markUnavailable('Health data is stale. Waiting for a fresh check.');
        }
        renderFreshness();
    };
    const pause = () => {
        clearTimeout(timer);
        activeRequest?.abort();
        renderFreshness();
    };
    refresh.addEventListener('click', loadHealth);
    action.addEventListener('click', loadHealth);
    document.addEventListener('visibilitychange', () => {
        if (document.hidden) pause();
        else { tick(); loadHealth(); }
    });
    window.addEventListener('offline', () => {
        pause();
        markUnavailable('You are offline. Checks will resume when your connection returns.');
    });
    window.addEventListener('online', loadHealth);
    window.addEventListener('pagehide', () => {
        stopped = true;
        pause();
        clearInterval(watchdog);
    });
    window.addEventListener('pageshow', () => {
        stopped = false;
        clearInterval(watchdog);
        watchdog = setInterval(tick, 1000);
        tick();
        loadHealth();
    });
    watchdog = setInterval(tick, 1000);
    loadHealth();
})();
