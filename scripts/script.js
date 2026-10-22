(function () {
    'use strict';

    const $ = (selector) => document.querySelector(selector);
    const $$ = (selector) => Array.from(document.querySelectorAll(selector));
    const form = $('#availability-form');
    const input = $('#domain-input');
    const select = $('#tld-select');
    const extensionOptions = Array.from(select.options).filter((option) => !option.disabled);
    const popular = ['com', 'net', 'org', 'io', 'co', 'dev', 'ai', 'id', 'xyz'];
    const extensions = extensionOptions.map((option) => option.value).sort((a, b) => {
        const first = popular.indexOf(a);
        const second = popular.indexOf(b);
        return (first < 0 ? popular.length : first) - (second < 0 ? popular.length : second) || a.localeCompare(b);
    });
    const cachePrefix = 'bulkdomain:';
    let expanded = false;
    let filter = 'all';
    let results = new Map();
    let counts = { available: 0, unavailable: 0, error: 0 };
    let toastTimer;
    let run = createRun();
    const whoisRequests = new Map();

    function createRun() {
        return { state: 'idle', queue: [], total: 0, checked: 0, processing: false, controller: null, cache: true };
    }

    function icon(name) {
        const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
        svg.setAttribute('class', 'icon');
        svg.setAttribute('aria-hidden', 'true');
        const use = document.createElementNS('http://www.w3.org/2000/svg', 'use');
        use.setAttribute('href', '#icon-' + name);
        svg.appendChild(use);
        return svg;
    }

    function toast(message) {
        clearTimeout(toastTimer);
        $('#toast').textContent = message;
        $('#toast').classList.remove('hidden');
        toastTimer = setTimeout(() => $('#toast').classList.add('hidden'), 3500);
    }

    function showError(message) {
        $('#form-message').textContent = message;
        $('#form-message').classList.remove('hidden');
    }

    function names() {
        return Array.from(new Set(input.value.toLowerCase().trim().split(/[\s,;]+/).filter(Boolean)));
    }

    function updateWordCount() {
        const count = names().length;
        $('#word-count').textContent = count.toLocaleString() + (count === 1 ? ' name' : ' names');
    }

    function selectedExtensions() {
        return extensionOptions.filter((option) => option.selected).map((option) => option.value);
    }

    function updateExtensionCount() {
        $('#extension-count').textContent = selectedExtensions().length + ' selected';
    }

    function renderExtensions() {
        const query = $('#extension-search').value.trim().toLowerCase().replace(/^\./, '');
        const selected = new Set(selectedExtensions());
        const visible = extensions.filter((extension) => query ? extension.includes(query) : expanded || popular.includes(extension) || selected.has(extension));
        const fragment = document.createDocumentFragment();
        visible.forEach((extension) => {
            const label = document.createElement('label');
            label.className = 'extension-chip' + (selected.has(extension) ? ' is-selected' : '');
            label.title = '.' + extension;
            const checkbox = document.createElement('input');
            checkbox.type = 'checkbox';
            checkbox.value = extension;
            checkbox.checked = selected.has(extension);
            checkbox.setAttribute('aria-label', '.' + extension);
            const text = document.createElement('span');
            text.textContent = '.' + extension;
            label.append(checkbox, text, icon('check'));
            fragment.appendChild(label);
        });
        $('#extension-options').replaceChildren(fragment);
        $('#extension-options').classList.toggle('is-expanded', expanded || !!query);
        $('#extension-empty').classList.toggle('hidden', visible.length > 0);
        $('#browse-extensions').setAttribute('aria-expanded', String(expanded));
        $('#browse-extensions').replaceChildren(document.createTextNode(expanded ? 'popular extensions ' : 'all ' + extensions.length + ' extensions '), icon('chevron'));
        updateExtensionCount();
    }

    function validDomain(value) {
        if (value.length > 253) return false;
        const parts = value.split('.');
        if (parts.length < 2 || !/^[a-z][a-z0-9-]{1,62}$/i.test(parts[parts.length - 1])) return false;
        return parts.every((part) => /^[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/i.test(part));
    }

    function validLength(value, options) {
        return value.length >= options.min && value.length <= options.max;
    }

    function searchOptions() {
        return {
            names: names(), extensions: selectedExtensions(),
            prefix: $('#prefix').value.trim().toLowerCase(), suffix: $('#suffix').value.trim().toLowerCase(),
            min: Number($('#min-length').value) || 1, max: Number($('#max-length').value) || 63,
            combine: $('#combine-a').checked, hyphen: $('#combine-b').checked, cache: $('#cache').checked
        };
    }

    const yieldToBrowser = () => new Promise((resolve) => setTimeout(resolve, 0));

    async function generateDomains(options, current) {
        const generated = new Set();
        const addWord = (word) => {
            const base = options.prefix + word + options.suffix;
            if (!validLength(base, options)) return;
            options.extensions.forEach((extension) => {
                const domain = base + '.' + extension;
                if (validDomain(domain)) generated.add(domain);
            });
        };
        for (let index = 0; index < options.names.length; index++) {
            const name = options.names[index];
            if (validDomain(name)) {
                if (validLength(name.split('.')[0], options)) generated.add(name);
            } else {
                addWord(name);
            }
            if (index % 200 === 0) {
                await yieldToBrowser();
                if (current !== run || current.state === 'stopped') return null;
            }
        }
        if (options.combine || options.hyphen) {
            const words = options.names.filter((name) => /^[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/i.test(name));
            let iterations = 0;
            for (const first of words) {
                for (const second of words) {
                    if (options.combine) addWord(first + second);
                    if (options.hyphen) addWord(first + '-' + second);
                    if (++iterations % 200 === 0) {
                        await yieldToBrowser();
                        if (current !== run || current.state === 'stopped') return null;
                    }
                }
            }
        }
        return Array.from(generated);
    }

    function busy() {
        return ['preparing', 'running', 'paused'].includes(run.state);
    }

    function updateControls() {
        const active = busy();
        $('#search-fields').disabled = active;
        $('#cache').disabled = active;
        $('#clear-cache').disabled = active;
        $('#search').disabled = active;
        $('#search span').textContent = active ? (run.state === 'paused' ? 'Search paused' : 'Search running…') : 'Check availability';
        $('#pause').classList.toggle('hidden', run.state !== 'running');
        $('#resume').classList.toggle('hidden', run.state !== 'paused');
        $('#stop').classList.toggle('hidden', !active);
        $('#run-actions').classList.toggle('hidden', !active);
        $('#retry-error').disabled = active;
        $$('[data-retry]').forEach((button) => { button.disabled = active; });
        const labels = { idle: 'Ready', preparing: 'Preparing queue…', running: 'Checking domains…', paused: 'Paused', stopped: 'Stopped', completed: 'Check complete' };
        $('#search-status').textContent = labels[run.state];
        $('#results').setAttribute('aria-busy', String(active && run.state !== 'paused'));
    }

    function updateProgress() {
        const percent = run.total ? Math.min(100, run.checked * 100 / run.total) : 0;
        $('#domains-checked').textContent = run.checked.toLocaleString();
        $('#domains-length').textContent = run.total.toLocaleString();
        $('#progress').textContent = Number(percent.toFixed(1));
        $('#progress-fill').style.width = percent + '%';
        $('#search-progress').setAttribute('aria-valuenow', String(Number(percent.toFixed(1))));
    }

    function updateCounters() {
        $('#available-counter span').textContent = counts.available.toLocaleString();
        $('#unavailable-counter span').textContent = counts.unavailable.toLocaleString();
        $('#error-counter span').textContent = counts.error.toLocaleString();
        $('#total-results').textContent = results.size.toLocaleString();
        $('#retry-error').classList.toggle('hidden', counts.error === 0);
        $('#export-csv').disabled = (filter === 'all' ? results.size : counts[filter]) === 0;
    }

    function applyFilter() {
        let visible = 0;
        results.forEach((entry) => {
            const matches = filter === 'all' || entry.status === filter;
            entry.row.hidden = !matches;
            if (matches) visible++;
            const detail = entry.row.nextElementSibling;
            if (detail && detail.classList.contains('result-whois')) detail.hidden = !matches;
        });
        $$('.result-tab').forEach((button) => {
            const active = button.dataset.filter === filter;
            button.classList.toggle('is-active', active);
            button.setAttribute('aria-pressed', String(active));
        });
        $('#results-empty').classList.toggle('hidden', visible > 0);
        $('#results-empty p').textContent = results.size ? 'No results match this filter.' : 'Your results will appear here.';
        updateCounters();
    }

    function clearResults() {
        whoisRequests.forEach((controller) => controller.abort());
        whoisRequests.clear();
        results = new Map();
        counts = { available: 0, unavailable: 0, error: 0 };
        $('#result-table tbody').replaceChildren();
        $('#checking span').textContent = '—';
        filter = 'all';
        applyFilter();
        updateProgress();
    }

    function cachedResponse(domain) {
        try {
            const raw = localStorage.getItem(cachePrefix + domain) || localStorage.getItem(domain);
            if (!raw) return null;
            const response = JSON.parse(raw);
            if (response.code === 200 && response.data && response.data.domain === domain && typeof response.data.is_available === 'boolean') return response;
        } catch (error) { /* Storage may be disabled, full, or contain old data. */ }
        return null;
    }

    function saveCache(response) {
        try {
            localStorage.setItem(cachePrefix + response.data.domain, JSON.stringify(response));
        } catch (error) { /* A cache failure must not interrupt a search. */ }
    }

    async function request(data, controller) {
        const timeout = setTimeout(() => controller.abort(), 7000);
        try {
            const response = await fetch(window.location.pathname + window.location.search, {
                method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest', 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8' },
                body: new URLSearchParams(data).toString(), signal: controller.signal
            });
            if (!response.ok) throw new Error('Server returned HTTP ' + response.status + '.');
            return await response.json();
        } finally {
            clearTimeout(timeout);
        }
    }

    function cell(row, text, className) {
        const td = document.createElement('td');
        if (className) td.className = className;
        td.textContent = text;
        row.appendChild(td);
        return td;
    }

    function actionLink(text, href) {
        const link = document.createElement('a');
        link.href = href;
        link.target = '_blank';
        link.rel = 'noopener noreferrer';
        link.append(document.createTextNode(text + ' '), icon('external'));
        return link;
    }

    function writeResult(domain, response, source) {
        const data = response.data || {};
        const status = response.code !== 200 || typeof data.is_available !== 'boolean' ? 'error' : data.is_available ? 'available' : 'unavailable';
        const previous = results.get(domain);
        const index = previous ? previous.index : results.size + 1;
        if (previous) counts[previous.status]--;
        counts[status]++;
        const row = document.createElement('tr');
        row.dataset.domain = domain;
        row.dataset.status = status;
        cell(row, index, 'cell-number');
        cell(row, domain, 'cell-domain');
        const badge = document.createElement('span');
        badge.className = 'availability-badge badge-' + status;
        badge.textContent = { available: 'Available', unavailable: 'Unavailable', error: 'Error' }[status];
        cell(row, '', 'cell-status').appendChild(badge);
        cell(row, domain.split('.')[0].length, 'cell-length');
        const message = response.message || '-';
        cell(row, message, 'cell-message' + (message === '-' ? ' is-empty' : ''));
        cell(row, data.whois_server || '-', 'clm-whois');
        cell(row, source, 'clm-resource');
        cell(row, data.execute_time == null ? '-' : data.execute_time + (data.execute_time_unit || ''), 'clm-exec-time');
        const actions = cell(row, '', 'cell-actions');
        if (status === 'available') {
            actions.appendChild(actionLink('register', 'https://www.godaddy.com/domainsearch/find?domainToCheck=' + encodeURIComponent(domain)));
        } else if (status === 'unavailable') {
            actions.appendChild(actionLink('visit', 'https://' + domain));
            const divider = document.createElement('span');
            divider.className = 'action-divider';
            divider.textContent = '/';
            const whois = document.createElement('button');
            whois.type = 'button';
            whois.textContent = 'whois';
            whois.dataset.whois = domain;
            whois.setAttribute('aria-expanded', 'false');
            whois.setAttribute('aria-label', 'View WHOIS for ' + domain);
            actions.append(divider, whois);
        } else {
            const retry = document.createElement('button');
            retry.type = 'button';
            retry.textContent = 'retry';
            retry.dataset.retry = domain;
            retry.disabled = busy();
            actions.appendChild(retry);
        }
        if (previous) {
            const detail = previous.row.nextElementSibling;
            if (detail && detail.classList.contains('result-whois')) detail.remove();
            previous.row.replaceWith(row);
        } else {
            $('#result-table tbody').appendChild(row);
        }
        results.set(domain, { index, row, domain, response, status, source });
        row.hidden = filter !== 'all' && filter !== status;
        $('#results-empty').classList.toggle('hidden', (filter === 'all' ? results.size : counts[filter]) > 0);
        $('#results-empty p').textContent = 'No results match this filter.';
        updateCounters();
    }

    async function processQueue() {
        const current = run;
        if (current.processing || current.state !== 'running') return;
        current.processing = true;
        try {
            while (current === run && current.state === 'running' && current.queue.length) {
                const domain = current.queue.shift();
                $('#checking span').textContent = domain;
                let response = current.cache ? cachedResponse(domain) : null;
                let source = 'cache';
                if (!response) {
                    source = window.location.hostname;
                    const controller = new AbortController();
                    current.controller = controller;
                    try {
                        response = await request({ domain }, controller);
                        if (!response || !response.data) throw new Error('Invalid server response.');
                        if (current === run && current.state !== 'stopped' && current.cache && response.code === 200 && typeof response.data.is_available === 'boolean') saveCache(response);
                    } catch (error) {
                        response = { code: 500, message: error.name === 'AbortError' ? 'Request timed out. Retry this domain.' : 'Lookup failed. Retry this domain.', data: { domain } };
                    } finally {
                        current.controller = null;
                    }
                }
                if (current !== run || current.state === 'stopped') return;
                writeResult(domain, response, source);
                current.checked++;
                updateProgress();
                if (current.checked % 10 === 0) await yieldToBrowser();
            }
            if (current === run && !current.queue.length && current.state !== 'stopped') {
                current.state = 'completed';
                updateControls();
            }
        } finally {
            current.processing = false;
            if (current === run && current.state === 'running' && current.queue.length) processQueue();
        }
    }

    function stop() {
        run.state = 'stopped';
        run.queue = [];
        if (run.controller) run.controller.abort();
        updateControls();
    }

    function retryErrors(domain) {
        if (busy()) return;
        const failed = Array.from(results.values()).filter((entry) => entry.status === 'error' && (!domain || entry.domain === domain)).map((entry) => entry.domain);
        if (!failed.length) return;
        run = { ...createRun(), state: 'running', queue: failed, total: results.size, checked: results.size - failed.length, cache: $('#cache').checked };
        updateControls();
        updateProgress();
        processQueue();
    }

    async function showWhois(button) {
        const row = button.closest('tr');
        const next = row.nextElementSibling;
        if (next && next.classList.contains('result-whois')) {
            next.remove();
            button.setAttribute('aria-expanded', 'false');
            return;
        }
        button.setAttribute('aria-expanded', 'true');
        const detail = document.createElement('tr');
        detail.className = 'result-whois';
        const td = cell(detail, '');
        td.colSpan = 9;
        const header = document.createElement('div');
        header.className = 'whois-header';
        const title = document.createElement('span');
        title.textContent = button.dataset.whois;
        const close = document.createElement('button');
        close.type = 'button';
        close.className = 'close-whois';
        close.append(document.createTextNode('close '), icon('close'));
        close.addEventListener('click', () => { detail.remove(); button.setAttribute('aria-expanded', 'false'); button.focus(); });
        const content = document.createElement('pre');
        content.textContent = 'Fetching WHOIS data…';
        header.append(title, close);
        td.append(header, content);
        row.after(detail);
        const controller = new AbortController();
        whoisRequests.set(detail, controller);
        try {
            const response = await request({ domain: button.dataset.whois, whois: 1 }, controller);
            content.textContent = response.data && response.data.whois_data ? response.data.whois_data : 'No WHOIS data returned for this domain.';
        } catch (error) {
            content.textContent = 'Could not retrieve WHOIS data. Close this panel and try again.';
        } finally {
            whoisRequests.delete(detail);
        }
    }

    function exportCSV() {
        const entries = Array.from(results.values()).filter((entry) => filter === 'all' || entry.status === filter);
        if (!entries.length) return;
        const extraColumns = [
            { enabled: $('#show-whois').checked, title: 'WHOIS server', value: (entry) => entry.response.data.whois_server || '-' },
            { enabled: $('#show-resource').checked, title: 'Source', value: (entry) => entry.source },
            { enabled: $('#show-exec-time').checked, title: 'Time', value: (entry) => entry.response.data.execute_time == null ? '-' : entry.response.data.execute_time + (entry.response.data.execute_time_unit || '') }
        ].filter((column) => column.enabled);
        const rows = [['#', 'Domain', 'Status', 'Length', 'Message', ...extraColumns.map((column) => column.title)]];
        entries.forEach((entry) => rows.push([entry.index, entry.domain, entry.status, entry.domain.split('.')[0].length, entry.response.message || '-', ...extraColumns.map((column) => column.value(entry))]));
        const quote = (value) => {
            let text = String(value);
            if (text !== '-' && /^[=+@-]/.test(text)) text = "'" + text;
            return '"' + text.replace(/"/g, '""') + '"';
        };
        const csv = '\uFEFF' + rows.map((row) => row.map(quote).join(',')).join('\r\n');
        const url = URL.createObjectURL(new Blob([csv], { type: 'text/csv;charset=utf-8;' }));
        const link = document.createElement('a');
        link.href = url;
        link.download = 'bulk-domain-availability-' + new Date().toISOString().slice(0, 10) + '.csv';
        document.body.appendChild(link);
        link.click();
        link.remove();
        setTimeout(() => URL.revokeObjectURL(url), 1000);
        toast(entries.length + ' results exported.');
    }

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (busy()) return;
        $('#form-message').classList.add('hidden');
        const options = searchOptions();
        if (!options.names.length) { showError('Enter at least one domain name.'); input.focus(); return; }
        if (options.min > options.max) { showError('Minimum length cannot be greater than maximum length.'); return; }
        if (!options.extensions.length && options.names.some((name) => !validDomain(name))) {
            showError('Select at least one extension for names without a full domain.');
            $('#extension-search').focus();
            return;
        }
        run = { ...createRun(), state: 'preparing', cache: options.cache };
        const current = run;
        clearResults();
        $('#results').classList.remove('hidden');
        updateControls();
        const generated = await generateDomains(options, current);
        if (!generated || current !== run || current.state === 'stopped') return;
        if (!generated.length) {
            current.state = 'idle';
            updateControls();
            showError('No valid domains to check. Review your names, extensions, and length limits.');
            return;
        }
        current.queue = generated;
        current.total = generated.length;
        current.state = 'running';
        updateProgress();
        updateControls();
        $('#results').scrollIntoView({ behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth', block: 'start' });
        processQueue();
    });

    form.addEventListener('reset', () => {
        if (run.controller) run.controller.abort();
        run = createRun();
        clearResults();
        updateControls();
        $('#results').classList.add('hidden');
        $('#form-message').classList.add('hidden');
        $('#advanced-options').open = false;
        expanded = false;
        setTimeout(() => { renderExtensions(); updateWordCount(); }, 0);
    });

    form.addEventListener('invalid', (event) => {
        if ($('#advanced-options').contains(event.target)) $('#advanced-options').open = true;
    }, true);

    input.addEventListener('input', () => { updateWordCount(); $('#form-message').classList.add('hidden'); });
    $('#extension-search').addEventListener('input', renderExtensions);
    $('#extension-options').addEventListener('change', (event) => {
        if (!event.target.matches('input[type="checkbox"]')) return;
        const option = extensionOptions.find((item) => item.value === event.target.value);
        option.selected = event.target.checked;
        event.target.closest('label').classList.toggle('is-selected', event.target.checked);
        updateExtensionCount();
        $('#form-message').classList.add('hidden');
    });
    $('#browse-extensions').addEventListener('click', () => { expanded = !expanded; renderExtensions(); });
    $('#clear-extensions').addEventListener('click', () => { extensionOptions.forEach((option) => { option.selected = false; }); renderExtensions(); });
    $('#try-example').addEventListener('click', () => { input.value = 'brightidea\npixelgarden\nmadebyyou'; updateWordCount(); input.focus(); });
    $('#remove-unused').addEventListener('click', () => {
        const options = searchOptions();
        if (options.min > options.max) { showError('Minimum length cannot be greater than maximum length.'); return; }
        input.value = options.names.filter((name) => validLength(validDomain(name) ? name.split('.')[0] : name, options)).join('\n');
        updateWordCount();
        toast('Names deduplicated and filtered by length.');
    });
    $('#clear-cache').addEventListener('click', () => {
        try {
            let removed = 0;
            for (let index = localStorage.length - 1; index >= 0; index--) {
                const key = localStorage.key(index);
                if (key && (key.startsWith(cachePrefix) || (validDomain(key) && cachedResponse(key)))) { localStorage.removeItem(key); removed++; }
            }
            toast(removed ? 'Domain cache cleared.' : 'No cached domain results.');
        } catch (error) { toast('Browser storage is unavailable.'); }
    });
    $('#pause').addEventListener('click', () => { run.state = 'paused'; updateControls(); });
    $('#resume').addEventListener('click', () => { run.state = 'running'; updateControls(); processQueue(); });
    $('#stop').addEventListener('click', stop);
    $('#retry-error').addEventListener('click', () => retryErrors());
    $('#export-csv').addEventListener('click', exportCSV);
    $$('.result-tab').forEach((button) => button.addEventListener('click', () => { filter = button.dataset.filter; applyFilter(); }));
    ['whois', 'resource', 'exec-time'].forEach((column) => $('#show-' + column).addEventListener('change', (event) => $('#result-table').classList.toggle('show-' + column, event.target.checked)));
    $('#result-table').addEventListener('click', (event) => {
        const whois = event.target.closest('[data-whois]');
        const retry = event.target.closest('[data-retry]');
        if (whois) showWhois(whois);
        if (retry) retryErrors(retry.dataset.retry);
    });
    document.addEventListener('click', (event) => {
        const columns = $('.column-options');
        if (columns.open && !columns.contains(event.target)) columns.open = false;
    });
    document.addEventListener('keydown', (event) => { if (event.key === 'Escape') $('.column-options').open = false; });

    $('#extension-total').textContent = extensions.length;
    renderExtensions();
    updateWordCount();
    updateControls();
})();
