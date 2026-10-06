@php
    $customer = $customer ?? null;
    $namePrefix = $namePrefix ?? '';
    $field = fn (string $name) => $namePrefix !== '' ? "{$namePrefix}[{$name}]" : $name;
    $old = fn (string $name, $default = null) => old(
        $namePrefix !== '' ? "{$namePrefix}.{$name}" : $name,
        $customer?->{$name} ?? $default
    );
    $showCode = $showCode ?? true;
    $showActive = $showActive ?? true;
    $codeReadonly = $codeReadonly ?? (bool) $customer;
    $nextCode = $nextCode ?? \App\Models\Customer::nextCode();
    $forceEmptyName = (bool) ($forceEmptyName ?? false);
    $nameValue = $forceEmptyName ? (string) old($namePrefix !== '' ? "{$namePrefix}.name" : 'name', '') : $old('name');
    $documentTypeValue = $old('document_type', 'N/A') ?: 'N/A';
    $departments = \App\Support\ElSalvadorGeo::departmentNames();
    $selectedDepartment = $old('department');
    $selectedMunicipality = $old('municipality');
    $municipalities = filled($selectedDepartment)
        ? \App\Support\ElSalvadorGeo::municipalityNames($selectedDepartment)
        : [];
@endphp

@if ($showCode)
    <div class="grid-2">
        <div class="field">
            <label for="{{ $namePrefix }}code">Código</label>
            @if ($customer && $codeReadonly)
                <input id="{{ $namePrefix }}code" type="text" value="{{ $customer->code }}" readonly>
                <p class="muted" style="margin:.35rem 0 0">Código automático (solo lectura).</p>
            @else
                <input id="{{ $namePrefix }}code" type="text" value="{{ $nextCode }}" readonly>
                <p class="muted" style="margin:.35rem 0 0">Se genera automáticamente al guardar (solo lectura).</p>
            @endif
        </div>
        <div class="field">
            <label for="{{ $namePrefix }}name">Nombre *</label>
            <input
                id="{{ $namePrefix }}name"
                type="text"
                name="{{ $field('name') }}"
                value="{{ $nameValue }}"
                required
                autocomplete="off"
                placeholder=""
            >
        </div>
    </div>
@else
    <div class="field">
        <label for="{{ $namePrefix }}name">Nombre *</label>
        <input
            id="{{ $namePrefix }}name"
            type="text"
            name="{{ $field('name') }}"
            value="{{ $nameValue }}"
            required
            autocomplete="off"
            placeholder=""
        >
    </div>
@endif

<div class="grid-2" data-document-fields>
    <div class="field">
        <label for="{{ $namePrefix }}document_type">Tipo documento *</label>
        <select id="{{ $namePrefix }}document_type" name="{{ $field('document_type') }}" required data-document-type autocomplete="off">
            @foreach (['N/A', 'NIT', 'DUI', 'PASAPORTE', 'OTRO'] as $type)
                <option value="{{ $type }}" @selected($documentTypeValue === $type)>{{ $type }}</option>
            @endforeach
        </select>
    </div>
    <div class="field" data-document-number-wrap style="{{ $documentTypeValue === 'N/A' ? 'display:none' : '' }}">
        <label for="{{ $namePrefix }}document_number">Número documento</label>
        <input
            id="{{ $namePrefix }}document_number"
            type="text"
            name="{{ $field('document_number') }}"
            value="{{ $documentTypeValue === 'N/A' ? '' : $old('document_number') }}"
            data-document-number
            autocomplete="off"
            @disabled($documentTypeValue === 'N/A')
        >
    </div>
</div>

<div class="grid-2">
    <div class="field">
        <label for="{{ $namePrefix }}email">Email</label>
        <input id="{{ $namePrefix }}email" type="email" name="{{ $field('email') }}" value="{{ $old('email') }}">
    </div>
    <div class="field">
        <label for="{{ $namePrefix }}phone">Teléfono</label>
        <input id="{{ $namePrefix }}phone" type="text" name="{{ $field('phone') }}" value="{{ $old('phone') }}">
        @isset($phoneAlertId)
            <div
                id="{{ $phoneAlertId }}"
                class="flash"
                style="display:none;background:#fef2f2;color:#991b1b;border-color:#fecaca;margin-top:.55rem;margin-bottom:0"
                role="alert"
            ></div>
        @endisset
        @isset($phoneDuplicateAlertId)
            <div
                id="{{ $phoneDuplicateAlertId }}"
                class="flash"
                style="display:none;background:#fffbeb;color:#92400e;border-color:#fde68a;margin-top:.55rem;margin-bottom:0"
                role="alert"
            ></div>
        @endisset
        @isset($phonePrefillHintId)
            <p id="{{ $phonePrefillHintId }}" class="muted" style="display:none;margin:.4rem 0 0;font-size:.85rem"></p>
        @endisset
    </div>
</div>

<div class="field">
    <label for="{{ $namePrefix }}address">Dirección</label>
    <textarea id="{{ $namePrefix }}address" name="{{ $field('address') }}">{{ $old('address') }}</textarea>
</div>

<div data-geo-root>
    <div class="grid-2">
        <div class="field">
            <label for="{{ $namePrefix }}department">Departamento</label>
            <select
                id="{{ $namePrefix }}department"
                name="{{ $field('department') }}"
                data-geo-department
            >
                <option value="">— Selecciona —</option>
                @foreach ($departments as $department)
                    <option value="{{ $department }}" @selected($selectedDepartment === $department)>{{ $department }}</option>
                @endforeach
            </select>
        </div>
        <div class="field">
            <label for="{{ $namePrefix }}municipality">Municipio</label>
            <select
                id="{{ $namePrefix }}municipality"
                name="{{ $field('municipality') }}"
                data-geo-municipality
                @disabled(blank($selectedDepartment))
            >
                <option value="">— Selecciona —</option>
                @foreach ($municipalities as $municipality)
                    <option value="{{ $municipality }}" @selected($selectedMunicipality === $municipality)>{{ $municipality }}</option>
                @endforeach
            </select>
        </div>
    </div>
    <div class="grid-2">
        <div class="field">
            <label for="{{ $namePrefix }}country">País</label>
            <input id="{{ $namePrefix }}country" type="text" name="{{ $field('country') }}" value="{{ $old('country', 'El Salvador') }}">
        </div>
        <div class="field">
            <label for="{{ $namePrefix }}postal_code">Código postal</label>
            <input
                id="{{ $namePrefix }}postal_code"
                type="text"
                name="{{ $field('postal_code') }}"
                value="{{ $old('postal_code') }}"
                data-geo-postal
            >
            <p class="muted" style="margin:.35rem 0 0">Se completa al elegir municipio (puedes editarlo).</p>
        </div>
    </div>
</div>

@if ($showActive || ! ($compact ?? false))
    <div class="field">
        <label for="{{ $namePrefix }}notes">Notas</label>
        <textarea id="{{ $namePrefix }}notes" name="{{ $field('notes') }}">{{ $old('notes') }}</textarea>
    </div>
@endif

@if ($showActive)
    <div class="field">
        <input type="hidden" name="{{ $field('is_active') }}" value="0">
        <label>
            <input type="checkbox" name="{{ $field('is_active') }}" value="1" @checked((bool) $old('is_active', true))>
            Activo
        </label>
    </div>
@endif

<script>
(() => {
    const syncDocumentNumber = (root) => {
        const typeSelect = root.querySelector('[data-document-type]');
        const wrap = root.querySelector('[data-document-number-wrap]');
        const input = root.querySelector('[data-document-number]');
        if (!typeSelect || !wrap || !input) return;

        const isNa = typeSelect.value === 'N/A';
        wrap.style.display = isNa ? 'none' : '';
        input.disabled = isNa || typeSelect.disabled;
        if (isNa) input.value = '';
    };

    window.syncCustomerDocumentFields = () => {
        document.querySelectorAll('[data-document-fields]').forEach(syncDocumentNumber);
    };

    document.querySelectorAll('[data-document-fields]').forEach((root) => {
        if (root.dataset.docBound === '1') return;
        root.dataset.docBound = '1';
        root.querySelector('[data-document-type]')?.addEventListener('change', () => syncDocumentNumber(root));
        syncDocumentNumber(root);
    });
})();
</script>
