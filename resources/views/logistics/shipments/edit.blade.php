@extends('layouts.app')

@section('title', 'Editar '.$shipment->number)

@section('content')
<div class="topbar">
    <div>
        <h1>Editar envío {{ $shipment->number }}</h1>
        <p class="muted">
            {{ $shipment->statusLabel() }}
            @if ($shipment->hasSistrackLabel())
                · Ya está en Sistrack (guía {{ $shipment->sistrack_external_id }})
            @endif
        </p>
    </div>
    <a class="btn btn-secondary" href="{{ route('logistics.shipments.show', $shipment) }}">Volver</a>
</div>

<div class="card">
    <form method="POST" action="{{ route('logistics.shipments.update', $shipment) }}" data-geo-root>
        @csrf
        @method('PUT')

        @include('logistics.shipments._form', ['shipment' => $shipment])

        @if ($shipment->hasSistrackLabel())
            <div class="card" style="margin:0;padding:.85rem 1rem;background:#f9fafb">
                <label style="display:flex;align-items:center;gap:.5rem;font-weight:600">
                    <input type="checkbox" name="update_sistrack" value="1" @checked(old('update_sistrack'))>
                    Actualizar también en Sistrack
                </label>
                <p class="muted" style="margin:.35rem 0 0;font-size:.85rem">
                    Opcional. Si la marcas, se edita la misma orden en Sistrack (guía {{ $shipment->sistrack_external_id }}) con destinatario, dirección, descripción y COD; no se crea guía nueva. Si no la marcas, solo cambia aquí.
                    Si la etiqueta ya estaba impresa, vuelve a imprimirla.
                </p>
            </div>
        @endif

        <div class="actions" style="margin-top:1rem">
            <button class="btn" type="submit">Guardar cambios</button>
        </div>
    </form>
</div>
@endsection
