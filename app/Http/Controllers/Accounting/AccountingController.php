<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Seller;
use App\Services\AccountingOverviewService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AccountingController extends Controller
{
    public function __construct(private AccountingOverviewService $accounting) {}

    public function index(Request $request): View
    {
        [$from, $to] = $this->period($request);

        $balances = $this->accounting->balances($from, $to);
        $byCustomer = $this->accounting->balancesByCustomer($from, $to, 25);
        $bySeller = $this->accounting->balancesBySeller($from, $to, 25);

        return view('accounting.dashboard', compact('balances', 'byCustomer', 'bySeller', 'from', 'to'));
    }

    public function statements(Request $request): View
    {
        [$from, $to] = $this->period($request);

        $customers = Customer::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'code', 'name', 'phone']);

        $sellers = Seller::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'code', 'name']);

        return view('accounting.statements.index', compact('customers', 'sellers', 'from', 'to'));
    }

    public function customerStatement(Request $request, Customer $customer): View
    {
        [$from, $to] = $this->period($request);
        $statement = $this->accounting->customerStatement($customer, $from, $to);

        return view('accounting.statements.show', [
            'statement' => $statement,
            'from' => $from,
            'to' => $to,
        ]);
    }

    public function sellerStatement(Request $request, Seller $seller): View
    {
        [$from, $to] = $this->period($request);
        $statement = $this->accounting->sellerStatement($seller, $from, $to);

        return view('accounting.statements.show', [
            'statement' => $statement,
            'from' => $from,
            'to' => $to,
        ]);
    }

    public function redirectStatement(Request $request): RedirectResponse
    {
        $type = $request->string('type')->toString();
        $id = (int) $request->input('id');
        $query = array_filter([
            'from' => $request->input('from'),
            'to' => $request->input('to'),
        ]);

        if ($type === 'seller' && $id > 0) {
            return redirect()->route('accounting.statements.seller', ['seller' => $id] + $query);
        }

        if ($type === 'customer' && $id > 0) {
            return redirect()->route('accounting.statements.customer', ['customer' => $id] + $query);
        }

        return redirect()
            ->route('accounting.statements')
            ->with('error', 'Selecciona un cliente o vendedor.');
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function period(Request $request): array
    {
        $from = $request->filled('from')
            ? Carbon::parse($request->string('from'))->startOfDay()
            : now()->startOfMonth();
        $to = $request->filled('to')
            ? Carbon::parse($request->string('to'))->endOfDay()
            : now()->endOfDay();

        return [$from, $to];
    }
}
