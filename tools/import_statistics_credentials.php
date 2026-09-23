<?php

use App\Models\ReferenceSource;
use App\Models\Source;
use App\Models\StatisticsCrmSetting;
use App\Models\StatisticsIntegration;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

if ($argc !== 2) {
    fwrite(STDERR, "usage: import_statistics_credentials.php PAYLOAD.json\n");
    exit(2);
}

$payloadPath = $argv[1];
$raw = file_get_contents($payloadPath);
$payload = is_string($raw) ? json_decode($raw, true, 32, JSON_THROW_ON_ERROR) : null;
if (! is_array($payload)) {
    throw new RuntimeException('Invalid import payload.');
}

chdir(dirname(__DIR__));
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$required = [
    'avito.client_id', 'avito.client_secret',
    'grayapi.auth_url', 'grayapi.api_key',
    'crm.base_url', 'crm.api_login', 'crm.api_password',
];
foreach ($required as $key) {
    if (trim((string) data_get($payload, $key, '')) === '') {
        throw new RuntimeException('Required secret group is incomplete.');
    }
}

$label = trim((string) data_get($payload, 'avito.account_label', ''));
$sourceName = $label;
$normalized = mb_strtolower($sourceName);
$sourceExists = $sourceName !== '' && (
    ReferenceSource::query()->whereRaw('LOWER(TRIM(name)) = ?', [$normalized])->exists()
    || Source::withTrashed()->whereRaw('LOWER(TRIM(name)) = ?', [$normalized])->exists()
);

DB::transaction(function () use ($payload, $label, $sourceName): void {
    StatisticsIntegration::query()->updateOrCreate(
        ['provider' => StatisticsIntegration::PROVIDER_AVITO, 'name' => $label !== '' ? $label : 'Avito API'],
        [
            'source_name' => $sourceName,
            'base_url' => null,
            'credentials' => [
                'client_id' => (string) data_get($payload, 'avito.client_id'),
                'client_secret' => (string) data_get($payload, 'avito.client_secret'),
            ],
            'settings' => null,
            'enabled' => true,
            'last_error' => null,
        ]
    );

    StatisticsIntegration::query()->updateOrCreate(
        ['provider' => StatisticsIntegration::PROVIDER_GRAY_API, 'name' => $label !== '' ? 'GrayAPI · '.$label : 'GrayAPI'],
        [
            'source_name' => $sourceName,
            'base_url' => null,
            'credentials' => ['api_key' => (string) data_get($payload, 'grayapi.api_key')],
            'settings' => [
                'auth_url' => (string) data_get($payload, 'grayapi.auth_url'),
                'auth_type' => 'bearer',
                'date_from_parameter' => 'date_from',
                'date_to_parameter' => 'date_to',
                'impressions_path' => 'data.impressions',
                'views_path' => 'data.views',
                'contacts_path' => 'data.contacts',
                'spend_path' => 'data.spend',
                'spend_divisor' => 1,
            ],
            // Метрики нельзя запрашивать, пока GrayAPI не выдаст отдельный endpoint.
            'enabled' => false,
            'last_error' => 'Токен сохранен. Для загрузки статистики требуется URL метода статистики из документации GrayAPI.',
        ]
    );

    StatisticsCrmSetting::query()->updateOrCreate(
        ['id' => StatisticsCrmSetting::query()->value('id') ?? 1],
        [
            'base_url' => rtrim((string) data_get($payload, 'crm.base_url'), '/'),
            'api_path_prefix' => 'api/v1',
            'credentials' => [
                'auth_type' => 'login_password',
                'api_login' => (string) data_get($payload, 'crm.api_login'),
                'api_password' => (string) data_get($payload, 'crm.api_password'),
                'cabinet_login' => (string) data_get($payload, 'crm.cabinet_login', ''),
                'cabinet_password' => (string) data_get($payload, 'crm.cabinet_password', ''),
            ],
            'enabled' => true,
            'last_error' => null,
        ]
    );
});

$rawRows = DB::table('statistics_integrations')->pluck('credentials')->all();
$rawCrm = (string) DB::table('statistics_crm_settings')->value('credentials');
$plainValues = [
    data_get($payload, 'avito.client_id'), data_get($payload, 'avito.client_secret'),
    data_get($payload, 'grayapi.api_key'), data_get($payload, 'crm.api_login'),
    data_get($payload, 'crm.api_password'),
];
$encrypted = true;
foreach ($plainValues as $value) {
    if ($value !== '' && (str_contains(implode('', $rawRows), (string) $value) || str_contains($rawCrm, (string) $value))) {
        $encrypted = false;
    }
}

echo 'Import complete: avito=yes grayapi=yes crm=yes source_match='.( $sourceExists ? 'yes' : 'no' ).' encrypted='.( $encrypted ? 'yes' : 'no' ).PHP_EOL;
