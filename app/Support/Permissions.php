<?php

namespace App\Support;

/**
 * Permission catalog. Keys are stored in roles.permissions; admin roles bypass all checks.
 */
class Permissions
{
    /**
     * @return array<string, array<string, string>>
     */
    public static function groups(): array
    {
        return [
            'Tienda' => [
                'pos.sell' => 'Vender en el punto de venta',
                'pos.discount' => 'Dar descuentos en el punto de venta',
                'pos.price_override' => 'Cambiar precios en el punto de venta',
                'cash.manage' => 'Abrir, cerrar y mover efectivo en la caja de sus tiendas',
                'cash.view_all' => 'Ver y operar cajas de todas las tiendas',
            ],
            'Ventas' => [
                'sales.view_all' => 'Ver todas las ventas (si no, solo las propias)',
                'sales.create' => 'Crear ventas en línea',
                'sales.edit' => 'Editar ventas, cambiar estados y enviar a Sistrack',
                'sales.void' => 'Anular ventas',
                'sales.export' => 'Exportar reportes y etiquetas',
                'customers.manage' => 'Clientes',
                'sellers.manage' => 'Vendedores y liquidaciones',
            ],
            'Inventario' => [
                'inventory.view' => 'Ver dashboard, productos, combos y movimientos',
                'inventory.manage' => 'Crear/editar productos, combos y precios',
                'inventory.costs' => 'Ver costos y márgenes',
                'stock.manage' => 'Entradas de stock',
                'suppliers.manage' => 'Proveedores y cuentas por pagar',
            ],
            'Operaciones' => [
                'consignments.manage' => 'Consignación',
            ],
            'Envíos a terceros' => [
                'logistics.view' => 'Ver envíos y empresas, imprimir etiquetas Sistrack',
                'logistics.create' => 'Crear envíos, enviar a Sistrack, sincronizar y marcar entregados',
                'logistics.void' => 'Anular envíos y marcar devoluciones',
                'logistics.clients' => 'Crear y editar empresas cliente (comisiones y datos)',
                'logistics.settlements' => 'Liquidaciones (pagos a las empresas)',
            ],
            'Contabilidad' => [
                'accounting.view' => 'Resumen, estados de cuenta y reportes',
                'expenses.manage' => 'Gastos',
            ],
            'Configuración' => [
                'settings.stores' => 'Tiendas',
                'settings.carriers' => 'Empresas de envío',
                'settings.users' => 'Usuarios y roles',
            ],
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function all(): array
    {
        return array_merge(...array_map('array_keys', array_values(self::groups())));
    }

    /**
     * @return array<int, array{name: string, description: string, is_admin: bool, permissions: array<int, string>}>
     */
    public static function defaultRoles(): array
    {
        return [
            [
                'name' => 'Administrador',
                'description' => 'Acceso total.',
                'is_admin' => true,
                'permissions' => [],
            ],
            [
                'name' => 'Encargado de tienda',
                'description' => 'Punto de venta, caja y cortes de sus tiendas, descuentos y anulaciones.',
                'is_admin' => false,
                'permissions' => [
                    'pos.sell', 'pos.discount', 'pos.price_override', 'cash.manage',
                    'sales.view_all', 'sales.create', 'sales.edit', 'sales.void',
                    'customers.manage', 'inventory.view',
                ],
            ],
            [
                'name' => 'Cajero / vendedor de tienda',
                'description' => 'Vender y operar la caja de su tienda. Sin costos ni anulaciones.',
                'is_admin' => false,
                'permissions' => ['pos.sell', 'cash.manage', 'customers.manage', 'inventory.view'],
            ],
            [
                'name' => 'Vendedor en línea',
                'description' => 'Crea y ve solo sus ventas.',
                'is_admin' => false,
                'permissions' => ['sales.create', 'sales.edit', 'customers.manage', 'inventory.view'],
            ],
            [
                'name' => 'Bodega',
                'description' => 'Productos, stock y proveedores.',
                'is_admin' => false,
                'permissions' => ['inventory.view', 'inventory.manage', 'inventory.costs', 'stock.manage', 'suppliers.manage'],
            ],
            [
                'name' => 'Contador',
                'description' => 'Contabilidad, gastos, reportes y liquidaciones de envíos. Ventas en solo lectura.',
                'is_admin' => false,
                'permissions' => [
                    'accounting.view', 'expenses.manage', 'sales.view_all', 'sales.export', 'inventory.view', 'inventory.costs',
                    'logistics.view', 'logistics.settlements',
                ],
            ],
            self::logisticsDispatchRole(),
        ];
    }

    /**
     * @return array{name: string, description: string, is_admin: bool, permissions: array<int, string>}
     */
    public static function logisticsDispatchRole(): array
    {
        return [
            'name' => 'Logística / despacho',
            'description' => 'Crea envíos a terceros, los manda a Sistrack e imprime etiquetas. Sin anular ni liquidar.',
            'is_admin' => false,
            'permissions' => ['logistics.view', 'logistics.create'],
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function logisticsAll(): array
    {
        return array_keys(self::groups()['Envíos a terceros']);
    }
}
