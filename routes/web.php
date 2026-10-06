<?php

use App\Http\Controllers\Accounting\AccountingController;
use App\Http\Controllers\Auth\AccountController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Consignments\ConsignmentController;
use App\Http\Controllers\Costs\CostDashboardController;
use App\Http\Controllers\Costs\ExpenseController;
use App\Http\Controllers\Inventory\ComboController;
use App\Http\Controllers\Inventory\FreeShippingController;
use App\Http\Controllers\Inventory\InventoryDashboardController;
use App\Http\Controllers\Inventory\InventoryMovementController;
use App\Http\Controllers\Inventory\OnDemandSaleController;
use App\Http\Controllers\Inventory\ProductController;
use App\Http\Controllers\Inventory\ProductLabelController;
use App\Http\Controllers\Inventory\ProductSalesController;
use App\Http\Controllers\Inventory\StockReceiptController;
use App\Http\Controllers\Inventory\SupplierController;
use App\Http\Controllers\Inventory\SupplierPayableController;
use App\Http\Controllers\Logistics\LogisticsClientController;
use App\Http\Controllers\Logistics\LogisticsSettlementController;
use App\Http\Controllers\Logistics\LogisticsShipmentController;
use App\Http\Controllers\Sales\CustomerController;
use App\Http\Controllers\Sales\SaleController;
use App\Http\Controllers\Sales\SellerController;
use App\Http\Controllers\Sales\SellerSettlementController;
use App\Http\Controllers\Sales\ShippingCarrierController;
use App\Http\Controllers\Settings\RoleController;
use App\Http\Controllers\Settings\StoreController;
use App\Http\Controllers\Settings\UserController;
use App\Http\Controllers\Store\CashSessionController;
use App\Http\Controllers\Store\PosController;
use App\Support\Navigation;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'showLogin'])->name('login');
    Route::post('/login', [LoginController::class, 'login'])->name('login.store');
});
Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

Route::middleware(['auth', 'account'])->group(function () {
    Route::get('/', fn () => redirect(Navigation::homeUrl(auth()->user())))->name('home');

    Route::get('mi-cuenta', [AccountController::class, 'edit'])->name('account.edit');
    Route::put('mi-cuenta/password', [AccountController::class, 'updatePassword'])->name('account.password');
    Route::put('mi-cuenta/pin', [AccountController::class, 'updatePin'])->name('account.pin');

    Route::prefix('inventory')->name('inventory.')->group(function () {
        Route::middleware('permission:inventory.view')->group(function () {
            Route::get('/', [InventoryDashboardController::class, 'index'])->name('dashboard');
            Route::get('products/export', [ProductController::class, 'export'])->name('products.export');
            Route::get('products/labels', [ProductLabelController::class, 'index'])->name('products.labels');
            Route::get('on-demand', [OnDemandSaleController::class, 'index'])->name('on-demand.index');
            Route::get('movements', [InventoryMovementController::class, 'index'])->name('movements.index');
            Route::get('sold', [ProductSalesController::class, 'index'])->name('sold.index');
            Route::get('sold/export', [ProductSalesController::class, 'export'])->name('sold.export');
        });

        Route::middleware('permission:inventory.manage')->group(function () {
            Route::get('products/bulk-prices', [ProductController::class, 'bulkPrices'])->name('products.bulk-prices');
            Route::put('products/bulk-prices', [ProductController::class, 'updateBulkPrices'])->name('products.bulk-prices.update');
            Route::resource('products', ProductController::class)->except(['index', 'show']);
            Route::get('products/{product}/duplicate', [ProductController::class, 'duplicate'])->name('products.duplicate');
            Route::resource('combos', ComboController::class)->except(['index', 'show']);
            Route::post('combos/{combo}/duplicate', [ComboController::class, 'duplicate'])->name('combos.duplicate');
            Route::get('free-shipping', [FreeShippingController::class, 'index'])->name('free-shipping.index');
            Route::post('free-shipping', [FreeShippingController::class, 'store'])->name('free-shipping.store');
            Route::delete('free-shipping/{product}', [FreeShippingController::class, 'destroy'])->name('free-shipping.destroy');
        });

        Route::middleware('permission:inventory.view')->group(function () {
            Route::resource('products', ProductController::class)->only(['index', 'show']);
            Route::resource('combos', ComboController::class)->only(['index', 'show']);
        });

        Route::middleware('permission:suppliers.manage')->group(function () {
            Route::resource('suppliers', SupplierController::class);
            Route::get('payables', [SupplierPayableController::class, 'index'])->name('payables.index');
            Route::get('payables/create', [SupplierPayableController::class, 'create'])->name('payables.create');
            Route::post('payables', [SupplierPayableController::class, 'store'])->name('payables.store');
            Route::post('payables/{supplier}/pay-full', [SupplierPayableController::class, 'payFull'])->name('payables.pay-full');
            Route::get('payables/{supplier}', [SupplierPayableController::class, 'show'])->name('payables.show');
        });

        Route::middleware('permission:stock.manage')->group(function () {
            Route::get('stock', [StockReceiptController::class, 'index'])->name('stock.index');
            Route::get('stock/receive', [StockReceiptController::class, 'create'])->name('stock.create');
            Route::post('stock/receive', [StockReceiptController::class, 'store'])->name('stock.store');
            Route::get('stock/lots/{lot}/edit', [StockReceiptController::class, 'edit'])->name('stock.edit');
            Route::put('stock/lots/{lot}', [StockReceiptController::class, 'update'])->name('stock.update');
            Route::delete('stock/lots/{lot}', [StockReceiptController::class, 'destroy'])->name('stock.destroy');
        });
    });

    Route::prefix('sales')->name('sales.')->group(function () {
        Route::middleware('permission:customers.manage')->group(function () {
            Route::resource('customers', CustomerController::class);
            Route::post('customers/{customer}/prices', [CustomerController::class, 'syncProductPrices'])->name('customers.prices.sync');
            Route::delete('customers/{customer}/prices/{product}', [CustomerController::class, 'destroyProductPrices'])->name('customers.prices.destroy');
        });

        Route::middleware('permission:sellers.manage')->group(function () {
            Route::resource('sellers', SellerController::class);
            Route::get('seller-settlements', [SellerSettlementController::class, 'index'])->name('seller-settlements.index');
            Route::get('seller-settlements/create', [SellerSettlementController::class, 'create'])->name('seller-settlements.create');
            Route::post('seller-settlements', [SellerSettlementController::class, 'store'])->name('seller-settlements.store');
            Route::get('seller-settlements/{sellerSettlement}', [SellerSettlementController::class, 'show'])->name('seller-settlements.show');
            Route::post('seller-settlements/{sellerSettlement}/void', [SellerSettlementController::class, 'void'])->name('seller-settlements.void');
        });

        Route::resource('shipping-carriers', ShippingCarrierController::class)->except(['show'])->middleware('permission:settings.carriers');

        Route::middleware('permission:sales.create')->group(function () {
            Route::get('sales/create', [SaleController::class, 'create'])->name('sales.create');
            Route::post('sales', [SaleController::class, 'store'])->name('sales.store');
        });

        Route::middleware('permission:sales.export')->group(function () {
            Route::get('sales/export-labels', [SaleController::class, 'exportLabels'])->name('sales.export-labels');
            Route::post('sales/export-labels', [SaleController::class, 'exportLabels'])->name('sales.export-labels.selected')->middleware('sale.visible');
            Route::get('sales/export-report', [SaleController::class, 'exportReport'])->name('sales.export-report');
        });

        Route::middleware(['permission:sales.edit', 'sale.visible'])->group(function () {
            Route::post('sales/send-sistrack', [SaleController::class, 'sendManyToSistrack'])->name('sales.send-sistrack');
            Route::post('sales/sync-sistrack-status', [SaleController::class, 'syncManySistrackStatus'])->name('sales.sync-sistrack-status');
            Route::get('sales/sync-sistrack-status/pending', [SaleController::class, 'pendingSistrackStatusSync'])->name('sales.sync-sistrack-status.pending');
            Route::post('sales/mark-delivered', [SaleController::class, 'markManyDelivered'])->name('sales.mark-delivered');
            Route::post('sales/mark-returned', [SaleController::class, 'markManyReturned'])->name('sales.mark-returned');
            Route::post('sales/{sale}/send-sistrack', [SaleController::class, 'sendToSistrack'])->name('sales.send-sistrack.one');
            Route::post('sales/{sale}/resend-sistrack', [SaleController::class, 'resendToSistrack'])->name('sales.resend-sistrack.one');
            Route::post('sales/{sale}/sync-sistrack-status', [SaleController::class, 'syncSistrackStatus'])->name('sales.sync-sistrack-status.one');
            Route::post('sales/{sale}/update-sistrack', [SaleController::class, 'updateInSistrack'])->name('sales.update-sistrack.one');
            Route::post('sales/{sale}/mark-delivered', [SaleController::class, 'markDelivered'])->name('sales.mark-delivered.one');
            Route::post('sales/{sale}/mark-returned', [SaleController::class, 'markReturned'])->name('sales.mark-returned.one');
            Route::patch('sales/{sale}/customer', [SaleController::class, 'updateCustomer'])->name('sales.customer.update');
            Route::patch('sales/{sale}/seller', [SaleController::class, 'updateSeller'])->name('sales.seller.update');
            Route::patch('sales/{sale}/shipping', [SaleController::class, 'updateShipping'])->name('sales.shipping.update');
            Route::patch('sales/{sale}/discounts', [SaleController::class, 'updateDiscounts'])->name('sales.discounts.update');
            Route::post('sales/{sale}/items', [SaleController::class, 'addItem'])->name('sales.items.store');
            Route::patch('sales/{sale}/items/{item}', [SaleController::class, 'updateItem'])->name('sales.items.update');
            Route::delete('sales/{sale}/items/{item}', [SaleController::class, 'destroyItem'])->name('sales.items.destroy');
        });

        Route::middleware(['permission:sales.void', 'sale.visible'])->group(function () {
            Route::post('sales/void-many', [SaleController::class, 'voidMany'])->name('sales.void-many');
            Route::post('sales/{sale}/void', [SaleController::class, 'void'])->name('sales.void');
        });

        Route::middleware(['permission:sales.create,sales.view_all', 'sale.visible'])->group(function () {
            Route::get('sales', [SaleController::class, 'index'])->name('sales.index');
            Route::get('sales/{sale}', [SaleController::class, 'show'])->name('sales.show');
        });
    });

    Route::prefix('consignments')->name('consignments.')->middleware('permission:consignments.manage')->group(function () {
        Route::get('/', [ConsignmentController::class, 'dashboard'])->name('dashboard');
        Route::get('list', [ConsignmentController::class, 'index'])->name('index');
        Route::get('monthly-products', [ConsignmentController::class, 'monthlyProducts'])->name('monthly-products');
        Route::get('create', [ConsignmentController::class, 'create'])->name('create');
        Route::post('/', [ConsignmentController::class, 'store'])->name('store');
        Route::get('settle', [ConsignmentController::class, 'settleCreate'])->name('settle');
        Route::post('settle', [ConsignmentController::class, 'settleStore'])->name('settle.store');
        Route::get('{consignment}', [ConsignmentController::class, 'show'])->name('show');
        Route::post('{consignment}/payments', [ConsignmentController::class, 'storePayment'])->name('payments.store');
        Route::post('{consignment}/returns', [ConsignmentController::class, 'storeReturn'])->name('returns.store');
        Route::post('{consignment}/void', [ConsignmentController::class, 'void'])->name('void');
    });

    Route::prefix('costs')->name('costs.')->group(function () {
        Route::middleware('permission:accounting.view')->group(function () {
            Route::get('/', [CostDashboardController::class, 'index'])->name('dashboard');
            Route::get('margins', [CostDashboardController::class, 'margins'])->name('margins');
            Route::get('sellers', [CostDashboardController::class, 'bySeller'])->name('sellers');
            Route::get('periods', [CostDashboardController::class, 'byPeriod'])->name('periods');
        });
        Route::resource('expenses', ExpenseController::class)->except(['show'])->middleware('permission:expenses.manage');
    });

    Route::prefix('contabilidad')->name('accounting.')->middleware('permission:accounting.view')->group(function () {
        Route::get('/', [AccountingController::class, 'index'])->name('dashboard');
        Route::get('estados', [AccountingController::class, 'statements'])->name('statements');
        Route::get('estados/ver', [AccountingController::class, 'redirectStatement'])->name('statements.redirect');
        Route::get('estados/cliente/{customer}', [AccountingController::class, 'customerStatement'])->name('statements.customer');
        Route::get('estados/vendedor/{seller}', [AccountingController::class, 'sellerStatement'])->name('statements.seller');
    });

    Route::prefix('tienda')->name('store.')->group(function () {
        Route::middleware('permission:pos.sell')->group(function () {
            Route::get('/', [PosController::class, 'index'])->name('pos');
            Route::post('cobrar', [PosController::class, 'checkout'])->name('pos.checkout');
            Route::get('abrir-caja', [PosController::class, 'drawer'])->name('drawer');
            Route::post('cambiar-usuario', [PosController::class, 'switchUser'])->name('switch')->middleware('throttle:10,1');
        });

        Route::middleware('permission:pos.sell,cash.manage,cash.view_all')->group(function () {
            Route::get('seleccionar', [PosController::class, 'selectStore'])->name('select');
            Route::post('seleccionar', [PosController::class, 'rememberStore'])->name('select.store');
        });

        Route::get('ventas/{sale}/ticket', [PosController::class, 'ticket'])->name('sales.ticket')
            ->middleware(['permission:pos.sell,cash.manage,cash.view_all,sales.view_all', 'sale.visible']);

        Route::middleware('permission:cash.manage,cash.view_all')->group(function () {
            Route::get('caja', [CashSessionController::class, 'index'])->name('cash.index');
            Route::post('caja', [CashSessionController::class, 'open'])->name('cash.open');
            Route::get('caja/{cashSession}', [CashSessionController::class, 'show'])->name('cash.show');
            Route::get('caja/{cashSession}/corte', [CashSessionController::class, 'report'])->name('cash.report');
            Route::post('caja/{cashSession}/movimientos', [CashSessionController::class, 'storeMovement'])->name('cash.movements.store');
            Route::patch('caja/{cashSession}/cajero', [CashSessionController::class, 'changeCashier'])->name('cash.cashier');
            Route::post('caja/{cashSession}/cerrar', [CashSessionController::class, 'close'])->name('cash.close');
        });
    });

    Route::prefix('configuracion')->name('settings.')->group(function () {
        Route::resource('tiendas', StoreController::class)
            ->parameters(['tiendas' => 'store'])
            ->names('stores')
            ->except(['show', 'destroy'])
            ->middleware('permission:settings.stores');

        Route::middleware('permission:settings.users')->group(function () {
            Route::resource('usuarios', UserController::class)
                ->parameters(['usuarios' => 'user'])
                ->names('users')
                ->except(['show', 'destroy']);
            Route::resource('roles', RoleController::class)->except(['show']);
        });
    });

    Route::prefix('logistics')->name('logistics.')->group(function () {
        Route::middleware('permission:logistics.clients')->group(function () {
            Route::get('clients/create', [LogisticsClientController::class, 'create'])->name('clients.create');
            Route::post('clients', [LogisticsClientController::class, 'store'])->name('clients.store');
            Route::get('clients/{client}/edit', [LogisticsClientController::class, 'edit'])->name('clients.edit');
            Route::put('clients/{client}', [LogisticsClientController::class, 'update'])->name('clients.update');
        });

        Route::middleware('permission:logistics.create')->group(function () {
            Route::get('shipments/create', [LogisticsShipmentController::class, 'create'])->name('shipments.create');
            Route::post('shipments', [LogisticsShipmentController::class, 'store'])->name('shipments.store');
            Route::post('shipments/send-sistrack', [LogisticsShipmentController::class, 'sendManyToSistrack'])->name('shipments.send-sistrack');
            Route::post('shipments/sync-sistrack-status', [LogisticsShipmentController::class, 'syncManySistrackStatus'])->name('shipments.sync-sistrack-status');
            Route::get('shipments/{shipment}/edit', [LogisticsShipmentController::class, 'edit'])->name('shipments.edit');
            Route::put('shipments/{shipment}', [LogisticsShipmentController::class, 'update'])->name('shipments.update');
            Route::post('shipments/{shipment}/send-sistrack', [LogisticsShipmentController::class, 'sendToSistrack'])->name('shipments.send-sistrack.one');
            Route::post('shipments/{shipment}/resend-sistrack', [LogisticsShipmentController::class, 'resendToSistrack'])->name('shipments.resend-sistrack.one');
            Route::post('shipments/{shipment}/sync-sistrack-status', [LogisticsShipmentController::class, 'syncSistrackStatus'])->name('shipments.sync-sistrack-status.one');
            Route::post('shipments/{shipment}/mark-delivered', [LogisticsShipmentController::class, 'markDelivered'])->name('shipments.mark-delivered.one');
        });

        Route::middleware('permission:logistics.void')->group(function () {
            Route::post('shipments/{shipment}/mark-returned', [LogisticsShipmentController::class, 'markReturned'])->name('shipments.mark-returned.one');
            Route::post('shipments/{shipment}/void', [LogisticsShipmentController::class, 'void'])->name('shipments.void');
        });

        Route::middleware('permission:logistics.view')->group(function () {
            Route::get('clients', [LogisticsClientController::class, 'index'])->name('clients.index');
            Route::get('clients/{client}', [LogisticsClientController::class, 'show'])->name('clients.show');
            Route::get('shipments', [LogisticsShipmentController::class, 'index'])->name('shipments.index');
            Route::get('shipments/sistrack-labels', [LogisticsShipmentController::class, 'sistrackLabels'])->name('shipments.sistrack-labels');
            Route::get('shipments/{shipment}', [LogisticsShipmentController::class, 'show'])->name('shipments.show');
            Route::get('shipments/{shipment}/sistrack-label', [LogisticsShipmentController::class, 'sistrackLabel'])->name('shipments.sistrack-label');
        });

        Route::middleware('permission:logistics.settlements')->group(function () {
            Route::get('settlements', [LogisticsSettlementController::class, 'index'])->name('settlements.index');
            Route::get('settlements/create', [LogisticsSettlementController::class, 'create'])->name('settlements.create');
            Route::post('settlements', [LogisticsSettlementController::class, 'store'])->name('settlements.store');
            Route::get('settlements/{settlement}', [LogisticsSettlementController::class, 'show'])->name('settlements.show');
            Route::post('settlements/{settlement}/void', [LogisticsSettlementController::class, 'void'])->name('settlements.void');
        });
    });
});
