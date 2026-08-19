<script>
(() => {
    if (typeof TomSelect === 'undefined' || window.SearchableSelectsReady) return;
    window.SearchableSelectsReady = true;

    const defaultPlaceholder = (el) => {
        return el.dataset.placeholder
            || el.getAttribute('placeholder')
            || el.querySelector('option[value=""]')?.textContent?.trim()
            || 'Buscar…';
    };

    window.destroySearchableSelect = (el) => {
        if (el?.tomselect) {
            el.tomselect.destroy();
        }
    };

    window.refreshSearchableSelect = (el) => {
        if (!el) return;
        window.destroySearchableSelect(el);
        window.initSearchableSelects(el);
    };

    window.initSearchableSelects = (root = document) => {
        const nodes = [];
        if (root instanceof HTMLSelectElement) {
            nodes.push(root);
        } else if (root instanceof Element || root instanceof Document) {
            root.querySelectorAll('select').forEach((el) => nodes.push(el));
        }

        nodes.forEach((el) => {
            if (el.dataset.noSearch !== undefined) return;
            if (el.closest('template')) return;
            if (el.tomselect) return;

            const wasDisabled = el.disabled === true;
            // Inicializar aunque esté disabled (p. ej. panel de cliente oculto)
            // y luego deshabilitar el control Tom Select.
            if (wasDisabled) el.disabled = false;

            const isMultiple = el.multiple === true;

            // Importante: NO poner maxItems:1 en selects normales.
            // Eso fuerza modo "multi con límite 1" y bloquea cambiar la opción.
            const options = {
                allowEmptyOption: true,
                create: false,
                maxOptions: null,
                closeAfterSelect: true,
                placeholder: defaultPlaceholder(el),
                plugins: {
                    clear_button: { title: 'Limpiar' },
                },
                render: {
                    no_results: () => '<div class="no-results">Sin resultados</div>',
                },
            };

            if (isMultiple) {
                options.maxItems = null;
                options.plugins.remove_button = { title: 'Quitar' };
                options.closeAfterSelect = false;
            }

            const ts = new TomSelect(el, options);

            // Evento propio con bubbles para formularios que usan delegación.
            ts.on('change', (value) => {
                el.dispatchEvent(new CustomEvent('searchable:change', {
                    bubbles: true,
                    detail: { value },
                }));
            });

            if (wasDisabled) {
                el.disabled = true;
                ts.disable();
            }
        });
    };

    const boot = () => window.initSearchableSelects(document);
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
</script>
