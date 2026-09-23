<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Support\Totp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class TwoFactorController extends Controller
{
    public function challenge()
    {
        $user = Auth::user();
        if (! $user || ! $user->requiresTwoFactor()) {
            return redirect()->route('home');
        }
        if (! $user->hasTwoFactorEnabled()) {
            return redirect()->route('two-factor.setup');
        }
        if (session('two_factor_passed')) {
            return redirect()->route('home');
        }

        return view('auth.two-factor-challenge');
    }

    public function verifyChallenge(Request $request)
    {
        $request->validate([
            'code' => ['required', 'string'],
        ], [
            'code.required' => 'Введите код из приложения',
        ]);

        $user = Auth::user();
        if (! $user || ! $user->two_factor_secret) {
            return redirect()->route('login');
        }

        if (! Totp::verify($user->two_factor_secret, $request->input('code'))) {
            Log::info('two_factor_failed', ['user_id' => $user->id]);
            throw ValidationException::withMessages([
                'code' => ['Неверный код. Попробуйте ещё раз.'],
            ]);
        }

        $request->session()->put('two_factor_passed', true);
        Log::info('two_factor_passed', ['user_id' => $user->id]);

        return redirect()->intended(route('home'));
    }

    public function setup()
    {
        $user = Auth::user();
        if (! $user || ! ($user->isDeveloper() || $user->isGeneralDirector())) {
            return redirect()->route('home');
        }

        if (! $user->two_factor_secret || $user->two_factor_confirmed_at) {
            $secret = Totp::generateSecret();
            $user->forceFill([
                'two_factor_secret' => $secret,
                'two_factor_confirmed_at' => null,
            ])->save();
            request()->session()->forget('two_factor_passed');
        } else {
            $secret = $user->two_factor_secret;
        }

        $uri = Totp::provisioningUri($secret, $user->email ?? $user->name);

        return view('auth.two-factor-setup', [
            'secret' => $secret,
            'otpauthUri' => $uri,
            'qrUrl' => Totp::qrImageUrl($uri),
        ]);
    }

    public function confirmSetup(Request $request)
    {
        $request->validate([
            'code' => ['required', 'string'],
        ]);

        $user = Auth::user();
        if (! $user?->two_factor_secret) {
            return redirect()->route('two-factor.setup');
        }

        if (! Totp::verify($user->two_factor_secret, $request->input('code'))) {
            throw ValidationException::withMessages([
                'code' => ['Неверный код. Проверьте время на телефоне и секрет.'],
            ]);
        }

        $user->forceFill(['two_factor_confirmed_at' => now()])->save();
        $request->session()->put('two_factor_passed', true);
        Log::info('two_factor_enabled', ['user_id' => $user->id]);

        return redirect()->route('home')->with('success', 'Двухфакторная аутентификация включена.');
    }
}
