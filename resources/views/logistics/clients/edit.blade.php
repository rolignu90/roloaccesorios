@extends('layouts.app')

@section('title', 'Editar '.$client->name)

@section('content')
<div class="topbar">
    <div>
        <h1>Editar {{ $client->name }}</h1>
        <p class="muted">{{ $client->code }}</p>
    </div>
    <a class="btn btn-secondary" href="{{ route('logistics.clients.show', $client) }}">Volver</a>
</div>

<div class="card">
    <form method="POST" action="{{ route('logistics.clients.update', $client) }}">
        @csrf
        @method('PUT')
        @include('logistics.clients._form', ['client' => $client, 'carriers' => $carriers])
        <div class="actions" style="margin-top:1rem">
            <button class="btn" type="submit">Guardar cambios</button>
        </div>
    </form>
</div>
@endsection
