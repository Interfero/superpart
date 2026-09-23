<?php

use App\Support\TrustedProxies;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: TrustedProxies::resolve());
        $middleware->web(replace: [
            \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class => \App\Http\Middleware\VerifyCsrfToken::class,
            \Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class => \App\Http\Middleware\VerifyCsrfToken::class,
        ]);
        $middleware->append(\App\Http\Middleware\SecurityHeaders::class);
        $middleware->appendToGroup('api', \App\Http\Middleware\AssignCorrelationId::class);
        $middleware->appendToGroup('web', \App\Http\Middleware\EnforceAbsoluteSessionTimeout::class);
        $middleware->appendToGroup('web', \App\Http\Middleware\EnsureTwoFactorSatisfied::class);
        $middleware->redirectUsersTo('/');
        $middleware->redirectGuestsTo('/login');
        $middleware->alias([
            'levelion.webhook' => \App\Http\Middleware\VerifyLevelionWebhook::class,
            'developer.only' => \App\Http\Middleware\EnsureDeveloper::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->dontFlash([
            'client_secret',
            'api_key',
            'api_secret',
            'crm_api_key',
            'crm_api_secret',
            'crm_api_login',
            'crm_api_password',
            'crm_cabinet_login',
            'crm_cabinet_password',
            'gray_auth_url',
        ]);

        $exceptions->render(function (TokenMismatchException $e, Request $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Сессия устарела. Обновите страницу.',
                ], 419);
            }

            if ($request->user()) {
                return redirect()->back()->with(
                    'csrf_notice',
                    'Сессия устарела. Обновите страницу и отправьте форму снова.'
                );
            }

            return redirect()->route('login')->with(
                'csrf_notice',
                'Сессия устарела. Обновите страницу и попробуйте войти снова.'
            );
        });
    })->create();
