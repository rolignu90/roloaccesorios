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

            const ts = new TomSelect(el, {
                allowEmptyOption: true,
                create: false,
                maxOptions: null,
                placeholder: defaultPlaceholder(el),
                plugins: {
                    clear_button: { title: 'Limpiar' },
                },
                render: {
                    no_results: () => '<div class="no-results">Sin resultados</div>',
                },
            });

            // Evento propio con bubbles para formularios que usan delegación.
            ts.on('change', (value) => {
                el.dispatchEvent(new CustomEvent('searchable:change', {
                    bubbles: true,
                    detail: { value },
                }));
            });
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
