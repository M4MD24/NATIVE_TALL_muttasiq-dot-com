<script>
    (function() {
        const storageKey = 'jsErrorLog';
        const maxEntries = 50;
        const overlayId = 'js-error-overlay';
        const bodyId = 'js-error-body';

        const loadEntries = () => {
            try {
                return JSON.parse(localStorage.getItem(storageKey)) || [];
            } catch (error) {
                return [];
            }
        };

        const saveEntries = (entries) => {
            try {
                localStorage.setItem(storageKey, JSON.stringify(entries.slice(-maxEntries)));
            } catch (error) {}
        };

        const formatEntry = (entry) => {
            const parts = [
                `[${entry.time}]`,
                `[${entry.type}]`,
                entry.message || 'Unknown error',
            ];

            if (entry.source) {
                parts.push(`(${entry.source}:${entry.line || 0}:${entry.column || 0})`);
            }

            if (entry.stack) {
                parts.push(`\n${entry.stack}`);
            }

            return parts.join(' ');
        };

        const ensureOverlay = () => {
            if (document.getElementById(overlayId)) {
                return;
            }

            if (!document.body) {
                document.addEventListener(
                    'DOMContentLoaded',
                    () => {
                        ensureOverlay();
                    }, {
                        once: true
                    },
                );
                return;
            }

            const overlay = document.createElement('div');
            overlay.id = overlayId;
            overlay.style.cssText = [
                'position:fixed',
                'inset:0',
                'z-index:2147483647',
                'background:rgba(0,0,0,0.85)',
                'color:#f8f8f2',
                'font-family:ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace',
                'display:none',
            ].join(';');

            const toolbar = document.createElement('div');
            toolbar.style.cssText = [
                'display:flex',
                'gap:8px',
                'align-items:center',
                'padding:12px',
                'border-bottom:1px solid rgba(255,255,255,0.2)',
            ].join(';');

            const title = document.createElement('div');
            title.textContent = 'JS Errors';
            title.style.cssText = 'flex:1;font-weight:600';

            const closeButton = document.createElement('button');
            closeButton.type = 'button';
            closeButton.textContent = 'Close';
            closeButton.style.cssText = 'padding:6px 10px';
            closeButton.addEventListener('click', () => {
                overlay.style.display = 'none';
            });

            const clearButton = document.createElement('button');
            clearButton.type = 'button';
            clearButton.textContent = 'Clear';
            clearButton.style.cssText = 'padding:6px 10px';
            clearButton.addEventListener('click', () => {
                saveEntries([]);
                const body = overlay.querySelector(`#${bodyId}`);
                if (body) {
                    body.textContent = '';
                }
            });

            toolbar.appendChild(title);
            toolbar.appendChild(clearButton);
            toolbar.appendChild(closeButton);

            const body = document.createElement('pre');
            body.id = bodyId;
            body.style.cssText = [
                'margin:0',
                'padding:12px',
                'white-space:pre-wrap',
                'word-break:break-word',
                'max-height:calc(100vh - 56px)',
                'overflow:auto',
            ].join(';');

            overlay.appendChild(toolbar);
            overlay.appendChild(body);
            document.body.appendChild(overlay);
        };

        const renderEntries = (entries) => {
            ensureOverlay();

            const overlay = document.getElementById(overlayId);
            const body = document.getElementById(bodyId);

            if (!overlay || !body) {
                return;
            }

            body.textContent = entries.map(formatEntry).join('\n\n');
        };

        const showOverlay = () => {
            ensureOverlay();
            const overlay = document.getElementById(overlayId);
            if (overlay) {
                overlay.style.display = 'block';
            }
        };

        const addEntry = (entry) => {
            const entries = loadEntries();
            entries.push(entry);
            saveEntries(entries);
            renderEntries(entries);
            showOverlay();
        };

        window.addEventListener('error', (event) => {
            addEntry({
                type: 'error',
                time: new Date().toISOString(),
                message: event.message,
                source: event.filename,
                line: event.lineno,
                column: event.colno,
                stack: event.error ? event.error.stack : null,
            });
        });

        window.addEventListener('unhandledrejection', (event) => {
            const reason = event.reason;
            addEntry({
                type: 'promise',
                time: new Date().toISOString(),
                message: reason && reason.message ? reason.message : String(reason),
                stack: reason && reason.stack ? reason.stack : null,
            });
        });

        const existingEntries = loadEntries();
        if (existingEntries.length > 0) {
            renderEntries(existingEntries);
        }
    })();
</script>
