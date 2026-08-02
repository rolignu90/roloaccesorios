<?php

namespace App\Http\Controllers\Costs;

use App\Http\Controllers\Controller;
use App\Http\Requests\Costs\StoreExpenseRequest;
use App\Http\Requests\Costs\UpdateExpenseRequest;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ExpenseController extends Controller
{
    public function index(Request $request): View
    {
        $expenses = Expense::query()
            ->with('category')
            ->when($request->filled('from'), fn ($q) => $q->whereDate('expense_date', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('expense_date', '<=', $request->date('to')))
            ->when($request->filled('category_id'), fn ($q) => $q->where('expense_category_id', $request->integer('category_id')))
            ->latest('expense_date')
            ->paginate(20)
            ->withQueryString();

        $categories = ExpenseCategory::query()->where('is_active', true)->orderBy('name')->get();

        return view('costs.expenses.index', compact('expenses', 'categories'));
    }

    public function create(): View
    {
        $categories = ExpenseCategory::query()->where('is_active', true)->orderBy('name')->get();

        return view('costs.expenses.create', compact('categories'));
    }

    public function store(StoreExpenseRequest $request): RedirectResponse
    {
        Expense::query()->create($request->validated());

        return redirect()
            ->route('costs.expenses.index')
            ->with('success', 'Gasto registrado correctamente.');
    }

    public function edit(Expense $expense): View
    {
        $categories = ExpenseCategory::query()->orderBy('name')->get();

        return view('costs.expenses.edit', compact('expense', 'categories'));
    }

    public function update(UpdateExpenseRequest $request, Expense $expense): RedirectResponse
    {
        $expense->update($request->validated());

        return redirect()
            ->route('costs.expenses.index')
            ->with('success', 'Gasto actualizado correctamente.');
    }

    public function destroy(Expense $expense): RedirectResponse
    {
        $expense->delete();

        return redirect()
            ->route('costs.expenses.index')
            ->with('success', 'Gasto eliminado correctamente.');
    }
}
