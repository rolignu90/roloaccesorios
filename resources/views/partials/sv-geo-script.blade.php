<script type="application/json" id="sv-geo-data">@json(\App\Support\ElSalvadorGeo::forJs())</script>
<script>
(() => {
    const dataEl = document.getElementById('sv-geo-data');
    if (!dataEl || window.SvGeoReady) return;
    window.SvGeoReady = true;

    const departments = JSON.parse(dataEl.textContent || '[]');

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

        window.initSearchableSelects?.(muniSelect);
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

    window.initSvGeoCascades = (scope = document) => {
        scope.querySelectorAll('[data-geo-root]').forEach(bindRoot);
    };

    document.addEventListener('DOMContentLoaded', () => window.initSvGeoCascades());
    window.initSvGeoCascades();
})();
</script>
