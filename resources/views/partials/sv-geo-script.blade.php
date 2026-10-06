<script type="application/json" id="sv-geo-data">@json(\App\Support\ElSalvadorGeo::forJs())</script>
<script>
(() => {
    const dataEl = document.getElementById('sv-geo-data');
    if (!dataEl || window.SvGeoReady) return;
    window.SvGeoReady = true;

    const departments = JSON.parse(dataEl.textContent || '[]');

    const setSelectValue = (select, value, silent = false) => {
        if (!select) return;
        const next = value ?? '';
        if (select.tomselect) {
            select.tomselect.setValue(next, silent);
            return;
        }
        select.value = next;
        if (!silent) {
            select.dispatchEvent(new Event('change', { bubbles: true }));
        }
    };

    const fillMunicipalities = (root, preserve = true) => {
        const deptSelect = root.querySelector('[data-geo-department]');
        const muniSelect = root.querySelector('[data-geo-municipality]');
        const postalInput = root.querySelector('[data-geo-postal]');
        if (!deptSelect || !muniSelect) return;

        const selectedDept = deptSelect.value;
        const previousMuni = preserve ? (muniSelect.dataset.selected || muniSelect.value) : '';
        const department = departments.find((d) => d.name === selectedDept);

        window.destroySearchableSelect?.(muniSelect);
        muniSelect.innerHTML = '<option value="">— Selecciona —</option>';
        if (!department) {
            muniSelect.disabled = true;
            if (postalInput && !postalInput.dataset.manual) postalInput.value = '';
            window.initSearchableSelects?.(muniSelect);
            return;
        }

        muniSelect.disabled = false;
        department.municipalities.forEach((m) => {
            const option = document.createElement('option');
            option.value = m.name;
            option.textContent = m.name;
            option.dataset.postal = m.postal_code || '';
            if (m.name === previousMuni) option.selected = true;
            muniSelect.appendChild(option);
        });

        if (previousMuni) {
            muniSelect.value = previousMuni;
            muniSelect.dataset.selected = previousMuni;
        }

        window.initSearchableSelects?.(muniSelect);
        if (muniSelect.tomselect && previousMuni) {
            muniSelect.tomselect.setValue(previousMuni, true);
        }
        syncPostal(root);
    };

    const syncPostal = (root) => {
        const muniSelect = root.querySelector('[data-geo-municipality]');
        const postalInput = root.querySelector('[data-geo-postal]');
        if (!muniSelect || !postalInput || postalInput.dataset.manual === '1') return;
        const option = muniSelect.selectedOptions[0];
        postalInput.value = option?.dataset?.postal || '';
    };

    const bindRoot = (root) => {
        if (root.dataset.geoBound === '1') return;
        root.dataset.geoBound = '1';

        const muniSelect = root.querySelector('[data-geo-municipality]');
        const postalInput = root.querySelector('[data-geo-postal]');
        if (muniSelect?.value) muniSelect.dataset.selected = muniSelect.value;

        fillMunicipalities(root, true);

        root.querySelector('[data-geo-department]')?.addEventListener('change', () => {
            if (muniSelect) muniSelect.dataset.selected = '';
            if (postalInput) delete postalInput.dataset.manual;
            fillMunicipalities(root, false);
        });

        muniSelect?.addEventListener('change', () => {
            if (postalInput) delete postalInput.dataset.manual;
            muniSelect.dataset.selected = muniSelect.value;
            syncPostal(root);
        });

        postalInput?.addEventListener('input', () => {
            postalInput.dataset.manual = '1';
        });
    };

    /**
     * Prefill departamento + municipio (compatible con Tom Select).
     */
    window.setSvGeoValues = (rootOrScope, department, municipality) => {
        const root = rootOrScope?.matches?.('[data-geo-root]')
            ? rootOrScope
            : rootOrScope?.querySelector?.('[data-geo-root]');
        if (!root) return;

        bindRoot(root);

        const deptSelect = root.querySelector('[data-geo-department]');
        const muniSelect = root.querySelector('[data-geo-municipality]');
        const postalInput = root.querySelector('[data-geo-postal]');
        if (!deptSelect) return;

        if (postalInput) delete postalInput.dataset.manual;
        if (muniSelect) muniSelect.dataset.selected = municipality || '';

        setSelectValue(deptSelect, department || '', true);
        fillMunicipalities(root, true);

        if (municipality && muniSelect) {
            muniSelect.dataset.selected = municipality;
            setSelectValue(muniSelect, municipality, true);
            syncPostal(root);
        }
    };

    window.initSvGeoCascades = (scope = document) => {
        scope.querySelectorAll('[data-geo-root]').forEach(bindRoot);
    };

    document.addEventListener('DOMContentLoaded', () => window.initSvGeoCascades());
    window.initSvGeoCascades();
})();
</script>
