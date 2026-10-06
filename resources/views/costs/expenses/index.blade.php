@extends('layouts.app')

@section('title', 'Gastos')

@section('content')
<div class="topbar">
    <div>
        <h1>Contabilidad · Gastos</h1>
        <p class="muted">Costos operativos fuera del COGS de inventario</p>
    </div>
    <div class="actions">
        <a class="btn btn-secondary" href="{{ route('accounting.dashboard') }}">Resumen</a>
        <a class="btn btn-secondary" href="{{ route('costs.dashboard') }}">Resultado</a>
        <a class="btn" href="{{ route('costs.expenses.create') }}">Nuevo gasto</a>
    </div>
</div>

<div class="card" style="margin-bottom:1rem">
    @include('accounting._periods', [
        'from' => request()->filled('from') ? \Carbon\Carbon::parse(request('from')) : null,
        'to' => request()->filled('to') ? \Carbon\Carbon::parse(request('to')) : null,
    ])
    <form class="search" method="GET" action="{{ route('costs.expenses.index') }}">
        <input type="date" name="from" value="{{ request('from') }}">
        <input type="date" name="to" value="{{ request('to') }}">
        <select name="category_id">
            <option value="">Todas las categorías</option>
            @foreach ($categories as $category)
                <option value="{{ $category->id }}" @selected(request('category_id') == $category->id)>{{ $category->name }}</option>
            @endforeach
        </select>
        <button class="btn" type="submit">Filtrar</button>
    </form>
</div>

<div class="card">
    <table>
        <thead>
            <tr>
                <th>Fecha</th>
                <th>Categoría</th>
                <th>Descripción</th>
                <th>Referencia</th>
                <th>Monto</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @forelse ($expenses as $expense)
                <tr>
                    <td>{{ $expense->expense_date->format('d/m/Y') }}</td>
                    <td>{{ $expense->category?->name }}</td>
                    <td>{{ $expense->description }}</td>
                    <td>{{ $expense->reference ?: '—' }}</td>
                    <td>{{ money($expense->amount) }}</td>
                    <td><a href="{{ route('costs.expenses.edit', $expense) }}">Editar</a></td>
                </tr>
            @empty
                <tr><td colspan="6" class="muted">Sin gastos registrados.</td></tr>
            @endforelse
        </tbody>
    </table>
    <div style="margin-top:1rem">{{ $expenses->links() }}</div>
</div>
@endsection
