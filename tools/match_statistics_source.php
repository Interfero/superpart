<?php

use App\Models\ReferenceSource;
use App\Models\Source;
use App\Models\StatisticsIntegration;
use Illuminate\Contracts\Console\Kernel;

chdir(dirname(__DIR__));
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$avito = StatisticsIntegration::query()->where('provider', StatisticsIntegration::PROVIDER_AVITO)->firstOrFail();
$needle = mb_strtolower(trim((string) $avito->source_name));
$normalize = static fn (string $value): string => preg_replace('/[^\pL\pN]+/u', '', mb_strtolower(trim($value))) ?? '';
$needleNormalized = $normalize($needle);

$candidates = ReferenceSource::query()->get(['id', 'name'])->map(fn ($row) => ['kind' => 'reference', 'id' => $row->id, 'name' => $row->name])
    ->merge(Source::withTrashed()->get(['id', 'name'])->map(fn ($row) => ['kind' => 'local', 'id' => $row->id, 'name' => $row->name]))
    ->map(function (array $row) use ($normalize, $needleNormalized): array {
        $candidate = $normalize((string) $row['name']);
        similar_text($needleNormalized, $candidate, $similarity);
        $contains = $candidate !== '' && ($needleNormalized === $candidate || str_contains($candidate, $needleNormalized) || str_contains($needleNormalized, $candidate));
        return $row + ['score' => (int) round($similarity), 'contains' => $contains, 'length' => mb_strlen($candidate), 'normalized' => $candidate];
    })
    ->sortByDesc(fn (array $row) => ($row['contains'] ? 1000 : 0) + $row['score'])
    ->values();

foreach ($candidates->take(5) as $candidate) {
    echo implode(' ', [
        'kind='.$candidate['kind'],
        'id='.$candidate['id'],
        'score='.$candidate['score'],
        'contains='.($candidate['contains'] ? 'yes' : 'no'),
        'length='.$candidate['length'],
    ]).PHP_EOL;
}

$best = $candidates->first();
$second = $candidates->get(1);
$sameTopName = $best && $second && $best['normalized'] === $second['normalized'];
$safeUniqueMatch = $best
    && ($best['contains'] || $best['score'] >= 82)
    && (! $second || $sameTopName || $best['score'] >= $second['score'] + 8 || ($best['contains'] && ! $second['contains']));

if ($safeUniqueMatch) {
    StatisticsIntegration::query()->where('provider', StatisticsIntegration::PROVIDER_AVITO)
        ->update(['source_name' => $best['name'], 'name' => $best['name']]);
    StatisticsIntegration::query()->where('provider', StatisticsIntegration::PROVIDER_GRAY_API)
        ->update(['source_name' => $best['name'], 'name' => 'GrayAPI · '.$best['name']]);
}

echo 'automatic_match='.($safeUniqueMatch ? 'yes' : 'no').PHP_EOL;
