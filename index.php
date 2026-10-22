<?php

error_reporting(E_ALL);
ini_set('display_errors', 0);

require_once('classes' . DIRECTORY_SEPARATOR . 'lookup.php');

$serverList = dirname(__FILE__) . "/misc/servers/servers.json";

if ($_POST) (new Lookup())->run($_POST, $serverList);

$icon = function ($name, $class = '') {
    return '<svg class="icon ' . $class . '" aria-hidden="true"><use href="#icon-' . $name . '"></use></svg>';
};
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#101210">
    <meta name="description" content="A minimal workspace for bulk domain availability checks, word combinations, WHOIS lookups, and CSV exports.">
    <title>Bulk Domain Availability</title>
    <link rel="icon" href="favicon.ico">
    <link rel="stylesheet" href="styles/style.css">
    <script src="scripts/script.js" defer></script>
</head>
<body>
    <svg class="svg-sprite" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
        <symbol id="icon-terminal" viewBox="0 0 24 24"><path d="m4 6 6 6-6 6m9 0h7"/></symbol>
        <symbol id="icon-globe" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><ellipse cx="12" cy="12" rx="4" ry="9"/><path d="M3 12h18"/></symbol>
        <symbol id="icon-search" viewBox="0 0 24 24"><circle cx="10.8" cy="10.8" r="6.8"/><path d="m16 16 4.5 4.5"/></symbol>
        <symbol id="icon-arrow" viewBox="0 0 24 24"><path d="M4 12h15m-6-6 6 6-6 6"/></symbol>
        <symbol id="icon-external" viewBox="0 0 24 24"><path d="M14 4h6v6m0-6L10 14M10 4H5a1 1 0 0 0-1 1v14a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1v-5"/></symbol>
        <symbol id="icon-chevron" viewBox="0 0 24 24"><path d="m7 10 5 5 5-5"/></symbol>
        <symbol id="icon-download" viewBox="0 0 24 24"><path d="M12 3v12m-5-5 5 5 5-5M4 16v4h16v-4"/></symbol>
        <symbol id="icon-sliders" viewBox="0 0 24 24"><path d="M4 7h7m4 0h5M4 17h3m4 0h9"/><circle cx="13" cy="7" r="2"/><circle cx="9" cy="17" r="2"/></symbol>
        <symbol id="icon-check" viewBox="0 0 24 24"><path d="m5 12 4 4L19 6"/></symbol>
        <symbol id="icon-close" viewBox="0 0 24 24"><path d="m6 6 12 12M18 6 6 18"/></symbol>
        <symbol id="icon-pause" viewBox="0 0 24 24"><path d="M8 5v14M16 5v14"/></symbol>
        <symbol id="icon-play" viewBox="0 0 24 24"><path d="m7 4 14 8-14 8V4Z"/></symbol>
        <symbol id="icon-stop" viewBox="0 0 24 24"><rect x="5" y="5" width="14" height="14" rx="1"/></symbol>
        <symbol id="icon-info" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 11v6m0-10v.1"/></symbol>
        <symbol id="icon-github" viewBox="0 0 24 24"><path d="M9 19c-4 1-4-2-6-2m12 5v-3.9a3.4 3.4 0 0 0-.9-2.7c3-.3 6.2-1.5 6.2-6.9A5.4 5.4 0 0 0 18.8 5a5 5 0 0 0-.1-3.5S17.5 1.1 15 2.8a13.1 13.1 0 0 0-6 0C6.5 1.1 5.3 1.5 5.3 1.5A5 5 0 0 0 5.2 5a5.4 5.4 0 0 0-1.5 3.5c0 5.4 3.2 6.6 6.2 6.9A3.4 3.4 0 0 0 9 18.1V22"/></symbol>
    </svg>
    <a class="skip-link" href="#domain-input">Skip to domain checker</a>

    <header class="site-header">
        <div class="shell header-inner">
            <a href="./" class="brand" aria-label="Bulk Domain Availability home"><?= $icon('terminal') ?><span>bulkdomain<span class="brand-cursor">_</span></span></a>
            <nav class="main-nav" aria-label="Main navigation"><a class="nav-active" href="#checker" aria-current="page">checker</a><a href="#how-it-works">guide</a></nav>
            <a class="github-link" href="https://github.com/abaron/bulk-domain-availability" target="_blank" rel="noopener noreferrer"><?= $icon('github') ?><span>source</span><?= $icon('external', 'external-icon') ?></a>
        </div>
    </header>

    <main class="shell">
        <section class="hero" aria-labelledby="page-title">
            <div class="hero-copy"><p class="eyebrow"><span class="status-dot"></span> BULK DOMAIN AVAILABILITY</p><h1 id="page-title">Find your next <span>domain_</span></h1><p class="hero-description">Check names in bulk. Explore combinations. Find what's available.</p></div>
            <div class="terminal-note" aria-label="Supported features"><p><span class="terminal-prompt">$</span> bulkdomain --help</p><dl><div><dt>input</dt><dd>names / full domains</dd></div><div><dt>targets</dt><dd><span id="extension-total">374</span> extensions</dd></div><div><dt>output</dt><dd>availability + WHOIS</dd></div></dl></div>
        </section>

        <noscript><p class="notice notice-error">JavaScript is required to use the domain checker.</p></noscript>

        <section id="checker" class="checker-card" aria-labelledby="checker-title">
            <div class="card-heading"><div class="card-heading-title"><?= $icon('terminal') ?><h2 id="checker-title">new search</h2></div><span class="subtle-label">[ domain workspace ]</span></div>
            <form action="" method="post" accept-charset="utf-8" id="availability-form">
                <fieldset id="search-fields">
                    <legend class="sr-only">Domain search options</legend>
                    <div class="input-columns">
                        <div class="domain-panel">
                            <div class="field-heading"><label for="domain-input"><span class="step-number">01</span> Domain names</label><button type="button" class="text-button" id="try-example">load example <?= $icon('arrow') ?></button></div>
                            <div class="textarea-wrap"><textarea id="domain-input" name="domains" placeholder="your-next-idea&#10;another-project&#10;somethinggreat.com" required spellcheck="false" autocapitalize="none" autocomplete="off" autocorrect="off" aria-describedby="domain-hint"></textarea></div>
                            <div class="input-meta"><p id="domain-hint">Separate with spaces, commas, or new lines.</p><span id="word-count">0 names</span></div>
                        </div>
                        <div class="extension-panel">
                            <div class="field-heading"><span id="extensions-label"><span class="step-number">02</span> Extensions</span><span id="extension-count" class="count-badge">3 selected</span></div>
                            <label class="extension-search" for="extension-search"><?= $icon('search') ?><input id="extension-search" type="search" placeholder="Search extensions…" autocomplete="off" spellcheck="false" aria-controls="extension-options"><span class="sr-only">Search extensions</span></label>
                            <select name="tlds" id="tld-select" multiple hidden aria-hidden="true" tabindex="-1"><?= Lookup::htmlOptions($serverList); ?></select>
                            <div id="extension-options" class="extension-options" role="group" aria-labelledby="extensions-label"></div>
                            <p id="extension-empty" class="extension-empty hidden">No matching extensions.</p>
                            <div class="extension-footer"><button type="button" class="text-button muted-button" id="browse-extensions" aria-expanded="false" aria-controls="extension-options">all extensions <?= $icon('chevron') ?></button><button type="button" class="text-button muted-button" id="clear-extensions">clear selection</button></div>
                        </div>
                    </div>

                    <details class="advanced-options" id="advanced-options">
                        <summary><span><?= $icon('sliders') ?> Advanced options <span class="optional-label">prefix / suffix / combinations</span></span><?= $icon('chevron', 'details-chevron') ?></summary>
                        <div class="advanced-content">
                            <div class="advanced-fields">
                                <div class="field"><label for="prefix">Prefix <span>optional</span></label><input id="prefix" name="prefix" type="text" placeholder="e.g. get" spellcheck="false" autocapitalize="none"></div>
                                <div class="field"><label for="suffix">Suffix <span>optional</span></label><input id="suffix" name="suffix" type="text" placeholder="e.g. labs" spellcheck="false" autocapitalize="none"></div>
                                <div class="field"><label for="min-length">Min. length</label><input id="min-length" name="min-length" type="number" placeholder="1" min="1" max="63" inputmode="numeric"></div>
                                <div class="field"><label for="max-length">Max. length</label><input id="max-length" name="max-length" type="number" placeholder="63" min="1" max="63" inputmode="numeric"></div>
                            </div>
                            <div class="combine-options"><label class="checkbox-label" for="combine-a"><input type="checkbox" name="combine" value="1" id="combine-a"><span>Combine words <small>word + list → wordlist</small></span></label><label class="checkbox-label" for="combine-b"><input type="checkbox" name="combine" value="2" id="combine-b"><span>Combine with hyphen <small>word + list → word-list</small></span></label><button type="button" id="remove-unused" class="text-button">clean up names</button></div>
                        </div>
                    </details>
                </fieldset>

                <div id="form-message" class="notice notice-error hidden" role="alert"></div>
                <div class="form-bottom">
                    <div class="cache-controls"><label for="cache" class="checkbox-label"><input type="checkbox" name="cache" value="1" id="cache" checked><span>Cache results <span class="help-tooltip" title="Save results in this browser for repeat searches."><?= $icon('info') ?></span></span></label><button type="button" id="clear-cache" class="text-button muted-button">clear cache</button></div>
                    <div class="search-actions"><button type="reset" class="button button-ghost" id="reset">Reset</button><button type="submit" id="search" class="button button-primary"><?= $icon('search') ?><span>Check availability</span><?= $icon('arrow') ?></button></div>
                </div>
            </form>
        </section>

        <section id="results" class="results-card result hidden" aria-labelledby="results-title">
            <div class="results-heading"><div><div class="section-kicker">OUTPUT / DOMAIN LOOKUP</div><h2 id="results-title">Search results<span class="brand-cursor">_</span></h2></div><div class="run-actions hidden" id="run-actions" role="group" aria-label="Search controls"><button type="button" id="stop" class="button button-danger hidden"><?= $icon('stop') ?><span>Stop</span></button><button type="button" id="pause" class="button button-primary hidden"><?= $icon('pause') ?><span>Pause</span></button><button type="button" id="resume" class="button button-primary hidden"><?= $icon('play') ?><span>Resume</span></button></div><button type="button" id="export-csv" class="button button-secondary" disabled><?= $icon('download') ?><span>Export CSV</span></button></div>
            <div class="stats-grid"><div class="stat-card"><span class="stat-label"><span class="status-dot dot-green"></span>Available</span><strong id="available-counter"><span>0</span></strong></div><div class="stat-card"><span class="stat-label"><span class="status-dot dot-gray"></span>Unavailable</span><strong id="unavailable-counter"><span>0</span></strong></div><div class="stat-card"><span class="stat-label"><span class="status-dot dot-amber"></span>Errors <button type="button" class="text-button retry-error hidden" id="retry-error">retry</button></span><strong id="error-counter"><span>0</span></strong></div></div>
            <div class="progress-section"><div class="progress-label"><span id="search-status" role="status">Ready</span><span class="progress-numbers"><span id="domains-checked">0</span> / <span id="domains-length">0</span> checked <span class="progress-percent"><span id="progress">0</span>%</span></span></div><div id="search-progress" class="progress-track" role="progressbar" aria-label="Domain checking progress" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0"><span id="progress-fill"></span></div><p id="checking">target <span>—</span></p></div>
            <div class="results-toolbar"><div class="result-tabs" role="group" aria-label="Filter results"><button type="button" class="result-tab is-active" data-filter="all" aria-pressed="true">All <span id="total-results">0</span></button><button type="button" class="result-tab" data-filter="available" aria-pressed="false">Available</button><button type="button" class="result-tab" data-filter="unavailable" aria-pressed="false">Unavailable</button><button type="button" class="result-tab" data-filter="error" aria-pressed="false">Errors</button></div><details class="column-options"><summary><?= $icon('sliders') ?> Columns <?= $icon('chevron') ?></summary><div class="column-options-menu"><label class="checkbox-label"><input type="checkbox" id="show-whois">WHOIS server</label><label class="checkbox-label"><input type="checkbox" id="show-exec-time">Execution time</label><label class="checkbox-label"><input type="checkbox" id="show-resource">Source</label></div></details></div>
            <div class="table-scroll" tabindex="0" role="region" aria-label="Domain search results"><table id="result-table"><caption class="sr-only">Domain availability results</caption><thead><tr><th scope="col" class="cell-number">#</th><th scope="col">Domain</th><th scope="col">Status</th><th scope="col" class="cell-length">Length</th><th scope="col">Message</th><th scope="col" class="clm-whois">WHOIS server</th><th scope="col" class="clm-resource">Source</th><th scope="col" class="clm-exec-time">Time</th><th scope="col" class="cell-actions">Actions</th></tr></thead><tbody></tbody></table><div id="results-empty" class="results-empty"><span aria-hidden="true">[ no output ]</span><p>Your results will appear here.</p></div></div>
            <p class="results-footnote"><?= $icon('info') ?> Availability can change. Confirm with a registrar before purchasing.</p>
        </section>

        <section id="how-it-works" class="how-it-works" aria-labelledby="how-title">
            <div class="how-heading"><h2 id="how-title"><span class="terminal-prompt">#</span> Quick reference</h2><span class="subtle-label">three steps. one workspace.</span></div>
            <div class="steps-grid"><article class="help-step"><span class="help-number">01 /</span><div><h3>Enter names</h3><p>Paste keywords or full domains. Use advanced options to add a prefix, suffix, or combine words.</p></div></article><article class="help-step"><span class="help-number">02 /</span><div><h3>Select targets</h3><p>Choose extensions and run the check. Pause, resume, or stop the queue whenever you need.</p></div></article><article class="help-step"><span class="help-number">03 /</span><div><h3>Inspect &amp; export</h3><p>Filter available names, inspect WHOIS data, or export the current view as a CSV.</p></div></article></div>
        </section>
    </main>

    <footer class="site-footer shell"><span class="footer-brand"><?= $icon('terminal') ?> bulk domain availability</span><span>by <a href="https://github.com/abaron" target="_blank" rel="noopener noreferrer">abaron</a><span class="footer-divider">/</span>&copy; <?= date('Y') ?></span></footer>
    <div id="toast" class="toast hidden" role="status"></div>
</body>
</html>
