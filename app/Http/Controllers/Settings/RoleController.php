<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Support\Permissions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class RoleController extends Controller
{
    public function index(): View
    {
        return view('settings.roles.index', [
            'roles' => Role::query()->withCount('users')->orderByDesc('is_admin')->orderBy('name')->get(),
            'groups' => Permissions::groups(),
        ]);
    }

    public function create(): View
    {
        return view('settings.roles.form', ['role' => new Role(['permissions' => []]), 'groups' => Permissions::groups()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $role = Role::query()->create($this->validated($request));

        return redirect()->route('settings.roles.index')->with('success', "Rol {$role->name} creado.");
    }

    public function edit(Role $role): View
    {
        return view('settings.roles.form', ['role' => $role, 'groups' => Permissions::groups()]);
    }

    public function update(Request $request, Role $role): RedirectResponse
    {
        if ($role->is_admin) {
            return back()->with('error', 'El rol Administrador siempre tiene acceso total y no se edita.');
        }

        $role->update($this->validated($request, $role));

        return redirect()->route('settings.roles.index')->with('success', "Rol {$role->name} actualizado.");
    }

    public function destroy(Role $role): RedirectResponse
    {
        if ($role->is_admin || $role->users()->exists()) {
            return back()->with('error', 'No se puede eliminar un rol administrador o que tenga usuarios asignados.');
        }

        $role->delete();

        return redirect()->route('settings.roles.index')->with('success', 'Rol eliminado.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Role $role = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80', Rule::unique('roles', 'name')->ignore($role)],
            'description' => ['nullable', 'string', 'max:255'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', Rule::in(Permissions::all())],
        ]);

        $data['permissions'] = array_values(array_unique($data['permissions'] ?? []));
        $data['is_admin'] = false;

        return $data;
    }
}
