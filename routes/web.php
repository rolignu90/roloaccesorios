<?php

use App\Http\Controllers\Consignments\ConsignmentController;
use App\Http\Controllers\Costs\CostDashboardController;
use App\Http\Controllers\Costs\ExpenseController;
use App\Http\Controllers\Inventory\FreeShippingController;
use App\Http\Controllers\Inventory\InventoryDashboardController;
use App\Http\Controllers\Inventory\InventoryMovementController;
use App\Http\Controllers\Inventory\ProductController;
use App\Http\Controllers\Inventory\StockReceiptController;
use App\Http\Controllers\Inventory\SupplierController;
use App\Http\Controllers\PinAuthController;
use App\Http\Controllers\Sales\CustomerController;
use App\Http\Controllers\Sales\SaleController;
use App\Http\Controllers\Sales\SellerController;
use App\Http\Controllers\Sales\ShippingCarrierController;
use Illuminate\Support\Facades\Route;

Route::get('/login', [PinAuthController::class, 'showLogin'])->name('login');
Route::post('/login', [PinAuthController::class, 'login'])->name('login.store');
Route::post('/logout', [PinAuthController::class, 'logout'])->name('logout');

Route::redirect('/', '/inventory');

Route::middleware('pin.auth')->group(function () {
    Route::prefix('inventory')->name('inventory.')->group(function () {
        Route::get('/', [InventoryDashboardController::class, 'index'])->name('dashboard');

        Route::resource('suppliers', SupplierController::class);
        Route::resource('products', ProductController::class);
        Route::get('products/{product}/duplicate', [ProductController::class, 'duplicate'])->name('products.duplicate');

        Route::get('free-shipping', [FreeShippingController::class, 'index'])->name('free-shipping.index');
        Route::post('free-shipping', [FreeShippingController::class, 'store'])->name('free-shipping.store');
        Route::delete('free-shipping/{product}', [FreeShippingController::class, 'destroy'])->name('free-shipping.destroy');

        Route::get('movements', [InventoryMovementController::class, 'index'])->name('movements.index');

        Route::get('stock', [StockReceiptController::class, 'index'])->name('stock.index');
        Route::get('stock/receive', [StockReceiptController::class, 'create'])->name('stock.create');
        Route::post('stock/receive', [StockReceiptController::class, 'store'])->name('stock.store');
        Route::get('stock/lots/{lot}/edit', [StockReceiptController::class, 'edit'])->name('stock.edit');
        Route::put('stock/lots/{lot}', [StockReceiptController::class, 'update'])->name('stock.update');
        Route::delete('stock/lots/{lot}', [StockReceiptController::class, 'destroy'])->name('stock.destroy');
    });

    Route::prefix('sales')->name('sales.')->group(function () {
        Route::resource('customers', CustomerController::class);
        Route::post('customers/{customer}/prices', [CustomerController::class, 'syncProductPrices'])->name('customers.prices.sync');
        Route::delete('customers/{customer}/prices/{product}', [CustomerController::class, 'destroyProductPrices'])->name('customers.prices.destroy');
        Route::resource('sellers', SellerController::class);
        Route::resource('shipping-carriers', ShippingCarrierController::class)->except(['show']);
        Route::get('sales', [SaleController::class, 'index'])->name('sales.index');
        Route::get('sales/create', [SaleController::class, 'create'])->name('sales.create');
        Route::get('sales/export-labels', [SaleController::class, 'exportLabels'])->name('sales.export-labels');
        Route::post('sales/export-labels', [SaleController::class, 'exportLabels'])->name('sales.export-labels.selected');
        Route::post('sales', [SaleController::class, 'store'])->name('sales.store');
        Route::get('sales/{sale}', [SaleController::class, 'show'])->name('sales.show');
        Route::patch('sales/{sale}/customer', [SaleController::class, 'updateCustomer'])->name('sales.customer.update');
        Route::patch('sales/{sale}/shipping', [SaleController::class, 'updateShipping'])->name('sales.shipping.update');
        Route::post('sales/{sale}/items', [SaleController::class, 'addItem'])->name('sales.items.store');
        Route::patch('sales/{sale}/items/{item}', [SaleController::class, 'updateItem'])->name('sales.items.update');
        Route::delete('sales/{sale}/items/{item}', [SaleController::class, 'destroyItem'])->name('sales.items.destroy');
        Route::post('sales/{sale}/void', [SaleController::class, 'void'])->name('sales.void');
    });

    Route::prefix('consignments')->name('consignments.')->group(function () {
        Route::get('/', [ConsignmentController::class, 'dashboard'])->name('dashboard');
        Route::get('list', [ConsignmentController::class, 'index'])->name('index');
        Route::get('create', [ConsignmentController::class, 'create'])->name('create');
        Route::post('/', [ConsignmentController::class, 'store'])->name('store');
        Route::get('{consignment}', [ConsignmentController::class, 'show'])->name('show');
        Route::post('{consignment}/payments', [ConsignmentController::class, 'storePayment'])->name('payments.store');
        Route::post('{consignment}/returns', [ConsignmentController::class, 'storeReturn'])->name('returns.store');
        Route::post('{consignment}/void', [ConsignmentController::class, 'void'])->name('void');
    });

    Route::prefix('costs')->name('costs.')->group(function () {
        Route::get('/', [CostDashboardController::class, 'index'])->name('dashboard');
        Route::get('margins', [CostDashboardController::class, 'margins'])->name('margins');
        Route::resource('expenses', ExpenseController::class)->except(['show']);
    });
});
