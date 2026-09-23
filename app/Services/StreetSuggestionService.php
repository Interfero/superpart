<?php

namespace App\Services;

use Illuminate\Http\Client\Pool;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class StreetSuggestionService
{
    private const CACHE_TTL_SECONDS = 86400;

    private const CITY_COORDS_TTL_SECONDS = 604800;

    private const PHOTON_TIMEOUT_SECONDS = 1.5;

    /** @var array<string, string> */
    private const PREFIX_SHORT = [
        'улица' => 'ул.',
        'ул.' => 'ул.',
        'ул' => 'ул.',
        'проспект' => 'пр.',
        'пр-кт' => 'пр.',
        'пр.' => 'пр.',
        'пр-т' => 'пр.',
        'переулок' => 'пер.',
        'пер.' => 'пер.',
        'пер' => 'пер.',
        'бульвар' => 'б-р',
        'б-р' => 'б-р',
        'бул.' => 'б-р',
        'шоссе' => 'ш.',
        'ш.' => 'ш.',
        'набережная' => 'наб.',
        'наб.' => 'наб.',
        'площадь' => 'пл.',
        'пл.' => 'пл.',
        'проезд' => 'пр-д',
        'аллея' => 'ал.',
        'линия' => 'лин.',
        'тупик' => 'туп.',
        'микрорайон' => 'мкр.',
        'мкр.' => 'мкр.',
        'мкр' => 'мкр.',
    ];

    /**
     * @return list<array{label: string, value: string}>
     */
    public function suggest(string $cityName, string $query, int $limit = 12): array
    {
        $cityName = $this->normalizeCityName($cityName);
        $query = trim($query);

        if ($cityName === '' || mb_strlen($query) < 2) {
            return [];
        }

        $limit = max(1, min($limit, 20));
        $cacheKey = 'street_suggest:v5:'.md5(mb_strtolower($cityName.'|'.$query.'|'.$limit));

        return Cache::remember($cacheKey, self::CACHE_TTL_SECONDS, function () use ($cityName, $query, $limit) {
            $coords = $this->resolveCityCoordinates($cityName);
            $rows = $this->fetchFromPhoton($cityName, $query, $coords);

            return $this->extractStreets($rows, $query, $limit);
        });
    }

    /**
     * @return array{lat: float, lon: float}|null
     */
    private function resolveCityCoordinates(string $cityName): ?array
    {
        $cacheKey = 'city_coords:v1:'.md5(mb_strtolower($cityName));

        return Cache::remember($cacheKey, self::CITY_COORDS_TTL_SECONDS, function () use ($cityName) {
            try {
                $response = Http::connectTimeout(1)
                    ->timeout(self::PHOTON_TIMEOUT_SECONDS)
                    ->get('https://photon.komoot.io/api/', [
                        'q' => $cityName.', Россия',
                        'limit' => 8,
                    ]);

                if (! $response->successful()) {
                    return null;
                }

                $features = $response->json('features');
                if (! is_array($features)) {
                    return null;
                }

                $cityLower = mb_strtolower($cityName);

                foreach ($features as $feature) {
                    if (! is_array($feature)) {
                        continue;
                    }

                    $properties = $feature['properties'] ?? [];
                    if (! is_array($properties)) {
                        continue;
                    }

                    $name = mb_strtolower(trim((string) ($properties['name'] ?? '')));
                    $featureCity = mb_strtolower(trim((string) (
                        $properties['city']
                        ?? $properties['town']
                        ?? $properties['name']
                        ?? ''
                    )));

                    $isPlace = in_array($properties['type'] ?? '', ['city', 'town', 'village', 'district'], true)
                        || in_array($properties['osm_value'] ?? '', ['city', 'town', 'village'], true);

                    if (! $isPlace) {
                        continue;
                    }

                    if ($name !== $cityLower
                        && $featureCity !== $cityLower
                        && ! str_contains($featureCity, $cityLower)
                        && ! str_contains($cityLower, $featureCity)) {
                        continue;
                    }

                    $coordinates = $feature['geometry']['coordinates'] ?? null;
                    if (! is_array($coordinates) || count($coordinates) < 2) {
                        continue;
                    }

                    return [
                        'lon' => (float) $coordinates[0],
                        'lat' => (float) $coordinates[1],
                    ];
                }
            } catch (\Throwable) {
                return null;
            }

            return null;
        });
    }

    /**
     * @param  array{lat: float, lon: float}|null  $coords
     * @return list<array<string, mixed>>
     */
    private function fetchFromPhoton(string $cityName, string $query, ?array $coords): array
    {
        try {
            $responses = Http::pool(function (Pool $pool) use ($cityName, $query, $coords) {
                $requests = [];

                $params = ['q' => $query, 'limit' => 20];
                if ($coords !== null) {
                    $params['lat'] = $coords['lat'];
                    $params['lon'] = $coords['lon'];
                }

                $requests[] = $pool->as('biased')
                    ->connectTimeout(1)
                    ->timeout(self::PHOTON_TIMEOUT_SECONDS)
                    ->get('https://photon.komoot.io/api/', $params);

                $requests[] = $pool->as('named')
                    ->connectTimeout(1)
                    ->timeout(self::PHOTON_TIMEOUT_SECONDS)
                    ->get('https://photon.komoot.io/api/', [
                        'q' => trim($query.' '.$cityName),
                        'limit' => 20,
                    ]);

                return $requests;
            });

            $rows = [];
            $cityLower = mb_strtolower($cityName);

            foreach (['biased', 'named'] as $key) {
                $response = $responses[$key] ?? null;
                if ($response === null || ! $response->successful()) {
                    continue;
                }

                $features = $response->json('features');
                if (! is_array($features)) {
                    continue;
                }

                foreach ($features as $feature) {
                    if (! is_array($feature)) {
                        continue;
                    }

                    $properties = $feature['properties'] ?? [];
                    if (! is_array($properties) || ! $this->isStreetFeature($properties)) {
                        continue;
                    }

                    if (! $this->featureMatchesCity($properties, $cityLower)) {
                        continue;
                    }

                    $road = $this->roadFromPhotonProperties($properties);
                    if ($road === null) {
                        continue;
                    }

                    $rows[] = [
                        'address' => [
                            'road' => $road,
                        ],
                    ];
                }
            }

            return $rows;
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @param  list<array<string, mixed>>  $payload
     * @return list<array{label: string, value: string}>
     */
    private function extractStreets(array $payload, string $query, int $limit): array
    {
        $found = [];

        foreach ($payload as $row) {
            $address = $row['address'] ?? [];
            if (! is_array($address)) {
                continue;
            }

            $road = $address['road'] ?? null;
            if (! is_string($road) || trim($road) === '') {
                continue;
            }

            $suggestion = $this->formatSuggestion($road);
            if ($suggestion === null || ! $this->matchesQuery($road, $suggestion['label'], $query)) {
                continue;
            }

            $found[mb_strtolower($suggestion['label'])] = $suggestion;

            if (count($found) >= $limit) {
                break;
            }
        }

        $streets = array_values($found);
        usort($streets, fn (array $a, array $b): int => strnatcasecmp($a['label'], $b['label']));

        return array_slice($streets, 0, $limit);
    }

    /**
     * @param  array<string, mixed>  $properties
     */
    private function isStreetFeature(array $properties): bool
    {
        if (! empty($properties['street'])) {
            return true;
        }

        if (($properties['osm_key'] ?? '') === 'highway') {
            return true;
        }

        if (($properties['type'] ?? '') === 'street') {
            return true;
        }

        $name = mb_strtolower(trim((string) ($properties['name'] ?? '')));

        foreach (['улица', 'ул.', 'проспект', 'пр.', 'переулок', 'бульвар', 'шоссе', 'площадь', 'набережная', 'проезд'] as $marker) {
            if (str_contains($name, $marker)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $properties
     */
    private function featureMatchesCity(array $properties, string $cityLower): bool
    {
        $featureCity = mb_strtolower(trim((string) (
            $properties['city']
            ?? $properties['town']
            ?? $properties['village']
            ?? ''
        )));

        if ($featureCity === '') {
            return true;
        }

        return str_contains($featureCity, $cityLower) || str_contains($cityLower, $featureCity);
    }

    /**
     * @param  array<string, mixed>  $properties
     */
    private function roadFromPhotonProperties(array $properties): ?string
    {
        $street = trim((string) ($properties['street'] ?? ''));
        if ($street !== '') {
            return $street;
        }

        $name = trim((string) ($properties['name'] ?? ''));
        if ($name !== '' && $this->isStreetFeature($properties)) {
            return $name;
        }

        return null;
    }

    /**
     * @return array{label: string, value: string}|null
     */
    private function formatSuggestion(string $road): ?array
    {
        $road = trim(preg_replace('/\s+/u', ' ', $road) ?? '');
        if ($road === '') {
            return null;
        }

        [$prefixShort, $name] = $this->splitStreetPrefix($road);
        if ($name === '') {
            return null;
        }

        $label = $prefixShort !== null ? trim($prefixShort.' '.$name) : $name;
        $label = mb_substr($label, 0, 30);

        return [
            'label' => $label,
            'value' => $label,
        ];
    }

    /**
     * @return array{0: ?string, 1: string}
     */
    private function splitStreetPrefix(string $road): array
    {
        $road = trim($road);
        $lower = mb_strtolower($road);

        foreach (self::PREFIX_SHORT as $prefix => $short) {
            $prefixLower = mb_strtolower($prefix);
            $withSpace = $prefixLower.' ';

            if (str_starts_with($lower, $withSpace)) {
                return [$short, trim(mb_substr($road, mb_strlen($prefix)))];
            }
        }

        return [null, $road];
    }

    private function matchesQuery(string $road, string $label, string $query): bool
    {
        $queryLower = mb_strtolower(trim($query));
        if ($queryLower === '') {
            return false;
        }

        foreach ([$road, $label, $this->stripStreetPrefix($road)] as $candidate) {
            $candidateLower = mb_strtolower(trim($candidate));
            if ($candidateLower !== '' && str_contains($candidateLower, $queryLower)) {
                return true;
            }
        }

        return false;
    }

    private function normalizeCityName(string $cityName): string
    {
        $cityName = trim(preg_replace('/\s+/u', ' ', $cityName) ?? '');

        return preg_replace('/^(г\.|город)\s+/ui', '', $cityName) ?? $cityName;
    }

    public function normalizeStreetName(string $name): string
    {
        return mb_substr($this->splitStreetPrefix(trim($name))[1], 0, 30);
    }

    private function stripStreetPrefix(string $road): string
    {
        return $this->splitStreetPrefix($road)[1];
    }
}
