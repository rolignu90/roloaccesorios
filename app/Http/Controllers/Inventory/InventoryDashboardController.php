<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Services\InventoryDashboardService;
use Illuminate\View\View;

class InventoryDashboardController extends Controller
{
    public function __construct(private InventoryDashboardService $dashboard)
    {
    }

    public function index(): View
    {
        $data = $this->dashboard->snapshot();

        return view('inventory.dashboard.index', $data);
    }
}
