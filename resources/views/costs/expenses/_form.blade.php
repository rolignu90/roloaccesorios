@php($expense = $expense ?? null)
<div class="grid-2">
    <div class="field">
        <label for="expense_category_id">Categoría *</label>
        <select id="expense_category_id" name="expense_category_id" required>
            <option value="">— Selecciona —</option>
            @foreach ($categories as $category)
                <option value="{{ $category->id }}" @selected(old('expense_category_id', $expense?->expense_category_id) == $category->id)>
                    {{ $category->name }}
                </option>
            @endforeach
        </select>
    </div>
    <div class="field">
        <label for="expense_date">Fecha *</label>
        <input id="expense_date" type="date" name="expense_date" value="{{ old('expense_date', optional($expense?->expense_date)->toDateString() ?? now()->toDateString()) }}" required>
    </div>
</div>
<div class="field">
    <label for="description">Descripción *</label>
    <input id="description" type="text" name="description" value="{{ old('description', $expense?->description) }}" required>
</div>
<div class="grid-2">
    <div class="field">
        <label for="amount">Monto (USD) *</label>
        <input id="amount" type="number" min="0.01" step="0.01" name="amount" value="{{ old('amount', $expense?->amount) }}" required>
    </div>
    <div class="field">
        <label for="reference">Referencia</label>
        <input id="reference" type="text" name="reference" value="{{ old('reference', $expense?->reference) }}">
    </div>
</div>
<div class="field">
    <label for="notes">Notas</label>
    <textarea id="notes" name="notes">{{ old('notes', $expense?->notes) }}</textarea>
</div>
