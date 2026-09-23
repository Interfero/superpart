<?php

namespace App\Providers;

use App\Models\User;
use App\Models\Transaction;
use App\Observers\TransactionObserver;
use App\Observers\UserProtectedAccountObserver;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }

        Gate::before(function (?User $user, string $ability) {
            if ($user?->hasElevatedAccess()) {
                return true;
            }

            return null;
        });

        Gate::define('use-withdrawals', fn (User $user) => ! $user->isManager());

        Gate::define('access-management-users', fn (User $user) => $user->hasElevatedAccess());

        Gate::define('access-employees-directory', fn (User $user) => $user->hasElevatedAccess() || $user->isPartner());

        Gate::define('access-management-reference-sources', fn (User $user) => $user->hasElevatedAccess());

        Gate::define('manage-sources', fn (User $user) => ! $user->isManager());

        Gate::define('access-cities-directory', fn (User $user) => ! $user->isManager());

        // На проде без `composer dump-autoload` после FTP класс может отсутствовать в classmap.
        foreach ([
            app_path('Models/AgentAccountLock.php'),
            app_path('Support/ProtectedAccountGuard.php'),
            app_path('Support/WithdrawalSubmitter.php'),
            app_path('Observers/UserProtectedAccountObserver.php'),
        ] as $classFile) {
            if (is_file($classFile)) {
                require_once $classFile;
            }
        }

        if ($this->app->runningInConsole()) {
            $path = database_path('seeders/IntegrationTestPartnerSeeder.php');
            if (is_file($path)) {
                require_once $path;
            }
        }

        Transaction::observe(TransactionObserver::class);
        User::observe(UserProtectedAccountObserver::class);
    }
}
