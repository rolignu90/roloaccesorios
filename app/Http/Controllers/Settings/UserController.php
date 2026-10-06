<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\Seller;
use App\Models\Store;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(): View
    {
        $users = User::query()
            ->with(['role:id,name,is_admin,permissions', 'seller:id,name,sale_prefix', 'stores:id,name'])
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get();

        return view('settings.users.index', compact('users'));
    }

    public function create(): View
    {
        return view('settings.users.create', $this->formData(new User(['is_active' => true])));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $password = $data['password'] ?: Str::password(10, symbols: false);

        $user = User::query()->create([
            ...collect($data)->except(['password', 'store_ids'])->all(),
            'password' => $password,
            'must_change_password' => true,
        ]);
        $user->stores()->sync($data['store_ids'] ?? []);

        return redirect()
            ->route('settings.users.index')
            ->with('success', "Usuario {$user->username} creado. Contraseña temporal: {$password} (se pedirá cambiarla al entrar).");
    }

    public function edit(User $user): View
    {
        return view('settings.users.edit', $this->formData($user->load('stores')));
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $this->validated($request, $user);

        $losesAdmin = $user->isAdmin()
            && (! $data['is_active'] || ! Role::query()->whereKey($data['role_id'])->value('is_admin'));
        $otherAdmins = User::query()->active()->whereKeyNot($user->id)
            ->whereHas('role', fn ($q) => $q->where('is_admin', true))->exists();
        if ($losesAdmin && ! $otherAdmins) {
            return back()->withInput()->with('error', 'Es el único administrador activo; no se le puede quitar el rol ni desactivar.');
        }

        $message = "Usuario {$user->username} actualizado.";
        $fill = collect($data)->except(['password', 'store_ids'])->all();

        if (! empty($data['password'])) {
            $fill['password'] = $data['password'];
            $fill['must_change_password'] = true;
            $message .= ' Se le pedirá cambiar la contraseña al entrar.';
        }
        if ($request->boolean('reset_pin')) {
            $fill['pos_pin'] = null;
            $message .= ' PIN de caja eliminado.';
        }

        $user->update($fill);
        $user->stores()->sync($data['store_ids'] ?? []);

        return redirect()->route('settings.users.index')->with('success', $message);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?User $user = null): array
    {
        $request->merge([
            'username' => Str::lower(trim((string) $request->input('username'))),
            'is_active' => $request->boolean('is_active'),
        ]);

        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'username' => ['required', 'string', 'max:50', 'regex:/^[a-z0-9._-]+$/', Rule::unique('users', 'username')->ignore($user)],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user)],
            'role_id' => ['required', 'integer', Rule::exists('roles', 'id')],
            'seller_id' => ['nullable', 'integer', Rule::exists('sellers', 'id'), Rule::unique('users', 'seller_id')->ignore($user)],
            'store_ids' => ['nullable', 'array'],
            'store_ids.*' => ['integer', Rule::exists('stores', 'id')],
            'is_active' => ['boolean'],
            'password' => ['nullable', Password::min(8)],
        ], [
            'username.regex' => 'El usuario solo puede tener minúsculas, números, punto, guion o guion bajo.',
            'username.unique' => 'Ese usuario ya existe.',
            'seller_id.unique' => 'Ese vendedor ya está vinculado a otro usuario.',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(User $user): array
    {
        return [
            'user' => $user,
            'roles' => Role::query()->orderByDesc('is_admin')->orderBy('name')->get(['id', 'name', 'description']),
            'sellers' => Seller::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'sale_prefix']),
            'stores' => Store::query()->orderBy('name')->get(['id', 'name', 'is_active']),
        ];
    }
}
