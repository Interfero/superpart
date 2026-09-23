<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthController extends Controller
{
    /** Лимит попыток на нормализованный email (ТЗ SEC-02). */
    private const LOGIN_MAX_ATTEMPTS_EMAIL = 5;

    private const LOGIN_DECAY_SECONDS_EMAIL = 60;

    /** Общий антиабуз по IP — не блокирует чужой аккаунт. */
    private const LOGIN_MAX_ATTEMPTS_IP = 40;

    private const LOGIN_DECAY_SECONDS_IP = 60;

    public function showLogin(): View
    {
        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        $emailKey = $this->loginEmailThrottleKey($request);
        $ipKey = $this->loginIpThrottleKey($request);

        if (RateLimiter::tooManyAttempts($emailKey, self::LOGIN_MAX_ATTEMPTS_EMAIL)) {
            $seconds = RateLimiter::availableIn($emailKey);

            throw ValidationException::withMessages([
                'email' => "Слишком много попыток входа. Повторите через {$seconds} сек.",
            ])->redirectTo(route('login'));
        }

        if (RateLimiter::tooManyAttempts($ipKey, self::LOGIN_MAX_ATTEMPTS_IP)) {
            $seconds = RateLimiter::availableIn($ipKey);

            throw ValidationException::withMessages([
                'email' => "Слишком много попыток с этого адреса. Повторите через {$seconds} сек.",
            ])->redirectTo(route('login'));
        }

        // Без постоянного remember-token (ТЗ FR-PRIV-03).
        if (Auth::attempt($credentials, false)) {
            RateLimiter::clear($emailKey);
            RateLimiter::clear($ipKey);
            $request->session()->regenerate();
            $request->session()->put('auth_started_at', time());
            $request->session()->forget('two_factor_passed');

            /** @var \App\Models\User $user */
            $user = $request->user();
            $user->last_login_at = now();
            $user->save();

            // Снести устаревший cookie запоминания email, если ещё есть у клиента.
            Cookie::queue(Cookie::forget('superpart_remember_email'));

            return redirect()->intended('/dashboard');
        }

        RateLimiter::hit($emailKey, self::LOGIN_DECAY_SECONDS_EMAIL);
        RateLimiter::hit($ipKey, self::LOGIN_DECAY_SECONDS_IP);

        return back()
            ->withErrors([
                'email' => 'Неверный email или пароль.',
            ])
            ->onlyInput('email');
    }

    private function loginEmailThrottleKey(Request $request): string
    {
        return 'login:email:'.Str::transliterate(Str::lower((string) $request->input('email')));
    }

    private function loginIpThrottleKey(Request $request): string
    {
        return 'login:ip:'.$request->ip();
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        Cookie::queue(Cookie::forget('superpart_remember_email'));

        return redirect('/');
    }
}
