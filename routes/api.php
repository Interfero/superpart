<?php

use App\Http\Controllers\Api\LevelionWebhookController;
use App\Http\Middleware\VerifyLevelionWebhook;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API (интеграция с Levelion CRM)
|--------------------------------------------------------------------------
*/

Route::prefix('v1')
    ->middleware([VerifyLevelionWebhook::class])
    ->group(function () {
        Route::get('/ping', fn () => response()->json(['ok' => true, 'app' => 'superpart']))->name('api.v1.ping');
        Route::post('/webhooks/order-snapshot', [LevelionWebhookController::class, 'orderSnapshot'])
            ->name('api.v1.webhooks.order-snapshot');
        Route::post('/webhooks/order-completed', [LevelionWebhookController::class, 'orderCompleted'])
            ->name('api.v1.webhooks.order-completed');
        Route::post('/webhooks/partner-order-created', [LevelionWebhookController::class, 'partnerOrderCreated'])
            ->name('api.v1.webhooks.partner-order-created');
        Route::post('/webhooks/reference-source-upsert', [LevelionWebhookController::class, 'referenceSourceUpsert'])
            ->name('api.v1.webhooks.reference-source-upsert');
    });
