@extends('layouts.app')

@section('title', 'Nueva empresa logística')

@section('content')
<div class="topbar">
    <div>
        <h1>Nueva empresa logística</h1>
        <p class="muted">Define su comisión; el flete y 2.5% Sistrack salen del courier</p>
    </div>
    <a class="btn btn-secondary" href="{{ route('logistics.clients.index') }}">Volver</a>
</div>

<div class="card">
    <form method="POST" action="{{ route('logistics.clients.store') }}">
        @csrf
        @include('logistics.clients._form', ['client' => null, 'nextCode' => $nextCode, 'carriers' => $carriers])
        <div class="actions" style="margin-top:1rem">
            <button class="btn" type="submit">Guardar</button>
        </div>
    </form>
</div>
@endsection
