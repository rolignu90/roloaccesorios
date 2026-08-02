<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PinAuthController extends Controller
{
    public function showLogin(): View|RedirectResponse
    {
        if (session('pin_authenticated')) {
            return redirect()->route('inventory.products.index');
        }

        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $request->validate([
            'pin' => ['required', 'string'],
        ]);

        $configuredPin = (string) config('pin.pin');

        if ($configuredPin === '' || ! hash_equals($configuredPin, $request->string('pin')->toString())) {
            return back()
                ->withErrors(['pin' => 'PIN incorrecto.'])
                ->onlyInput('pin');
        }

        $request->session()->regenerate();
        $request->session()->put('pin_authenticated', true);

        return redirect()->intended(route('inventory.products.index'));
    }

    public function logout(Request $request): RedirectResponse
    {
        $request->session()->forget('pin_authenticated');
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
