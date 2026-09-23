<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CityController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FeedbackController;
use App\Http\Controllers\Management\ManagementReferenceSourceController;
use App\Http\Controllers\Management\ManagementUserController;
use App\Http\Controllers\NewsController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PortalAttachmentController;
use App\Http\Controllers\PortalNotificationController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\SourceController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\WithdrawalController;
use App\Http\Controllers\Developer\SyncMonitorController;
use App\Http\Controllers\WorkTypeController;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Route;

Route::view('/offer', 'offer')->name('offer');

Route::get('/', function () {
    return auth()->check()
        ? redirect('/dashboard')
        : redirect()->route('login');
})->name('welcome');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    Route::get('/two-factor/challenge', [\App\Http\Controllers\Auth\TwoFactorController::class, 'challenge'])
        ->name('two-factor.challenge');
    Route::post('/two-factor/challenge', [\App\Http\Controllers\Auth\TwoFactorController::class, 'verifyChallenge'])
        ->middleware('throttle:10,1')
        ->name('two-factor.challenge.verify');
    Route::get('/two-factor/setup', [\App\Http\Controllers\Auth\TwoFactorController::class, 'setup'])
        ->name('two-factor.setup');
    Route::post('/two-factor/setup', [\App\Http\Controllers\Auth\TwoFactorController::class, 'confirmSetup'])
        ->middleware('throttle:10,1')
        ->name('two-factor.setup.confirm');

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('home');

    /*
    |--------------------------------------------------------------------------
    | Новости
    |--------------------------------------------------------------------------
    */

    Route::get('/news', [NewsController::class, 'index'])->name('news.index');
    Route::get('/news/create', [NewsController::class, 'create'])->name('news.create');
    Route::post('/news', [NewsController::class, 'store'])->name('news.store');
    Route::get('/news/{id}', [NewsController::class, 'show'])
        ->whereNumber('id')
        ->name('news.show');
    Route::delete('/news/{id}', [NewsController::class, 'destroy'])
        ->whereNumber('id')
        ->name('news.destroy');

    /*
    |--------------------------------------------------------------------------
    | Уведомления
    |--------------------------------------------------------------------------
    */

    Route::get('/notifications', [PortalNotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/{notification}/read', [PortalNotificationController::class, 'markRead'])->name('notifications.read');
    Route::post('/notifications/read-all', [PortalNotificationController::class, 'markAllRead'])->name('notifications.read-all');

    /*
    |--------------------------------------------------------------------------
    | Настройки
    |--------------------------------------------------------------------------
    */

    Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');
    Route::patch('/settings/profile', [SettingsController::class, 'updateProfile'])->name('settings.profile');
    Route::patch('/settings/legal', [SettingsController::class, 'updateLegal'])->name('settings.legal');
    Route::patch('/settings/password', [SettingsController::class, 'updatePassword'])->name('settings.password');
    Route::match(['post', 'patch'], '/settings/theme', [SettingsController::class, 'updateTheme'])->name('settings.theme');
    Route::patch('/settings/api-credentials', [SettingsController::class, 'updateApiCredentials'])->name('settings.api-credentials');

    Route::post('/settings/bank-cards', [SettingsController::class, 'storeBankCard'])->name('settings.bank-cards.store');
    Route::patch('/settings/bank-cards/{bankCard}', [SettingsController::class, 'updateBankCard'])->name('settings.bank-cards.update');
    Route::delete('/settings/bank-cards/{bankCard}', [SettingsController::class, 'destroyBankCard'])->name('settings.bank-cards.destroy');

    Route::patch('/settings/phones', [SettingsController::class, 'updatePartnerPhones'])->name('settings.phones.update');
    Route::post('/settings/phones', [SettingsController::class, 'storePartnerPhone'])->name('settings.phones.store');

    /*
    |--------------------------------------------------------------------------
    | Обратная связь
    |--------------------------------------------------------------------------
    */

    Route::get('/feedback', [FeedbackController::class, 'index'])->name('feedback.index');
    Route::get('/feedback/create', [FeedbackController::class, 'create'])->name('feedback.create');
    Route::post('/feedback', [FeedbackController::class, 'store'])->name('feedback.store');
    Route::get('/feedback/{feedback}', [FeedbackController::class, 'show'])->name('feedback.show');
    Route::get('/attachments/feedback/{feedback}/{index}', [PortalAttachmentController::class, 'feedback'])
        ->whereNumber('index')
        ->name('attachments.feedback');
    Route::get('/attachments/reviews/{review}/{index}', [PortalAttachmentController::class, 'reviewPhoto'])
        ->whereNumber('index')
        ->name('attachments.review-photo');

    /*
    |--------------------------------------------------------------------------
    | Заявки
    |--------------------------------------------------------------------------
    */

    Route::get('/orders/create', [OrderController::class, 'create'])->name('orders.create');
    Route::get('/orders/city/{city}/time', [OrderController::class, 'cityTime'])->name('orders.city-time');
    Route::get('/orders/city/{city}/streets', [OrderController::class, 'streetSuggestions'])->name('orders.city-streets');

    Route::post('/orders', [OrderController::class, 'store'])->name('orders.store');

    Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');
    Route::get('/orders/unprocessed', [OrderController::class, 'unprocessed'])->name('orders.unprocessed');
    Route::get('/orders/export', [OrderController::class, 'export'])->name('orders.export');

    Route::get('/orders/{id}', [OrderController::class, 'show'])->name('orders.show');
    Route::post('/orders/{id}/comment', [OrderController::class, 'addComment'])->name('orders.comment');
    Route::post('/orders/{id}/cancel', [OrderController::class, 'cancel'])->name('orders.cancel');
    Route::post('/orders/{id}/retry-sync', [OrderController::class, 'retrySync'])->name('orders.retry-sync');

    /*
    |--------------------------------------------------------------------------
    | Начисления
    |--------------------------------------------------------------------------
    */

    Route::get('/transactions', [TransactionController::class, 'index'])->name('transactions.index');

    /*
    |--------------------------------------------------------------------------
    | Отчёты
    |--------------------------------------------------------------------------
    */

    Route::get('/reports/orders', [ReportController::class, 'orders'])->name('reports.orders');
    Route::get('/reports/orders/export', [ReportController::class, 'exportOrders'])->name('reports.orders.export');
    Route::get('/reports/cities', [ReportController::class, 'cities'])->name('reports.cities');
    Route::get('/reports/work-types', [ReportController::class, 'workTypes'])->name('reports.work-types');
    Route::get('/reports/reviews', [ReportController::class, 'reviews'])->name('reports.reviews');
    
    /*
    |--------------------------------------------------------------------------
    | Сотрудники
    |--------------------------------------------------------------------------
    */

    Route::redirect('/employees', '/management/users', 302)->name('employees.index');
    Route::redirect('/employees/create', '/management/users/create', 302)->name('employees.create');

    /*
    |--------------------------------------------------------------------------
    | Источники
    |--------------------------------------------------------------------------
    */

    Route::get('/sources', [SourceController::class, 'index'])->name('sources.index');
    Route::get('/sources/create', [SourceController::class, 'create'])->name('sources.create');
    Route::post('/sources', [SourceController::class, 'store'])->name('sources.store');

    Route::get('/sources/{id}', [SourceController::class, 'show'])
        ->whereNumber('id')
        ->name('sources.show');

    Route::redirect('/sources/{id}/edit', '/sources/{id}', 301)->whereNumber('id');

    Route::put('/sources/{id}', [SourceController::class, 'update'])
        ->whereNumber('id')
        ->name('sources.update');

    Route::delete('/sources/{id}', [SourceController::class, 'destroy'])
        ->whereNumber('id')
        ->name('sources.destroy');

    /*
    |--------------------------------------------------------------------------
    | Города
    |--------------------------------------------------------------------------
    */

    Route::get('/cities', [CityController::class, 'index'])
        ->middleware('can:access-cities-directory')
        ->name('cities.index');

    /*
    |--------------------------------------------------------------------------
    | Виды работ
    |--------------------------------------------------------------------------
    */

    Route::get('/work-types', [WorkTypeController::class, 'index'])->name('work-types.index');

    /*
    |--------------------------------------------------------------------------
    | Выплаты
    |--------------------------------------------------------------------------
    */

    Route::middleware('can:use-withdrawals')->group(function () {
        Route::get('/withdrawals', [WithdrawalController::class, 'index'])->name('withdrawals.index');
        Route::get('/withdrawals/create', [WithdrawalController::class, 'create'])->name('withdrawals.create');
        Route::post('/withdrawals', [WithdrawalController::class, 'store'])->name('withdrawals.store');
        Route::get('/withdrawals/{id}', [WithdrawalController::class, 'show'])->name('withdrawals.show');
        Route::patch('/withdrawals/{id}/status', [WithdrawalController::class, 'updateStatus'])
            ->name('withdrawals.update-status');
    });

    /*
    |--------------------------------------------------------------------------
    | Отзывы
    |--------------------------------------------------------------------------
    */

    Route::get('/reviews', [ReviewController::class, 'index'])->name('reviews.index');
    Route::get('/reviews/create', [ReviewController::class, 'create'])->name('reviews.create');
    Route::post('/reviews', [ReviewController::class, 'store'])->name('reviews.store');

    Route::get('/reviews/{review}', [ReviewController::class, 'show'])->name('reviews.show');

    /*
    |--------------------------------------------------------------------------
    | Управление пользователями
    |--------------------------------------------------------------------------
    */

    Route::middleware('can:access-employees-directory')
        ->prefix('management')
        ->name('management.')
        ->group(function () {

            Route::get('/users', [ManagementUserController::class, 'index'])->name('users.index');
            Route::get('/users/create', [ManagementUserController::class, 'create'])->name('users.create');

            Route::post('/users', [ManagementUserController::class, 'store'])->name('users.store');

            Route::get('/users/{user}/edit', [ManagementUserController::class, 'edit'])->name('users.edit');

            Route::put('/users/{user}', [ManagementUserController::class, 'update'])->name('users.update');

            Route::delete('/users/{user}', [ManagementUserController::class, 'destroy'])->name('users.destroy');

            Route::post('/users/{user}/reset-password', [ManagementUserController::class, 'resetPassword'])
                ->name('users.reset-password');
        });

    /*
    |--------------------------------------------------------------------------
    | Управление CRM источниками
    |--------------------------------------------------------------------------
    */

    Route::middleware('developer.only')->group(function () {
        Route::get('/developer/sync', [SyncMonitorController::class, 'index'])->name('developer.sync');
    });

    Route::middleware('can:access-management-reference-sources')
        ->prefix('management')
        ->name('management.')
        ->group(function () {

            Route::get('/reference-sources', [ManagementReferenceSourceController::class, 'index'])
                ->name('reference-sources.index');

            Route::patch('/reference-sources/partners/{user}', [ManagementReferenceSourceController::class, 'updatePartnerSources'])
                ->name('reference-sources.partner-sources');

            Route::patch('/reference-sources/{referenceSource}', [ManagementReferenceSourceController::class, 'update'])
                ->name('reference-sources.update');

            Route::delete('/reference-sources/{referenceSource}', [ManagementReferenceSourceController::class, 'destroy'])
                ->name('reference-sources.destroy');
        });

    /*
    |--------------------------------------------------------------------------
    | UI Kit
    |--------------------------------------------------------------------------
    */

    if (App::isLocal()) {
        Route::view('/ui-kit', 'ui-kit.index')->name('ui-kit');
    }
});
