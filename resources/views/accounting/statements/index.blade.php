@extends('layouts.app')

@section('title', 'Estados de cuenta')

@section('content')
<div class="topbar">
    <div>
        <h1>Estados de cuenta</h1>
        <p class="muted">Elige cliente o vendedor y el período. Se listan las ventas (sin anuladas) con saldo abierto vs entregado.</p>
    </div>
    <div class="actions">
        <a class="btn btn-secondary" href="{{ route('accounting.dashboard', ['from' => $from->toDateString(), 'to' => $to->toDateString()]) }}">Resumen</a>
        <a class="btn btn-secondary" href="{{ route('costs.dashboard', ['from' => $from->toDateString(), 'to' => $to->toDateString()]) }}">Resultado</a>
    </div>
</div>

<div class="card">
    @include('accounting._periods')
    <form method="GET" action="{{ route('accounting.statements.redirect') }}" class="grid-2" style="gap:1rem;align-items:end">
        <div class="field">
            <label for="type">Tipo</label>
            <select id="type" name="type" required>
                <option value="customer">Cliente</option>
                <option value="seller">Vendedor</option>
            </select>
        </div>
        <div class="field" id="customer-field">
            <label for="customer_id">Cliente</label>
            <select id="customer_id" name="id" data-placeholder="Buscar cliente…">
                <option value="">— Selecciona —</option>
                @foreach ($customers as $customer)
                    <option value="{{ $customer->id }}">{{ $customer->code }} — {{ $customer->name }}@if($customer->phone) · {{ $customer->phone }}@endif</option>
                @endforeach
            </select>
        </div>
        <div class="field" id="seller-field" style="display:none">
            <label for="seller_id">Vendedor</label>
            <select id="seller_id" data-placeholder="Buscar vendedor…">
                <option value="">— Selecciona —</option>
                @foreach ($sellers as $seller)
                    <option value="{{ $seller->id }}">{{ $seller->code }} — {{ $seller->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="field">
            <label for="from">Desde</label>
            <input id="from" type="date" name="from" value="{{ $from->toDateString() }}">
        </div>
        <div class="field">
            <label for="to">Hasta</label>
            <input id="to" type="date" name="to" value="{{ $to->toDateString() }}">
        </div>
        <div class="field">
            <button class="btn" type="submit">Ver estado de cuenta</button>
        </div>
    </form>
</div>

<script>
(() => {
    const type = document.getElementById('type');
    const customerField = document.getElementById('customer-field');
    const sellerField = document.getElementById('seller-field');
    const customerSelect = document.getElementById('customer_id');
    const sellerSelect = document.getElementById('seller_id');

    const sync = () => {
        const isSeller = type.value === 'seller';
        customerField.style.display = isSeller ? 'none' : '';
        sellerField.style.display = isSeller ? '' : 'none';
        if (isSeller) {
            customerSelect.removeAttribute('name');
            customerSelect.removeAttribute('required');
            sellerSelect.setAttribute('name', 'id');
            sellerSelect.setAttribute('required', 'required');
        } else {
            sellerSelect.removeAttribute('name');
            sellerSelect.removeAttribute('required');
            customerSelect.setAttribute('name', 'id');
            customerSelect.setAttribute('required', 'required');
        }
    };

    type?.addEventListener('change', sync);
    sync();
})();
</script>
@endsection
