<?php

namespace Tests\Unit;

use App\Models\Role;
use App\Models\User;
use App\Support\Navigation;
use App\Support\Permissions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class NavigationTest extends TestCase
{
    private function userWith(array $permissions, bool $admin = false): User
    {
        $user = new User(['is_active' => true]);
        $user->setRelation('role', new Role(['is_admin' => $admin, 'permissions' => $permissions]));

        return $user;
    }

    private function requestFor(string $routeName, ?User $user): Request
    {
        $request = Request::create('/');
        $request->setRouteResolver(fn () => Route::getRoutes()->getByName($routeName));
        $request->setUserResolver(fn () => $user);

        return $request;
    }

    public function test_every_tab_points_to_an_existing_route_that_marks_itself_active(): void
    {
        foreach (Navigation::sections() as $section) {
            foreach ($section['items'] as $item) {
                $this->assertNotEmpty($item['tabs'], "{$item['label']} has no tabs");

                foreach ($item['tabs'] as $tab) {
                    $route = Route::getRoutes()->getByName($tab['route']);
                    $this->assertNotNull($route, "Missing route {$tab['route']}");

                    $request = $this->requestFor($tab['route'], null);

                    $this->assertTrue(Navigation::tabIsActive($request, $tab), "{$tab['route']} does not match its own active patterns");
                    $this->assertTrue(Navigation::itemIsActive($request, $item));

                    foreach ($tab['permissions'] as $permission) {
                        $this->assertContains($permission, Permissions::all(), "Unknown permission {$permission}");
                    }
                }
            }
        }
    }

    public function test_tabs_only_show_for_multi_tab_items(): void
    {
        $admin = $this->userWith([], admin: true);

        $tabs = Navigation::activeTabs($this->requestFor('inventory.combos.index', $admin));

        $this->assertSame(['Productos', 'Combos', 'Precios masivos', 'Envío gratis'], array_column($tabs, 'label'));
        $this->assertSame([false, true, false, false], array_column($tabs, 'is_active'));

        $this->assertSame([], Navigation::activeTabs($this->requestFor('store.pos', $admin)));
    }

    public function test_menu_hides_what_the_role_cannot_use(): void
    {
        $cashier = $this->userWith(['pos.sell', 'cash.manage', 'inventory.view']);

        $labels = collect(Navigation::sectionsFor($cashier))->pluck('items')->collapse()->pluck('label')->all();

        $this->assertContains('Punto de venta', $labels);
        $this->assertContains('Caja', $labels);
        $this->assertNotContains('Contabilidad', $labels);
        $this->assertNotContains('Usuarios', $labels);
        $this->assertNotContains('Proveedores', $labels);

        $productTabs = Navigation::activeTabs($this->requestFor('inventory.combos.index', $cashier));
        $this->assertSame(['Productos', 'Combos'], array_column($productTabs, 'label'));

        $this->assertSame([], Navigation::sectionsFor($this->userWith([])));
    }

    public function test_logistics_tabs_follow_split_permissions(): void
    {
        $dispatch = $this->userWith(Permissions::logisticsDispatchRole()['permissions']);
        $tabs = Navigation::activeTabs($this->requestFor('logistics.shipments.index', $dispatch));
        $this->assertSame(['Envíos', 'Empresas'], array_column($tabs, 'label'));

        $accountant = $this->userWith(['logistics.view', 'logistics.settlements']);
        $tabs = Navigation::activeTabs($this->requestFor('logistics.shipments.index', $accountant));
        $this->assertSame(['Envíos', 'Empresas', 'Liquidar'], array_column($tabs, 'label'));

        $routes = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($route) => str_starts_with((string) $route->getName(), 'logistics.'));
        $this->assertNotEmpty($routes);
        foreach ($routes as $route) {
            $guards = collect($route->gatherMiddleware())->filter(fn ($m) => str_starts_with($m, 'permission:logistics.'));
            $this->assertCount(1, $guards, "{$route->getName()} debe tener exactamente un permiso de logística");
        }
    }

    public function test_default_roles_only_use_known_permissions(): void
    {
        foreach (Permissions::defaultRoles() as $role) {
            foreach ($role['permissions'] as $permission) {
                $this->assertContains($permission, Permissions::all(), "{$role['name']} uses unknown {$permission}");
            }
        }
    }

    public function test_inactive_user_has_no_permissions(): void
    {
        $user = $this->userWith([], admin: true);
        $user->is_active = false;

        $this->assertFalse($user->hasPermission('sales.view_all'));
    }
}
