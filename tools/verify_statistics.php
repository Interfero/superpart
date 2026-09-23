<?php

use App\Http\Controllers\StatisticsController;
use App\Http\Middleware\EnsureDeveloper;
use App\Models\ReferenceSource;
use App\Models\Source;
use App\Models\StatisticsIntegration;
use App\Models\StatisticsCrmSetting;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ViewErrorBag;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
View::share('errors', new ViewErrorBag());

$assert = static function (bool $condition, string $message): void {
    if (! $condition) {
        throw new RuntimeException($message);
    }
};

$assert(Schema::hasTable('statistics_integrations'), 'Нет таблицы integrations.');
$assert(Schema::hasTable('statistics_snapshots'), 'Нет таблицы snapshots.');
$assert(Schema::hasTable('statistics_crm_settings'), 'Нет таблицы CRM settings.');

$middleware = app(EnsureDeveloper::class);
$developer = new User(['role' => User::ROLE_DEVELOPER, 'name' => 'Проверка']);
$developer->id = 0;
Auth::setUser($developer);
$developerRequest = Request::create('/statistics');
$developerRequest->setUserResolver(fn () => $developer);
$response = $middleware->handle($developerRequest, fn () => response('ok', 200));
$assert($response->getStatusCode() === 200, 'Разработчик не прошел middleware.');

$otherRole = new User(['role' => User::ROLE_GENERAL_DIRECTOR]);
$otherRequest = Request::create('/statistics');
$otherRequest->setUserResolver(fn () => $otherRole);
$blocked = false;
try {
    $middleware->handle($otherRequest, fn () => response('unexpected', 200));
} catch (HttpExceptionInterface $e) {
    $blocked = $e->getStatusCode() === 403;
}
$assert($blocked, 'Другая роль не была заблокирована.');

DB::beginTransaction();
try {
    $sourceName = ReferenceSource::query()->value('name')
        ?? Source::withTrashed()->value('name')
        ?? 'Проверочный источник';
    $sentinel = 'statistics-encryption-check-'.bin2hex(random_bytes(8));

    $integration = StatisticsIntegration::query()->create([
        'provider' => StatisticsIntegration::PROVIDER_AVITO,
        'name' => 'Проверочное подключение',
        'source_name' => $sourceName,
        'credentials' => [
            'client_id' => $sentinel,
            'client_secret' => strrev($sentinel),
        ],
        'enabled' => false,
    ]);

    $raw = (string) DB::table('statistics_integrations')->where('id', $integration->id)->value('credentials');
    $assert(! str_contains($raw, $sentinel), 'Секрет записан в БД открытым текстом.');
    $assert($integration->fresh()->credentials['client_id'] === $sentinel, 'Зашифрованное поле не расшифровывается.');

    $crmSetting = StatisticsCrmSetting::query()->create([
        'base_url' => 'https://example.test',
        'api_path_prefix' => 'api/v1',
        'credentials' => ['api_key' => $sentinel, 'api_secret' => strrev($sentinel)],
        'enabled' => false,
    ]);
    $crmRaw = (string) DB::table('statistics_crm_settings')->where('id', $crmSetting->id)->value('credentials');
    $assert(! str_contains($crmRaw, $sentinel), 'Секрет CRM записан в БД открытым текстом.');
    $assert($crmSetting->fresh()->credentials['api_key'] === $sentinel, 'Зашифрованный CRM API key не расшифровывается.');

    $request = Request::create('/statistics', 'GET', [
        'date_from' => now()->startOfMonth()->toDateString(),
        'date_to' => now()->toDateString(),
    ]);
    $request->setUserResolver(fn () => $developer);
    $html = app(StatisticsController::class)->index($request)->render();
    $assert(str_contains($html, 'Стоимость заявки'), 'Страница статистики не отрисована полностью.');
    $assert(! str_contains($html, $sentinel), 'Секрет попал в HTML.');

    DB::rollBack();
} catch (Throwable $e) {
    DB::rollBack();
    throw $e;
}

echo "OK tables\n";
echo "OK developer-only\n";
echo "OK encrypted credentials\n";
echo "OK rendered statistics page\n";

$realDeveloper = User::query()->where('role', User::ROLE_DEVELOPER)->firstOrFail();
Auth::setUser($realDeveloper);
$httpRequest = Request::create('/statistics?'.http_build_query([
    'date_from' => now()->startOfMonth()->toDateString(),
    'date_to' => now()->toDateString(),
]), 'GET');
$httpKernel = $app->make(HttpKernel::class);
$httpResponse = $httpKernel->handle($httpRequest);
$assert($httpResponse->getStatusCode() === 200, 'HTTP-конвейер не вернул страницу разработчику.');
$assert(str_contains((string) $httpResponse->getContent(), 'Сквозные показатели'), 'HTTP-ответ не содержит страницу статистики.');
$httpKernel->terminate($httpRequest, $httpResponse);
echo "OK authenticated HTTP pipeline\n";
