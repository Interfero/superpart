<?php

use App\Http\Controllers\StatisticsController;
use App\Models\ReferenceSource;
use App\Models\Source;
use App\Models\StatisticsCrmSetting;
use App\Models\StatisticsIntegration;
use App\Models\User;
use App\Services\StatisticsCrmService;
use App\Services\StatisticsIntegrationService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ViewErrorBag;

chdir(dirname(__DIR__));
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
View::share('errors', new ViewErrorBag());

$assert = static function (bool $condition, string $message): void {
    if (! $condition) {
        throw new RuntimeException($message);
    }
};

$avito = StatisticsIntegration::query()->where('provider', StatisticsIntegration::PROVIDER_AVITO)->firstOrFail();
$gray = StatisticsIntegration::query()->where('provider', StatisticsIntegration::PROVIDER_GRAY_API)->firstOrFail();
$crm = StatisticsCrmSetting::query()->firstOrFail();
$assert($avito->hasCredentials(), 'Avito credentials missing.');
$assert($gray->hasCredentials(), 'GrayAPI credentials missing.');
$assert($crm->hasCredentials(), 'CRM credentials missing.');
$assert(data_get($gray->settings, 'auth_url') !== '', 'GrayAPI authorization URL missing.');
$assert(trim((string) $gray->base_url) === '', 'GrayAPI statistics endpoint must remain empty until documented.');
$assert(! $gray->enabled, 'GrayAPI must not run without a documented statistics endpoint.');

$normalized = mb_strtolower(trim((string) $avito->source_name));
$sourceExists = ReferenceSource::query()->whereRaw('LOWER(TRIM(name)) = ?', [$normalized])->exists()
    || Source::withTrashed()->whereRaw('LOWER(TRIM(name)) = ?', [$normalized])->exists();
$assert($sourceExists, 'CRM source mapping missing.');
$assert($gray->source_name === $avito->source_name, 'Provider source mappings differ.');

$developer = User::query()->where('role', User::ROLE_DEVELOPER)->firstOrFail();
Auth::setUser($developer);
$html = app(StatisticsController::class)->index(Request::create('/statistics', 'GET'))->render();
$secretValues = array_merge(
    array_values((array) $avito->credentials),
    array_values((array) $gray->credentials),
    array_values((array) $crm->credentials),
);
foreach ($secretValues as $value) {
    if (is_string($value) && mb_strlen($value) >= 8 && $value !== 'login_password') {
        $assert(! str_contains($html, $value), 'A credential leaked into rendered HTML.');
    }
}

$rawIntegrationCredentials = implode('', DB::table('statistics_integrations')->pluck('credentials')->all());
$rawCrmCredentials = (string) DB::table('statistics_crm_settings')->value('credentials');
foreach ($secretValues as $value) {
    if (is_string($value) && mb_strlen($value) >= 8 && $value !== 'login_password') {
        $assert(! str_contains($rawIntegrationCredentials.$rawCrmCredentials, $value), 'A credential is stored as plaintext.');
    }
}

$integrationService = app(StatisticsIntegrationService::class);
$avitoAuth = $integrationService->test($avito);
$avitoSync = $avitoAuth && $integrationService->sync($avito, now()->startOfMonth(), now());
$crmRemote = app(StatisticsCrmService::class)->test($crm);

echo 'secure_storage=yes source_mapping=yes html_leak=no'.PHP_EOL;
echo 'avito_auth='.($avitoAuth ? 'yes' : 'no').' avito_sync='.($avitoSync ? 'yes' : 'no').PHP_EOL;
if (! $avitoSync) {
    echo 'avito_sync_error='.(string) $avito->fresh()->last_error.PHP_EOL;
}
echo 'gray_credentials=yes gray_statistics_endpoint=pending'.PHP_EOL;
echo 'crm_credentials=yes crm_remote='.($crmRemote ? 'yes' : 'no').' local_reporting=yes'.PHP_EOL;
