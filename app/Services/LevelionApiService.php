<?php

namespace App\Services;

use App\Models\City;
use App\Models\Order;
use App\Models\PortalNotification;
use App\Models\ReferenceSource;
use App\Models\Source;
use App\Models\Transaction;
use App\Models\User;
use App\Support\CrmOrderStatusMapper;
use App\Support\PartnerChargeCalculator;
use App\Support\UserBalance;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class LevelionApiService
{
    private const STATUS_LABELS = [
        'in_work' => 'В работе',
        'in_work_sd' => 'В работе СД',
        'on_way' => 'В пути',
        'ready' => 'Готов',
        'clarification' => 'На уточнении',
        'not_processed' => 'Не оформлена',
        'waiting' => 'Ожидает',
        'waiting_payment' => 'Ожидает выплаты',
        'refusal' => 'Отказ',
        'refusal_non_profile' => 'Отказ Непрофиль',
        'cancelled' => 'Отмена',
        'warranty' => 'Гарантия',
    ];

    private function baseUrl(): string
    {
        return config('services.levelion.base_url', '');
    }

    private function crmApiUrl(string $path): string
    {
        $base = rtrim($this->baseUrl(), '/');
        $prefix = trim((string) config('services.levelion.api_path_prefix', 'api/v1'), '/');
        $path = trim($path, '/');

        return $base.'/'.$prefix.'/'.$path;
    }

    /**
     * @return list<string>
     */
    private function crmCandidateUrls(string $path): array
    {
        $path = trim($path, '/');
        $prefix = trim((string) config('services.levelion.api_path_prefix', 'api/v1'), '/');
        $bases = [
            rtrim($this->baseUrl(), '/'),
            rtrim((string) config('services.levelion.fallback_url', ''), '/'),
        ];
        $urls = [];
        foreach ($bases as $base) {
            if ($base === '') {
                continue;
            }
            $url = $base.'/'.$prefix.'/'.$path;
            if (! in_array($url, $urls, true)) {
                $urls[] = $url;
            }
        }

        return $urls;
    }

    /**
     * HTTP в CRM: повтор при 429 (запись), запасной URL при обрыве сети.
     *
     * @param  array<string, string>  $extraHeaders
     */
    private function crmSend(string $method, string $path, string $rawBody = '', int $timeout = 30, array $extraHeaders = []): \Illuminate\Http\Client\Response
    {
        $urls = $this->crmCandidateUrls($path);
        if ($urls === []) {
            throw new \RuntimeException('LEVELION_BASE_URL не задан.');
        }

        $isWrite = in_array(strtoupper($method), ['POST', 'PATCH', 'PUT', 'DELETE'], true);
        $max429 = $isWrite ? 4 : 1;
        $lastException = null;
        $lastResponse = null;

        foreach ($urls as $urlIndex => $url) {
            for ($attempt = 0; $attempt < $max429; $attempt++) {
                try {
                    $lastResponse = Http::timeout($timeout)
                        ->connectTimeout(10)
                        ->withHeaders(array_merge($this->headers($rawBody), $extraHeaders))
                        ->withBody($rawBody, 'application/json')
                        ->send($method, $url);
                } catch (\Illuminate\Http\Client\ConnectionException $e) {
                    $lastException = $e;
                    Log::warning('LevelionApiService: CRM недоступна, пробую следующий URL', [
                        'host' => parse_url($url, PHP_URL_HOST),
                        'try' => $urlIndex + 1,
                    ]);
                    break;
                }

                $status = $lastResponse->status();
                if ($status >= 500 && $urlIndex < count($urls) - 1) {
                    Log::warning('LevelionApiService: CRM 5xx, пробую следующий URL', [
                        'host' => parse_url($url, PHP_URL_HOST),
                        'status' => $status,
                        'try' => $urlIndex + 1,
                    ]);
                    break;
                }
                if ($status !== 429) {
                    return $lastResponse;
                }

                $wait = (int) $lastResponse->header('Retry-After', 2);
                $wait = min(max($wait, 1), 8);
                Log::warning('LevelionApiService: CRM 429, повтор', [
                    'path' => $path,
                    'wait' => $wait,
                    'attempt' => $attempt + 1,
                ]);
                if ($attempt < $max429 - 1) {
                    sleep($wait);
                }
            }

            if ($lastResponse && $lastResponse->status() !== 429) {
                return $lastResponse;
            }
        }

        if ($lastResponse) {
            return $lastResponse;
        }

        throw $lastException ?? new \Illuminate\Http\Client\ConnectionException('CRM недоступна');
    }

    private function sign(string $rawBody): string
    {
        return hash_hmac('sha256', $rawBody, config('services.levelion.api_secret', ''));
    }

    private function headers(string $rawBody): array
    {
        return [
            'X-API-Key' => config('services.levelion.api_key', ''),
            'X-Signature' => $this->sign($rawBody),
            'Accept' => 'application/json',
        ];
    }

    public function fetchSuperpartOrderChanges(int $cursor, int $limit = 50): ?array
    {
        if (! $this->isConfigured()) {
            return null;
        }

        $query = http_build_query([
            'cursor' => max(0, $cursor),
            'limit' => min(200, max(1, $limit)),
        ]);
        $rawBody = '';

        try {
            $response = $this->crmSend('GET', 'superpart/orders/changes?'.$query, $rawBody);

            if (! $response->successful()) {
                Log::warning('LevelionApiService: superpart/orders/changes', [
                    'status' => $response->status(),
                    'snippet' => substr($response->body(), 0, 200),
                ]);

                return null;
            }

            $data = $response->json();

            return is_array($data) ? $data : null;
        } catch (\Throwable $e) {
            Log::warning('LevelionApiService: superpart/orders/changes exception', [
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Полная постраничная выборка SP-релевантных заявок (ТЗ FR-REC-03).
     *
     * @return array<string, mixed>|null
     */
    public function fetchSuperpartOrdersFull(int $afterId, int $limit = 50): ?array
    {
        if (! $this->isConfigured()) {
            return null;
        }

        $query = http_build_query([
            'after_id' => max(0, $afterId),
            'limit' => min(100, max(1, $limit)),
        ]);
        $rawBody = '';

        try {
            $response = $this->crmSend('GET', 'superpart/orders/full?'.$query, $rawBody, 60);

            if (! $response->successful()) {
                Log::warning('LevelionApiService: superpart/orders/full', [
                    'status' => $response->status(),
                    'snippet' => substr($response->body(), 0, 200),
                ]);

                return null;
            }

            $data = $response->json();

            return is_array($data) ? $data : null;
        } catch (\Throwable $e) {
            Log::warning('LevelionApiService: superpart/orders/full exception', [
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    public function isConfigured(): bool
    {
        return $this->baseUrl() !== ''
            && config('services.levelion.api_key') !== ''
            && config('services.levelion.api_secret') !== '';
    }

    public function syncCitiesFromLevelionIfStale(int $ttlSeconds = 900): bool
    {
        if (! $this->isConfigured()) {
            return false;
        }

        if (Cache::has('levelion_sync_cities')) {
            return true;
        }

        $ok = $this->syncCitiesFromLevelion();
        if ($ok) {
            Cache::put('levelion_sync_cities', 1, $ttlSeconds);
        }

        return $ok;
    }

    public function syncReferenceSourcesFromLevelionIfStale(int $ttlSeconds = 900): bool
    {
        if (! $this->isConfigured()) {
            return false;
        }

        if (Cache::has('levelion_sync_reference_sources')) {
            return true;
        }

        $ok = $this->syncReferenceSourcesFromLevelion();
        if ($ok) {
            Cache::put('levelion_sync_reference_sources', 1, $ttlSeconds);
        }

        return $ok;
    }

    public function syncCitiesFromLevelion(): bool
    {
        if (! $this->isConfigured()) {
            return false;
        }

        try {
            $response = $this->crmSend('GET', 'reference/cities');

            if (! $response->successful()) {
                Log::warning('LevelionApiService: reference/cities ошибка', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return false;
            }

            $data = $response->json('data');
            if (! is_array($data)) {
                return false;
            }

            foreach ($data as $row) {
                $cityId = (int) ($row['city_id'] ?? 0);
                if ($cityId < 1) {
                    continue;
                }

                $name = trim((string) ($row['city_name'] ?? ''));
                $tz = $row['city_timezone'] ?? $row['timezone'] ?? null;
                $tz = is_string($tz) && $tz !== '' ? $tz : null;
                $displayName = $name !== '' ? $name : ('Город #'.$cityId);

                $city = City::query()->where('levelion_city_id', $cityId)->first();

                if (! $city && $name !== '') {
                    $city = City::query()
                        ->whereNull('levelion_city_id')
                        ->whereRaw('LOWER(TRIM(name)) = ?', [mb_strtolower($name)])
                        ->first();
                }

                if ($city) {
                    $city->update([
                        'levelion_city_id' => $cityId,
                        'name' => $displayName,
                        'timezone' => $tz ?? $city->timezone,
                        'is_available' => true,
                    ]);
                } else {
                    City::create([
                        'levelion_city_id' => $cityId,
                        'name' => $displayName,
                        'timezone' => $tz,
                        'is_available' => true,
                        'load_percentage' => 0,
                    ]);
                }
            }

            City::dedupeByName();

            return true;
        } catch (\Throwable $e) {
            Log::error('LevelionApiService: syncCities', ['error' => $e->getMessage()]);

            return false;
        }
    }

    public function syncReferenceSourcesFromLevelion(): bool
    {
        if (! $this->isConfigured()) {
            return false;
        }

        try {
            $response = $this->crmSend('GET', 'reference/sources');

            if (! $response->successful()) {
                Log::warning('LevelionApiService: reference/sources ошибка', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return false;
            }

            $data = $response->json('data');
            if (! is_array($data)) {
                return false;
            }

            $seenIds = [];
            foreach ($data as $row) {
                if (! is_array($row)) {
                    continue;
                }

                $sourceId = (int) ($row['source_id'] ?? 0);
                if ($sourceId < 1) {
                    continue;
                }

                $name = (string) ($row['source_name'] ?? '');
                $crmCityId = isset($row['city_id']) ? (int) $row['city_id'] : null;
                $cityName = isset($row['city_name']) && is_string($row['city_name']) ? $row['city_name'] : null;
                $partnerId = array_key_exists('superpart_partner_id', $row)
                    ? ($row['superpart_partner_id'] !== null ? (int) $row['superpart_partner_id'] : null)
                    : null;

                $availableForSuperpart = self::parseAvailableForSuperpartFlag($row);
                if ($availableForSuperpart) {
                    $seenIds[] = $sourceId;
                }

                $existing = ReferenceSource::query()
                    ->where('levelion_source_id', $sourceId)
                    ->first();

                if ($existing?->local_source_id !== null) {
                    // Локальные зеркала: обновляем имя/город/доступность, не трогаем ACL shared.
                    $existing->fill([
                        'name' => $name !== '' ? $name : $existing->name,
                        'crm_city_id' => $crmCityId && $crmCityId > 0 ? $crmCityId : $existing->crm_city_id,
                        'city_name' => $cityName ?? $existing->city_name,
                        'available_for_superpart' => $availableForSuperpart || (bool) $existing->available_for_superpart,
                        'synced_at' => now(),
                    ]);
                    $existing->save();

                    continue;
                }

                ReferenceSource::updateOrCreate(
                    ['levelion_source_id' => $sourceId],
                    [
                        'name' => $name !== '' ? $name : ('Источник #'.$sourceId),
                        'crm_city_id' => $crmCityId && $crmCityId > 0 ? $crmCityId : null,
                        'city_name' => $cityName,
                        'superpart_partner_id' => $partnerId,
                        'available_for_superpart' => $availableForSuperpart,
                        'synced_at' => now(),
                    ]
                );
            }

            if ($data !== []) {
                ReferenceSource::query()
                    ->whereNull('local_source_id')
                    ->whereNotNull('levelion_source_id')
                    ->whereNotIn('levelion_source_id', $seenIds !== [] ? $seenIds : [0])
                    ->update([
                        'available_for_superpart' => false,
                        'shared_with_all_partners' => false,
                        'synced_at' => now(),
                    ]);
            }

            return true;
        } catch (\Throwable $e) {
            Log::error('LevelionApiService: syncReferenceSources', ['error' => $e->getMessage()]);

            return false;
        }
    }

    public function patchReferenceSourcePartner(int $crmSourceId, ?int $superpartPartnerId): array
    {
        if (! $this->isConfigured()) {
            return ['ok' => false, 'error' => 'Интеграция не настроена (LEVELION_* в .env).'];
        }

        if ($crmSourceId < 1) {
            return ['ok' => false, 'error' => 'Некорректный идентификатор источника.'];
        }

        try {
            // Всегда включаем в каталог SuperPart — иначе CRM отвечает 422 на «Общий»/смену партнёра.
            $body = json_encode(
                [
                    'superpart_partner_id' => $superpartPartnerId,
                    'available_for_superpart' => true,
                ],
                JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
            );

            $response = $this->crmSend('PATCH', 'reference/sources/'.$crmSourceId, $body);

            if (! $response->successful()) {
                return ['ok' => false, 'error' => $this->formatPartnerOrderErrorHint($response->status(), $response->body())];
            }

            return ['ok' => true, 'error' => null];
        } catch (\Throwable $e) {
            Log::error('LevelionApiService: patchReferenceSource', ['error' => $e->getMessage()]);

            return ['ok' => false, 'error' => 'Ошибка запроса: '.$e->getMessage()];
        }
    }

    /**
     * Создать источник в CRM из карточки партнёра в SuperPart.
     *
     * @return array{crm_source_id: int|null, error: string|null}
     */
    public function pushLocalSourceToCrm(Source $source): array
    {
        if (! $this->isConfigured()) {
            return ['crm_source_id' => null, 'error' => 'Интеграция не настроена (LEVELION_* в .env).'];
        }

        try {
            $payload = [
                'source_name' => $source->name,
                // Владелец нужен CRM→SP webhook; видимость «общий» — shared_with_all_partners в SP.
                'superpart_partner_id' => (int) $source->user_id,
                'superpart_local_source_id' => (int) $source->id,
                'available_for_superpart' => true,
            ];

            $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

            $response = $this->crmSend('POST', 'reference/sources', $body);

            if (! $response->successful()) {
                Log::warning('LevelionApiService: POST reference/sources', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'local_source_id' => $source->id,
                ]);

                return [
                    'crm_source_id' => null,
                    'error' => $this->formatPartnerOrderErrorHint($response->status(), $response->body()),
                ];
            }

            $crmSourceId = (int) $response->json('source_id');

            if ($crmSourceId < 1) {
                return ['crm_source_id' => null, 'error' => 'CRM не вернула source_id.'];
            }

            return ['crm_source_id' => $crmSourceId, 'error' => null];
        } catch (\Throwable $e) {
            Log::error('LevelionApiService: pushLocalSourceToCrm', ['error' => $e->getMessage()]);

            return ['crm_source_id' => null, 'error' => 'Ошибка запроса: '.$e->getMessage()];
        }
    }

    /**
     * Обновить название источника в CRM.
     *
     * @return array{ok: bool, error: string|null}
     */
    public function updateCrmSourceFromLocal(Source $source, int $crmSourceId): array
    {
        if (! $this->isConfigured()) {
            return ['ok' => false, 'error' => 'Интеграция не настроена (LEVELION_* в .env).'];
        }

        if ($crmSourceId < 1) {
            return ['ok' => false, 'error' => 'Некорректный идентификатор источника CRM.'];
        }

        try {
            $body = json_encode(
                [
                    'source_name' => $source->name,
                    'superpart_partner_id' => (int) $source->user_id,
                    'available_for_superpart' => true,
                ],
                JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
            );

            $response = $this->crmSend('PATCH', 'reference/sources/'.$crmSourceId, $body);

            if (! $response->successful()) {
                return ['ok' => false, 'error' => $this->formatPartnerOrderErrorHint($response->status(), $response->body())];
            }

            return ['ok' => true, 'error' => null];
        } catch (\Throwable $e) {
            Log::error('LevelionApiService: updateCrmSourceFromLocal', ['error' => $e->getMessage()]);

            return ['ok' => false, 'error' => 'Ошибка запроса: '.$e->getMessage()];
        }
    }

    /**
     * Удалить источник в CRM (при удалении в SuperPart).
     *
     * @return array{ok: bool, error: string|null, deleted: bool}
     */
    /**
     * Снять источник с каталога SuperPart в CRM (не удалять карточку в диспетчерской).
     *
     * @return array{ok: bool, error: string|null}
     */
    public function revokeSuperpartCatalog(int $crmSourceId): array
    {
        if (! $this->isConfigured()) {
            return ['ok' => false, 'error' => 'Интеграция не настроена (LEVELION_* в .env).'];
        }

        if ($crmSourceId < 1) {
            return ['ok' => true, 'error' => null];
        }

        try {
            $payload = [
                'available_for_superpart' => false,
                'superpart_partner_id' => null,
            ];
            $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            $response = $this->crmSend('PATCH', 'reference/sources/'.$crmSourceId, $body, 8);

            if (! $response->successful()) {
                return ['ok' => false, 'error' => $this->formatPartnerOrderErrorHint($response->status(), $response->body())];
            }

            return ['ok' => true, 'error' => null];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => 'Ошибка запроса: '.$e->getMessage()];
        }
    }

    public function deleteCrmSource(int $crmSourceId, ?int $localSourceId = null): array
    {
        if (! $this->isConfigured()) {
            return ['ok' => false, 'error' => 'Интеграция не настроена (LEVELION_* в .env).', 'deleted' => false];
        }

        if ($localSourceId !== null && $localSourceId > 0) {
            $byLocal = $this->deleteCrmSourceRequest('reference/sources/by-local/'.$localSourceId);
            if ($byLocal['ok']) {
                return $byLocal;
            }
        }

        if ($crmSourceId < 1) {
            return ['ok' => true, 'error' => null, 'deleted' => false];
        }

        return $this->deleteCrmSourceRequest('reference/sources/'.$crmSourceId);
    }

    /**
     * @return array{ok: bool, error: string|null, deleted: bool}
     */
    private function deleteCrmSourceRequest(string $path): array
    {
        try {
            $response = $this->crmSend('DELETE', $path);

            if (! $response->successful()) {
                return [
                    'ok' => false,
                    'error' => $this->formatPartnerOrderErrorHint($response->status(), $response->body()),
                    'deleted' => false,
                ];
            }

            return [
                'ok' => true,
                'error' => null,
                'deleted' => (bool) $response->json('deleted', true),
            ];
        } catch (\Throwable $e) {
            Log::error('LevelionApiService: deleteCrmSource', ['path' => $path, 'error' => $e->getMessage()]);

            return ['ok' => false, 'error' => 'Ошибка запроса: '.$e->getMessage(), 'deleted' => false];
        }
    }

    /**
     * Отключить источник в CRM (устарело: используйте deleteCrmSource).
     *
     * @return array{ok: bool, error: string|null}
     */
    public function deactivateCrmSource(int $crmSourceId): array
    {
        $result = $this->deleteCrmSource($crmSourceId);

        return ['ok' => $result['ok'], 'error' => $result['error']];
    }

    public function getEquipmentTypesCached(): array
    {
        return Cache::remember('levelion_equipment_types_v1', 3600, function () {
            if (! $this->isConfigured()) {
                return [];
            }

            try {
                $response = $this->crmSend('GET', 'reference/work-types');

                if (! $response->successful()) {
                    return [];
                }

                $list = $response->json('equipment_types');

                return is_array($list) ? $list : [];
            } catch (\Throwable) {
                return [];
            }
        });
    }

    public function pushPartnerOrder(Order $order, string $idempotencyKey): array
    {
        if (! $this->isConfigured()) {
            return ['crm_id' => null, 'error' => 'Интеграция не настроена (LEVELION_* в .env).'];
        }

        $order->loadMissing('city', 'referenceSource');

        $lcCityId = $order->city?->levelion_city_id;
        if (! $lcCityId) {
            Log::warning('LevelionApiService: у города нет levelion_city_id', ['order_id' => $order->id]);

            return ['crm_id' => null, 'error' => 'У города нет привязки к CRM (levelion_city_id). Синхронизируйте города.'];
        }

        $payload = [
            'client_name' => $order->client_name,
            'client_phone' => self::normalizeClientPhoneForApi((string) $order->client_phone),
            'city_id' => $lcCityId,
            'street' => self::addressPartForApi($order->street, $order->address),
            'house' => self::addressPartForApi($order->house, 'б/н'),
            'flat' => $order->flat,
            'address_adds' => $order->address_adds,
            'datetime_order' => $order->order_time
                ->timezone(\App\Support\VisitDateTime::cityTimezone($order->city))
                ->format('Y-m-d H:i:s'),
            'order_adds' => $order->order_adds,
            'partner_user_id' => $order->user_id,
            'without_call' => (bool) $order->without_call,
        ];

        if (! empty($order->equipment_type)) {
            $payload['equipment_type'] = $order->equipment_type;
        } elseif (! empty($order->order_core)) {
            $payload['order_core'] = $order->order_core;
        } else {
            $payload['order_core'] = $order->is_non_profile ? 'non_core' : 'core';
        }

        if ($order->reference_source_id !== null && $order->referenceSource) {
            if (! $order->referenceSource->available_for_superpart) {
                return [
                    'crm_id' => null,
                    'error' => 'Источник не включён в каталог SuperPart. В CRM включите флаг «Показывать в каталоге SuperPart» для этого источника.',
                ];
            }

            $crmSourceId = $order->referenceSource->levelion_source_id;
            if ($crmSourceId !== null && (int) $crmSourceId > 0) {
                $payload['source_id'] = (int) $crmSourceId;
            }
        }

        $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        try {
            $response = $this->crmSend('POST', 'partner-orders', $body, 30, [
                'Idempotency-Key' => $idempotencyKey,
            ]);

            if (! $response->successful()) {
                $body = $response->body();

                Log::error('LevelionApiService: partner-orders', [
                    'status' => $response->status(),
                    'body' => $body,
                ]);

                return ['crm_id' => null, 'error' => $this->formatPartnerOrderErrorHint($response->status(), $body)];
            }

            $crmId = $response->json('order_id');

            if (is_numeric($crmId)) {
                return ['crm_id' => (int) $crmId, 'error' => null];
            }

            Log::error('LevelionApiService: partner-orders без order_id', ['body' => $response->body()]);

            return ['crm_id' => null, 'error' => 'CRM не вернула номер заявки (order_id). Проверьте ответ API в логах.'];
        } catch (\Throwable $e) {
            Log::error('LevelionApiService: pushPartnerOrder', ['error' => $e->getMessage()]);

            return ['crm_id' => null, 'error' => 'Ошибка запроса: '.$e->getMessage()];
        }
    }

    /**
     * @return array{ok: bool, error: ?string}
     */
    public function cancelPartnerOrder(int $crmOrderId): array
    {
        if (! $this->isConfigured()) {
            return ['ok' => false, 'error' => 'Интеграция не настроена.'];
        }

        if ($crmOrderId < 1) {
            return ['ok' => false, 'error' => 'Нет номера заявки CRM.'];
        }

        $body = json_encode(['order_status' => 'cancelled_cc'], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        try {
            $response = $this->crmSend('PATCH', 'partner-orders/'.$crmOrderId.'/status', $body);

            if ($response->successful()) {
                return ['ok' => true, 'error' => null];
            }

            Log::error('LevelionApiService: partner-orders status cancel', [
                'crm_id' => $crmOrderId,
                'status' => $response->status(),
            ]);

            return ['ok' => false, 'error' => 'CRM не приняла отмену (HTTP '.$response->status().').'];
        } catch (\Throwable $e) {
            Log::error('LevelionApiService: cancelPartnerOrder', ['error' => $e->getMessage()]);

            return ['ok' => false, 'error' => 'Ошибка запроса отмены в CRM.'];
        }
    }

    private function formatPartnerOrderErrorHint(int $status, string $body): string
    {
        $trimmed = trim($body);

        if ($trimmed === '') {
            $msg = 'CRM ответила HTTP '.$status.' (пустое тело).';
        } else {
            $decoded = json_decode($trimmed, true);

            if (is_array($decoded)) {
                if (isset($decoded['message']) && is_string($decoded['message'])) {
                    $msg = 'HTTP '.$status.': '.$decoded['message'];
                } elseif (isset($decoded['errors']) && is_array($decoded['errors'])) {
                    $flat = json_encode($decoded['errors'], JSON_UNESCAPED_UNICODE);
                    $msg = 'HTTP '.$status.': '.mb_substr($flat !== false ? $flat : $trimmed, 0, 400);
                } else {
                    $msg = 'HTTP '.$status.': '.mb_substr($trimmed, 0, 500);
                }
            } else {
                $msg = 'HTTP '.$status.': '.mb_substr($trimmed, 0, 500);
            }
        }

        if ($status === 404) {
            $msg .= ' На сервере CRM должен быть маршрут POST /api/v1/partner-orders.';
        }

        if ($status === 500) {
            $msg .= ' Ошибка выполнения на сервере CRM: проверьте storage/logs/laravel.log на хосте Lead Control.';
        }

        return $msg;
    }

    public static function newIdempotencyKey(): string
    {
        return (string) Str::uuid();
    }

    /**
     * Один ключ на содержимое заявки: повторная отправка не создаёт дубль в CRM.
     */
    public static function partnerOrderIdempotencyKey(Order $order): string
    {
        $dt = $order->order_time instanceof \DateTimeInterface
            ? $order->order_time->format('Y-m-d H:i:s')
            : (string) $order->order_time;

        return 'sp-po-'.hash('sha256', implode("\n", [
            (string) (int) $order->user_id,
            (string) $order->client_phone,
            $dt,
            (string) $order->street,
            (string) $order->house,
            (string) $order->flat,
            (string) $order->order_adds,
        ]));
    }

    public function fetchPartnerOrderFromCrm(int $levelionOrderId, int $timeoutSeconds = 20): ?array
    {
        if (! $this->isConfigured()) {
            return null;
        }

        if (Cache::has('levelion_crm_down')) {
            return null;
        }

        try {
            $response = $this->crmSend('GET', 'partner-orders/'.$levelionOrderId, '', $timeoutSeconds);

            if (! $response->successful()) {
                Log::warning('LevelionApiService: partner-orders/{id} ошибка', [
                    'order_id' => $levelionOrderId,
                    'status' => $response->status(),
                ]);

                return null;
            }

            $json = $response->json();

            return is_array($json) ? $json : null;
        } catch (\Throwable $e) {
            // При таймауте CRM не долбим его на каждой строке списка.
            if (str_contains($e->getMessage(), 'timed out')
                || str_contains($e->getMessage(), 'Timeout')
                || str_contains($e->getMessage(), 'Failed to connect')) {
                Cache::put('levelion_crm_down', 1, 60);
            }

            Log::error('LevelionApiService: fetchPartnerOrderFromCrm', [
                'order_id' => $levelionOrderId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    public function syncOrderStatusFromCrmIfConfigured(Order $order): void
    {
        $crmId = $order->crmOrderId();

        if (! $crmId || ! $this->isConfigured()) {
            return;
        }

        $data = $this->fetchPartnerOrderFromCrm($crmId);
        if ($data === null) {
            return;
        }

        $this->applyCrmPayloadToOrder($order, $data);
    }

    /**
     * Лёгкий синк статусов для списков: не чаще TTL, не больше $limit заявок за раз,
     * без терминальных статусов. Иначе 100 записей = 100 HTTP в CRM.
     *
     * @param  iterable<int, Order>  $orders
     */
    public function syncOrdersStatusFromCrmIfConfigured(iterable $orders, int $limit = 5, int $ttlSeconds = 180): void
    {
        if (! $this->isConfigured() || Cache::has('levelion_crm_down')) {
            return;
        }

        $skipStatuses = [
            'cancelled',
            'refusal_non_profile',
            'waiting_payment',
            'warranty',
        ];

        $synced = 0;

        foreach ($orders as $order) {
            if ($synced >= $limit) {
                break;
            }

            if (! $order instanceof Order || ! $order->crmOrderId()) {
                continue;
            }

            // refusal с нулём/без суммы мог быть ложным (CRM completed без вебхука).
            if (in_array((string) $order->status, $skipStatuses, true)) {
                continue;
            }

            if ($order->status === 'refusal' && (float) ($order->charge_amount ?? 0) > 0) {
                continue;
            }

            $cacheKey = 'crm_order_status_sync:'.$order->id;
            if (Cache::has($cacheKey)) {
                continue;
            }

            try {
                // Короткий таймаут в списках: не держать страницу по 10+ сек на заказ.
                $crmId = $order->crmOrderId();
                $data = $crmId ? $this->fetchPartnerOrderFromCrm($crmId, 3) : null;
                if ($data !== null) {
                    $this->applyCrmPayloadToOrder($order, $data);
                }
                Cache::put($cacheKey, 1, $ttlSeconds);
                $synced++;
            } catch (\Throwable $e) {
                report($e);
            }

            if (Cache::has('levelion_crm_down')) {
                break;
            }
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function applyCrmPayloadToOrder(Order $order, array $data): void
    {
        // Ответ CRM иногда {data: {...}}
        if (isset($data['data']) && is_array($data['data']) && ! isset($data['order_status'])) {
            $data = $data['data'];
        }

        // РК и карточка заявки всегда синхронизируем с CRM — даже для уже начисленных.
        $this->syncOrderSourceFromCrmPayload($order, $data);
        $this->syncOrderBodyFromCrmPayload($order, $data);

        $crmStatus = $data['order_status'] ?? null;
        if (! is_string($crmStatus) || $crmStatus === '') {
            return;
        }

        $alreadyCharged = Transaction::query()
            ->where('order_id', $order->id)
            ->where('operation_type', 'charge')
            ->exists();

        if ($order->status === 'waiting_payment' && $alreadyCharged) {
            return;
        }

        $crmCharge = PartnerChargeCalculator::fromCrmPayload($data);
        $localCharge = $order->charge_amount !== null ? (float) $order->charge_amount : null;
        $charge = $localCharge ?? $crmCharge;

        $statusKey = mb_strtolower(trim($crmStatus));

        if ($statusKey === 'completed') {
            // Проведён в CRM: начисляем 30% от чистыми, статус «ожидает выплаты».
            if ($order->type === 'warranty') {
                $newStatus = 'warranty';
            } elseif ($charge !== null && $charge > 0) {
                $newStatus = 'waiting_payment';
            } elseif ($charge !== null && $charge <= 0) {
                $newStatus = 'refusal';
            } else {
                // Нет сумм в CRM — не превращаем в ложный «Отказ».
                return;
            }

            $this->applyCompletedFromCrm($order, $data, $newStatus, $charge, $alreadyCharged);

            return;
        }

        if (! CrmOrderStatusMapper::mapsFromCrm($crmStatus)) {
            return;
        }

        $newStatus = CrmOrderStatusMapper::toPortal($crmStatus, (bool) $order->is_non_profile);
        if ($newStatus === null) {
            return;
        }

        if ($newStatus === $order->status) {
            $dirty = false;

            if (! empty($data['order_closed_at']) && $order->closed_local === null) {
                try {
                    $order->closed_local = Carbon::parse((string) $data['order_closed_at']);
                    $dirty = true;
                } catch (\Throwable) {
                    // ignore
                }
            }

            if ($dirty) {
                $order->save();
            }

            return;
        }

        $oldStatus = $order->status;
        $order->status = $newStatus;

        if (! empty($data['order_closed_at'])) {
            try {
                $order->closed_local = Carbon::parse((string) $data['order_closed_at']);
            } catch (\Throwable) {
                // keep previous
            }
        }

        $order->save();
        $this->notifyOrderStatusChanged($order, $oldStatus, $newStatus);
    }

    /**
     * CRM — источник правды по РК. Создаёт зеркало источника, если его ещё нет в каталоге SP.
     *
     * @param  array<string, mixed>  $data
     */
    public function syncOrderSourceFromCrmPayload(Order $order, array $data): bool
    {
        $crmSourceId = (int) ($data['source_id'] ?? 0);
        if ($crmSourceId < 1) {
            return false;
        }

        $name = isset($data['marketing_source_name']) && is_string($data['marketing_source_name'])
            ? $data['marketing_source_name']
            : null;

        $ref = ReferenceSource::ensureFromCrmSource($crmSourceId, $name);

        $dirty = false;
        if ((int) $order->reference_source_id !== (int) $ref->id) {
            $order->reference_source_id = (int) $ref->id;
            $order->source_id = null;
            $dirty = true;
        }

        $crmOrderId = (int) ($data['order_id'] ?? 0);
        if ($crmOrderId > 0 && (int) ($order->levelion_order_id ?? 0) !== $crmOrderId) {
            $order->levelion_order_id = $crmOrderId;
            $dirty = true;
        }

        if ($dirty) {
            $order->save();
        }

        return $dirty;
    }

    /**
     * Подтянуть карточку заявки из CRM (клиент/адрес/комментарий), чтобы тестовые «заглушки»
     * не оставались поверх реального CRM-заказа с тем же id.
     *
     * @param  array<string, mixed>  $data
     */
    public function syncOrderBodyFromCrmPayload(Order $order, array $data): bool
    {
        $dirty = false;

        $clientName = isset($data['client_name']) && is_string($data['client_name'])
            ? trim($data['client_name'])
            : '';
        if ($clientName !== '' && $clientName !== (string) $order->client_name) {
            $order->client_name = $clientName;
            $dirty = true;
        }

        $phone = '';
        if (isset($data['client_phone']) && is_string($data['client_phone'])) {
            $phone = trim($data['client_phone']);
        } elseif (isset($data['phone']) && is_string($data['phone'])) {
            $phone = trim($data['phone']);
        }
        if ($phone !== '' && $phone !== (string) $order->client_phone) {
            $order->client_phone = $phone;
            $dirty = true;
        }

        $street = isset($data['street']) && is_string($data['street']) ? trim($data['street']) : '';
        $house = isset($data['house']) && is_string($data['house']) ? trim($data['house']) : '';
        $flat = array_key_exists('flat', $data) ? $data['flat'] : $order->flat;
        $addressAdds = array_key_exists('address_adds', $data) ? $data['address_adds'] : $order->address_adds;

        if ($street !== '' && $street !== (string) $order->street) {
            $order->street = $street;
            $dirty = true;
        }
        if ($house !== '' && $house !== (string) $order->house) {
            $order->house = $house;
            $dirty = true;
        }
        if ((string) ($flat ?? '') !== (string) ($order->flat ?? '')) {
            $order->flat = $flat;
            $dirty = true;
        }
        if ((string) ($addressAdds ?? '') !== (string) ($order->address_adds ?? '')) {
            $order->address_adds = $addressAdds;
            $dirty = true;
        }

        $addressLine = trim(implode(' ', array_filter([
            $order->street,
            $order->house !== null && $order->house !== '' ? 'д. '.$order->house : '',
            $order->flat !== null && $order->flat !== '' ? 'кв. '.$order->flat : '',
        ])));
        if ($addressLine !== '' && $addressLine !== (string) $order->address) {
            $order->address = $addressLine;
            $dirty = true;
        }

        $orderAdds = '';
        if (isset($data['order_adds']) && is_string($data['order_adds'])) {
            $orderAdds = $data['order_adds'];
        } elseif (isset($data['description']) && is_string($data['description'])) {
            $orderAdds = $data['description'];
        }
        if ($orderAdds !== '' && $orderAdds !== (string) $order->order_adds) {
            $order->order_adds = $orderAdds;
            $dirty = true;
        }

        if (! empty($data['city_id'])) {
            $cityId = City::query()->where('levelion_city_id', (int) $data['city_id'])->value('id');
            if ($cityId && (int) $order->city_id !== (int) $cityId) {
                $order->city_id = (int) $cityId;
                $dirty = true;
            }
        }

        if (! empty($data['equipment_type']) && is_string($data['equipment_type'])
            && $data['equipment_type'] !== (string) $order->equipment_type) {
            $order->equipment_type = $data['equipment_type'];
            $dirty = true;
        }

        if (! empty($data['order_core']) && is_string($data['order_core'])) {
            if ($data['order_core'] !== (string) $order->order_core) {
                $order->order_core = $data['order_core'];
                $dirty = true;
            }
            $nonProfile = $data['order_core'] === 'non_core';
            if ((bool) $order->is_non_profile !== $nonProfile) {
                $order->is_non_profile = $nonProfile;
                $dirty = true;
            }
        }

        if (! empty($data['datetime_order'])) {
            try {
                $dt = Carbon::parse((string) $data['datetime_order']);
                if ($order->order_time === null || ! $order->order_time->equalTo($dt)) {
                    $order->order_time = $dt;
                    $dirty = true;
                }
            } catch (\Throwable) {
                // ignore
            }
        }

        // Стираем след неудачного тестового пуша, если CRM уже знает заявку.
        if ((string) $order->sync_status === 'error' && ! empty($data['order_id'])) {
            $order->sync_status = 'synced';
            $order->sync_last_error = null;
            $dirty = true;
        }

        if ($dirty) {
            $order->save();
        }

        return $dirty;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function applyCompletedFromCrm(
        Order $order,
        array $data,
        string $newStatus,
        ?float $charge,
        bool $alreadyCharged
    ): void {
        $closedAt = null;
        if (! empty($data['order_closed_at'])) {
            try {
                $closedAt = Carbon::parse((string) $data['order_closed_at']);
            } catch (\Throwable) {
                $closedAt = now();
            }
        } elseif (! empty($data['completed_at'])) {
            try {
                $closedAt = Carbon::parse((string) $data['completed_at']);
            } catch (\Throwable) {
                $closedAt = now();
            }
        }

        $oldStatus = $order->status;

        if ($newStatus === 'warranty') {
            $order->status = 'warranty';
            $order->charge_amount = null;
            if ($closedAt) {
                $order->closed_local = $closedAt;
            }
            $order->save();

            if ($oldStatus !== $newStatus) {
                $this->notifyOrderStatusChanged($order, $oldStatus, $newStatus);
            }

            return;
        }

        if ($newStatus === 'waiting_payment' && $charge !== null && $charge > 0) {
            if (! $alreadyCharged) {
                $user = User::query()->find($order->user_id);
                if ($user) {
                    DB::transaction(function () use ($order, $user, $charge, $closedAt, $newStatus) {
                        UserBalance::applyCharge($user, (int) $order->id, $charge, $closedAt ?? now());
                        $order->status = $newStatus;
                        $order->charge_amount = $charge;
                        $order->closed_local = $closedAt ?? ($order->closed_local ?? now());
                        $order->save();
                    });
                }
            } else {
                $order->status = $newStatus;
                if ($order->charge_amount === null) {
                    $order->charge_amount = $charge;
                }
                if ($order->closed_local === null) {
                    $order->closed_local = $closedAt ?? now();
                }
                $order->save();
            }

            if ($oldStatus !== $newStatus) {
                $order->refresh();
                $this->notifyOrderStatusChanged($order, $oldStatus, $newStatus);
            }

            return;
        }

        // completed с нулевой чистой суммой → отказ
        $order->status = 'refusal';
        $order->charge_amount = null;
        if ($closedAt) {
            $order->closed_local = $closedAt;
        }
        $order->save();

        if ($oldStatus !== 'refusal') {
            $this->notifyOrderStatusChanged($order, $oldStatus, 'refusal');
        }
    }

    private function notifyOrderStatusChanged(Order $order, ?string $oldStatus, string $newStatus): void
    {
        try {
            $recipients = collect();

            $owner = User::query()->find($order->user_id);
            if ($owner) {
                $recipients->push($owner);

                if ($owner->isManager() && $owner->parent_user_id) {
                    $parent = User::query()->find($owner->parent_user_id);
                    if ($parent) {
                        $recipients->push($parent);
                    }
                }
            }

            User::query()
                ->portalAdmins()
                ->get()
                ->each(fn (User $admin) => $recipients->push($admin));

            $oldLabel = self::STATUS_LABELS[$oldStatus] ?? ($oldStatus ?: '—');
            $newLabel = self::STATUS_LABELS[$newStatus] ?? $newStatus;

            $recipients
                ->unique('id')
                ->each(function (User $recipient) use ($order, $oldLabel, $newLabel) {
                    PortalNotification::create([
                        'user_id' => $recipient->id,
                        'type' => 'order_status_changed',
                        'title' => 'Изменение статуса заявки',
                        'message' => 'Заявка #'.$order->id.' изменила статус: '.$oldLabel.' → '.$newLabel.'.',
                        'url' => route('orders.show', $order->id),
                    ]);
                });
        } catch (\Throwable $e) {
            Log::warning('LevelionApiService: notifyOrderStatusChanged ошибка', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private static function parseAvailableForSuperpartFlag(array $row): bool
    {
        if (! array_key_exists('available_for_superpart', $row)) {
            // Справочник reference/sources — источники уже отобраны для SuperPart.
            return true;
        }

        return filter_var($row['available_for_superpart'], FILTER_VALIDATE_BOOLEAN);
    }

    private static function addressPartForApi(?string $value, ?string $fallback): string
    {
        $value = trim((string) $value);

        if ($value !== '') {
            return $value;
        }

        $fallback = trim((string) $fallback);

        return $fallback !== '' ? $fallback : '—';
    }

    public static function normalizeClientPhoneForApi(string $phone): string
    {
        $digits = preg_replace('/\D/', '', $phone) ?? '';

        if (strlen($digits) === 10) {
            return '+7'.$digits;
        }

        if (strlen($digits) === 11 && ($digits[0] === '7' || $digits[0] === '8')) {
            return '+7'.substr($digits, 1);
        }

        return $phone;
    }
}