<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\StoreSupplierPaymentRequest;
use App\Models\Supplier;
use App\Services\SupplierPaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class SupplierPayableController extends Controller
{
    public function __construct(private SupplierPaymentService $payments)
    {
    }

    public function index(): View
    {
        $rows = $this->payments->balancesBySupplier();
        $totalBalance = round((float) $rows->sum('balance'), 2);
        $totalPurchased = round((float) $rows->sum('purchased'), 2);
        $totalPaid = round((float) $rows->sum('paid'), 2);

        return view('inventory.payables.index', compact('rows', 'totalBalance', 'totalPurchased', 'totalPaid'));
    }

    public function show(Supplier $supplier): View
    {
        $lots = $supplier->inventoryLots()
            ->with('product')
            ->where('quantity_received', '>', 0)
            ->latest('received_at')
            ->latest('id')
            ->get();

        $payments = $supplier->payments()
            ->with(['allocations.inventoryLot.product'])
            ->latest('paid_at')
            ->latest('id')
            ->paginate(20);

        $purchased = round((float) $lots->sum(fn ($lot) => $lot->purchaseCost()), 2);
        $paid = round((float) $lots->sum(fn ($lot) => (float) $lot->amount_paid), 2);
        $balance = round($purchased - $paid, 2);

        return view('inventory.payables.show', compact(
            'supplier',
            'lots',
            'payments',
            'purchased',
            'paid',
            'balance'
        ));
    }

    public function create(): View
    {
        $suppliers = Supplier::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'code', 'name']);

        $selectedId = (int) (old('supplier_id', request('supplier_id')));
        $lots = $selectedId > 0
            ? $this->payments->payableLotsForSupplier($selectedId)
            : collect();

        return view('inventory.payables.create', [
            'suppliers' => $suppliers,
            'selectedSupplierId' => $selectedId,
            'lots' => $lots,
            'paymentMethods' => config('sales.payment_methods', []),
        ]);
    }

    public function store(StoreSupplierPaymentRequest $request): RedirectResponse
    {
        $payment = $this->payments->create($request->validated());

        return redirect()
            ->route('inventory.payables.show', $payment->supplier_id)
            ->with('success', 'Pago a proveedor registrado ('.money($payment->amount).').');
    }

    public function payFull(Supplier $supplier): RedirectResponse
    {
        try {
            $payment = $this->payments->payFull(
                (int) $supplier->id,
                paymentMethod: 'transfer',
                notes: 'Pago completo del saldo',
            );
        } catch (\Throwable $e) {
            return back()->withErrors(['payables' => $e->getMessage()]);
        }

        return redirect()
            ->route('inventory.payables.show', $supplier)
            ->with('success', 'Pago completo registrado ('.money($payment->amount).').');
    }
}
