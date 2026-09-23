<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Абсолютный срок сессии (ТЗ FR-PRIV-03): idle отдельно в config/session lifetime.
 */
class EnforceAbsoluteSessionTimeout
{
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check()) {
            $startedAt = $request->session()->get('auth_started_at');
            if (! is_numeric($startedAt)) {
                $request->session()->put('auth_started_at', time());
            } else {
                $maxSeconds = (int) config('session.absolute_lifetime', 60 * 24 * 7) * 60;
                if ($maxSeconds > 0 && (time() - (int) $startedAt) > $maxSeconds) {
                    Auth::logout();
                    $request->session()->invalidate();
                    $request->session()->regenerateToken();

                    if ($request->expectsJson()) {
                        return response()->json(['message' => 'Сессия истекла. Войдите снова.'], 401);
                    }

                    return redirect()->route('login')->with(
                        'csrf_notice',
                        'Сессия истекла по сроку. Войдите снова.'
                    );
                }
            }
        }

        return $next($request);
    }
}
