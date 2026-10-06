<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Navigation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class AccountController extends Controller
{
    public function edit(Request $request): View
    {
        return view('account.edit', [
            'user' => $request->user()->load(['role', 'seller', 'stores']),
        ]);
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $user = $request->user();

        $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::min(8), 'different:current_password'],
        ], [
            'current_password.current_password' => 'La contraseña actual no es correcta.',
            'password.different' => 'La nueva contraseña debe ser distinta a la actual.',
        ]);

        $user->forceFill([
            'password' => $request->string('password')->toString(),
            'must_change_password' => false,
        ])->save();

        $request->session()->regenerate();

        return redirect(Navigation::homeUrl($user))->with('success', 'Contraseña actualizada.');
    }

    public function updatePin(Request $request): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'pos_pin' => ['nullable', 'digits_between:4,6', 'confirmed'],
        ], [
            'current_password.current_password' => 'La contraseña actual no es correcta.',
            'pos_pin.digits_between' => 'El PIN debe tener de 4 a 6 dígitos.',
        ]);

        $pin = $data['pos_pin'] ?? null;
        if ($pin !== null && self::pinTaken($pin, $user)) {
            return back()->withErrors(['pos_pin' => 'Ese PIN ya lo usa otra persona. Elige otro.']);
        }

        $user->forceFill(['pos_pin' => $pin])->save();

        return back()->with('success', $pin ? 'PIN de caja actualizado.' : 'PIN de caja eliminado.');
    }

    public static function pinTaken(string $pin, ?User $except = null): bool
    {
        return User::query()
            ->whereNotNull('pos_pin')
            ->when($except, fn ($q) => $q->whereKeyNot($except->id))
            ->get(['id', 'pos_pin'])
            ->contains(fn (User $other) => $other->checkPosPin($pin));
    }
}
