@extends('layouts.app')

@section('title', 'Entrada de stock')

@section('content')
@php
    $productMap = $products->mapWithKeys(function ($product) {
        return [
            $product->id => $product->productSuppliers->map(fn ($row) => [
                'id' => $row->supplier_id,
                'name' => $row->supplier?->code.' — '.$row->supplier?->name,
                'purchase_price' => (string) $row->purchase_price,
                'is_preferred' => $row->is_preferred,
            ])->values(),
        ];
    });
    $pendingOnDemand = $pendingOnDemand ?? [];
@endphp

<div class="topbar">
    <div>
        <h1>Entrada de stock (FIFO)</h1>
        <p class="muted">Elige producto y proveedor; el precio de compra se precarga según el proveedor (USD). Si hay on demand pendiente, esta entrada lo cubre primero.</p>
    </div>
    <div class="actions">
        <a class="btn btn-secondary" href="{{ route('inventory.stock.index') }}">Ver entradas</a>
        <a class="btn btn-secondary" href="{{ route('inventory.on-demand.index') }}">On demand</a>
        <a class="btn btn-secondary" href="{{ route('inventory.products.index') }}">Productos</a>
    </div>
</div>

<div id="on-demand-hint" class="flash" style="background:#eff6ff;color:#1e40af;border-color:#bfdbfe;margin-bottom:1rem;display:none"></div>

<div class="card">
    <form method="POST" action="{{ route('inventory.stock.store') }}">
        @csrf
        <div class="grid-2">
            <div class="field">
                <label for="product_id">Producto *</label>
                <select id="product_id" name="product_id" required>
                    <option value="">— Selecciona —</option>
                    @foreach ($products as $product)
                        <option value="{{ $product->id }}" @selected(old('product_id', request('product_id')) == $product->id)>
                            {{ $product->code }} — {{ $product->name }}
                            @if (($pendingOnDemand[(string) $product->id] ?? 0) > 0)
                                · {{ $pendingOnDemand[(string) $product->id] }} on demand
                            @endif
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label for="supplier_id">Proveedor de esta compra *</label>
                <select id="supplier_id" name="supplier_id" required>
                    <option value="">— Selecciona producto primero —</option>
                </select>
            </div>
        </div>
        <div class="grid-3">
            <div class="field">
                <label for="quantity">Cantidad *</label>
                <input id="quantity" type="number" min="1" name="quantity" value="{{ old('quantity') }}" required>
            </div>
            <div class="field">
                <label for="purchase_price">Precio de compra (USD) *</label>
                <input id="purchase_price" type="number" min="0" step="0.01" name="purchase_price" value="{{ old('purchase_price') }}" required>
            </div>
            <div class="field">
                <label for="received_at">Fecha de recepción *</label>
                <input id="received_at" type="date" name="received_at" value="{{ old('received_at', now()->toDateString()) }}" required>
            </div>
        </div>
        <div class="grid-2">
            <div class="field">
                <label for="lot_number">Número de lote (opcional)</label>
                <input id="lot_number" type="text" name="lot_number" value="{{ old('lot_number') }}" placeholder="Se genera automático si lo dejas vacío">
            </div>
            <div class="field">
                <label for="invoice_reference">Factura / referencia</label>
                <input id="invoice_reference" type="text" name="invoice_reference" value="{{ old('invoice_reference') }}">
            </div>
        </div>
        <div class="field">
            <label for="notes">Notas</label>
            <textarea id="notes" name="notes">{{ old('notes') }}</textarea>
        </div>
        <button class="btn" type="submit">Registrar entrada</button>
    </form>
</div>

<script>
(() => {
    const productSuppliers = @json($productMap);
    const pendingOnDemand = @json($pendingOnDemand);
    const productSelect = document.getElementById('product_id');
    const supplierSelect = document.getElementById('supplier_id');
    const purchasePrice = document.getElementById('purchase_price');
    const quantityInput = document.getElementById('quantity');
    const hint = document.getElementById('on-demand-hint');
    const oldSupplier = @json(old('supplier_id'));

    const updateHint = () => {
        const pending = Number(pendingOnDemand[String(productSelect.value)] || 0);
        const qty = Number(quantityInput?.value || 0);
        if (!hint) return;
        if (pending <= 0 || !productSelect.value) {
            hint.style.display = 'none';
            return;
        }
        const cover = qty > 0 ? Math.min(pending, qty) : pending;
        hint.style.display = '';
        hint.textContent = qty > 0
            ? `Esta entrada cubrirá ${cover} de ${pending} unidad(es) on demand pendientes. El resto (${Math.max(0, qty - cover)}) queda en stock.`
            : `Hay ${pending} unidad(es) on demand pendientes. Esta entrada las cubrirá primero (COGS real) y el sobrante queda en stock.`;
    };

    const fillSuppliers = () => {
        const productId = productSelect.value;
        const rows = productSuppliers[productId] || [];

        window.destroySearchableSelect?.(supplierSelect);
        supplierSelect.innerHTML = '';

        if (!productId) {
            supplierSelect.innerHTML = '<option value="">— Selecciona producto primero —</option>';
            window.initSearchableSelects?.(supplierSelect);
            updateHint();
            return;
        }

        if (!rows.length) {
            supplierSelect.innerHTML = '<option value="">Este producto no tiene proveedores</option>';
            window.initSearchableSelects?.(supplierSelect);
            updateHint();
            return;
        }

        supplierSelect.innerHTML = '<option value="">— Selecciona —</option>';
        rows.forEach((row) => {
            const option = document.createElement('option');
            option.value = row.id;
            option.textContent = row.name + (row.is_preferred ? ' (preferido)' : '');
            option.dataset.price = row.purchase_price;
            if (String(oldSupplier) === String(row.id) || (!oldSupplier && row.is_preferred)) {
                option.selected = true;
            }
            supplierSelect.appendChild(option);
        });

        window.initSearchableSelects?.(supplierSelect);
        applyPrice();
        updateHint();
    };

    const applyPrice = () => {
        const selected = supplierSelect.selectedOptions[0];
        if (selected?.dataset?.price && !purchasePrice.dataset.touched) {
            purchasePrice.value = selected.dataset.price;
        }
    };

    purchasePrice.addEventListener('input', () => {
        purchasePrice.dataset.touched = '1';
    });

    productSelect.addEventListener('change', () => {
        purchasePrice.dataset.touched = '';
        fillSuppliers();
    });
    supplierSelect.addEventListener('change', () => {
        purchasePrice.dataset.touched = '';
        applyPrice();
    });
    quantityInput?.addEventListener('input', updateHint);

    fillSuppliers();
})();
</script>
@endsection
