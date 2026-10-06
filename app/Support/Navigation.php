<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Http\Request;

/**
 * Sidebar sections → items → tabs. An item is active when any of its tabs is;
 * items with more than one visible tab render a tab bar at the top of the page.
 * Tabs are hidden when the user lacks all of the tab's permissions.
 */
class Navigation
{
    /**
     * @return array<int, array{label: string, items: array<int, array{label: string, tabs: array<int, array{label: string, route: string, active: array<int, string>, permissions: array<int, string>}>}>}>
     */
    public static function sections(): array
    {
        return [
            ['label' => '', 'items' => [
                self::item('Inicio', [self::tab('Dashboard', 'inventory.dashboard', [], ['inventory.view'])]),
            ]],
            ['label' => 'Tienda', 'items' => [
                self::item('Punto de venta', [self::tab('Punto de venta', 'store.pos', ['store.pos*', 'store.select*', 'store.sales.ticket', 'store.switch*'], ['pos.sell'])]),
                self::item('Caja', [self::tab('Caja', 'store.cash.index', ['store.cash.*'], ['cash.manage', 'cash.view_all'])]),
            ]],
            ['label' => 'Ventas', 'items' => [
                self::item('Ventas', [self::tab('Ventas', 'sales.sales.index', ['sales.sales.*'], ['sales.create', 'sales.view_all'])]),
                self::item('Clientes', [self::tab('Clientes', 'sales.customers.index', ['sales.customers.*'], ['customers.manage'])]),
                self::item('Vendedores', [
                    self::tab('Vendedores', 'sales.sellers.index', ['sales.sellers.*'], ['sellers.manage']),
                    self::tab('Liquidaciones', 'sales.seller-settlements.index', ['sales.seller-settlements.*'], ['sellers.manage']),
                ]),
            ]],
            ['label' => 'Inventario', 'items' => [
                self::item('Productos', [
                    self::tab('Productos', 'inventory.products.index', [
                        'inventory.products.index', 'inventory.products.show', 'inventory.products.create',
                        'inventory.products.edit', 'inventory.products.duplicate', 'inventory.products.export',
                    ], ['inventory.view']),
                    self::tab('Combos', 'inventory.combos.index', ['inventory.combos.*'], ['inventory.view']),
                    self::tab('Precios masivos', 'inventory.products.bulk-prices', ['inventory.products.bulk-prices*'], ['inventory.manage']),
                    self::tab('Envío gratis', 'inventory.free-shipping.index', ['inventory.free-shipping.*'], ['inventory.manage']),
                ]),
                self::item('Stock', [
                    self::tab('Entradas', 'inventory.stock.index', ['inventory.stock.*'], ['stock.manage']),
                    self::tab('Vendidos', 'inventory.sold.index', ['inventory.sold.*'], ['inventory.view']),
                    self::tab('Movimientos', 'inventory.movements.index', ['inventory.movements.*'], ['inventory.view']),
                    self::tab('On demand', 'inventory.on-demand.index', ['inventory.on-demand.*'], ['inventory.view']),
                ]),
                self::item('Proveedores', [
                    self::tab('Proveedores', 'inventory.suppliers.index', ['inventory.suppliers.*'], ['suppliers.manage']),
                    self::tab('Cuentas por pagar', 'inventory.payables.index', ['inventory.payables.*'], ['suppliers.manage']),
                ]),
            ]],
            ['label' => 'Operaciones', 'items' => [
                self::item('Consignación', [
                    self::tab('Dashboard', 'consignments.dashboard', [], ['consignments.manage']),
                    self::tab('Entregas', 'consignments.index', ['consignments.index', 'consignments.show', 'consignments.create'], ['consignments.manage']),
                    self::tab('Liquidar', 'consignments.settle', ['consignments.settle*'], ['consignments.manage']),
                    self::tab('Por mes', 'consignments.monthly-products', [], ['consignments.manage']),
                ]),
                self::item('Logística', [
                    self::tab('Envíos', 'logistics.shipments.index', ['logistics.shipments.*'], ['logistics.view']),
                    self::tab('Empresas', 'logistics.clients.index', ['logistics.clients.*'], ['logistics.view']),
                    self::tab('Liquidar', 'logistics.settlements.index', ['logistics.settlements.*'], ['logistics.settlements']),
                ]),
            ]],
            ['label' => 'Contabilidad', 'items' => [
                self::item('Resumen', [
                    self::tab('Resumen', 'accounting.dashboard', [], ['accounting.view']),
                    self::tab('Resultado y márgenes', 'costs.dashboard', ['costs.dashboard', 'costs.margins'], ['accounting.view']),
                    self::tab('Por período', 'costs.periods', [], ['accounting.view']),
                    self::tab('Por vendedor', 'costs.sellers', [], ['accounting.view']),
                ]),
                self::item('Estados de cuenta', [self::tab('Estados de cuenta', 'accounting.statements', ['accounting.statements*'], ['accounting.view'])]),
                self::item('Gastos', [self::tab('Gastos', 'costs.expenses.index', ['costs.expenses.*'], ['expenses.manage'])]),
            ]],
            ['label' => 'Configuración', 'items' => [
                self::item('Usuarios', [
                    self::tab('Usuarios', 'settings.users.index', ['settings.users.*'], ['settings.users']),
                    self::tab('Roles', 'settings.roles.index', ['settings.roles.*'], ['settings.users']),
                ]),
                self::item('Tiendas', [self::tab('Tiendas', 'settings.stores.index', ['settings.stores.*'], ['settings.stores'])]),
                self::item('Empresas de envío', [self::tab('Empresas de envío', 'sales.shipping-carriers.index', ['sales.shipping-carriers.*'], ['settings.carriers'])]),
            ]],
        ];
    }

    /**
     * Sections with only the items/tabs the user can see.
     *
     * @return array<int, array{label: string, items: array<int, array{label: string, tabs: array<int, array{label: string, route: string, active: array<int, string>, permissions: array<int, string>}>}>}>
     */
    public static function sectionsFor(?User $user): array
    {
        $visible = [];

        foreach (self::sections() as $section) {
            $items = [];
            foreach ($section['items'] as $item) {
                $tabs = array_values(array_filter($item['tabs'], fn (array $tab) => self::tabAllowed($user, $tab)));
                if ($tabs !== []) {
                    $items[] = ['label' => $item['label'], 'tabs' => $tabs];
                }
            }
            if ($items !== []) {
                $visible[] = ['label' => $section['label'], 'items' => $items];
            }
        }

        return $visible;
    }

    /**
     * Tabs of the active item, only when there is more than one visible.
     *
     * @return array<int, array{label: string, route: string, active: array<int, string>, permissions: array<int, string>, is_active: bool}>
     */
    public static function activeTabs(Request $request): array
    {
        foreach (self::sectionsFor($request->user()) as $section) {
            foreach ($section['items'] as $item) {
                if (count($item['tabs']) < 2 || ! self::itemIsActive($request, $item)) {
                    continue;
                }

                return array_map(
                    fn (array $tab) => $tab + ['is_active' => self::tabIsActive($request, $tab)],
                    $item['tabs'],
                );
            }
        }

        return [];
    }

    /**
     * First page the user is allowed to open.
     */
    public static function homeUrl(?User $user): string
    {
        if ($user && ! $user->isAdmin() && $user->hasPermission('pos.sell')) {
            return route('store.pos');
        }

        foreach (self::sectionsFor($user) as $section) {
            foreach ($section['items'] as $item) {
                return route($item['tabs'][0]['route']);
            }
        }

        return route('account.edit');
    }

    /**
     * @param  array{tabs: array<int, array{active: array<int, string>}>}  $item
     */
    public static function itemIsActive(Request $request, array $item): bool
    {
        foreach ($item['tabs'] as $tab) {
            if (self::tabIsActive($request, $tab)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array{active: array<int, string>}  $tab
     */
    public static function tabIsActive(Request $request, array $tab): bool
    {
        return $request->routeIs(...$tab['active']);
    }

    /**
     * @param  array{permissions: array<int, string>}  $tab
     */
    public static function tabAllowed(?User $user, array $tab): bool
    {
        return $user !== null && ($tab['permissions'] === [] || $user->hasAnyPermission(...$tab['permissions']));
    }

    /**
     * @param  array<int, array{label: string, route: string, active: array<int, string>, permissions: array<int, string>}>  $tabs
     * @return array{label: string, tabs: array<int, array{label: string, route: string, active: array<int, string>, permissions: array<int, string>}>}
     */
    private static function item(string $label, array $tabs): array
    {
        return ['label' => $label, 'tabs' => $tabs];
    }

    /**
     * @param  array<int, string>  $active
     * @param  array<int, string>  $permissions
     * @return array{label: string, route: string, active: array<int, string>, permissions: array<int, string>}
     */
    private static function tab(string $label, string $route, array $active = [], array $permissions = []): array
    {
        return ['label' => $label, 'route' => $route, 'active' => $active ?: [$route], 'permissions' => $permissions];
    }
}
