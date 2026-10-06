@extends('layouts.app')

@section('title', 'Nuevo envío tercero')

@section('content')
<div class="topbar">
    <div>
        <h1>Nuevo envío a terceros</h1>
        <p class="muted">Número tentativo: {{ $nextNumber }} · sin descontar inventario</p>
    </div>
    <a class="btn btn-secondary" href="{{ route('logistics.shipments.index') }}">Volver</a>
</div>

<div class="card">
    <form method="POST" action="{{ route('logistics.shipments.store') }}" data-geo-root>
        @csrf

        @include('logistics.shipments._form')

        <div class="field">
            <label style="display:flex;align-items:center;gap:.5rem;font-weight:500">
                <input type="checkbox" name="send_to_sistrack" value="1" @checked(old('send_to_sistrack', true))>
                Enviar a Sistrack al guardar
            </label>
        </div>

        <div class="actions" style="margin-top:1rem">
            <button class="btn" type="submit">Crear envío</button>
        </div>
    </form>
</div>
@endsection
