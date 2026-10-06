<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Navigation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function showLogin(): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect(Navigation::homeUrl(Auth::user()));
        }

        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'username' => ['required', 'string', 'max:50'],
            'password' => ['required', 'string'],
        ]);

        $username = Str::lower(trim($credentials['username']));
        $throttleKey = 'login:'.$username.'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            throw ValidationException::withMessages([
                'username' => "Demasiados intentos. Intenta de nuevo en {$seconds} segundos.",
            ]);
        }

        $user = User::query()->where('username', $username)->first();
        $valid = $user && $user->is_active
            && Auth::attempt(['username' => $username, 'password' => $credentials['password']], $request->boolean('remember'));

        if (! $valid) {
            RateLimiter::hit($throttleKey, 60);

            $inactive = $user && ! $user->is_active && Hash::check($credentials['password'], $user->password);

            throw ValidationException::withMessages([
                'username' => $inactive ? 'Este usuario está desactivado.' : 'Usuario o contraseña incorrectos.',
            ]);
        }

        RateLimiter::clear($throttleKey);
        $request->session()->regenerate();
        $user->forceFill(['last_login_at' => now()])->save();

        return redirect()->intended(Navigation::homeUrl($user));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
