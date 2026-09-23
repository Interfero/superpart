<?php

namespace App\Services;

use App\Models\City;
use App\Models\Order;
use App\Models\PortalNotification;
use App\Models\ReferenceSource;
use App\Models\User;
use App\Support\CrmOrderStatusMapper;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class LevelionApiService
{
    private const STATUS_LABELS = [
        'in_work' => 'В работе',
        'clarification' => 'На уточнении',
        'not_processed' => 'Не оформлена',
        'waiting' => 'Ожидает',
        'waiting_payment' => 'Ожидает выплаты',
        'refusal' => 'Отказ',
        'refusal_non_profile' => 'Отказ Непрофиль',
        'cancelled' => 'Отмена',
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

    public function isConfigured(): bool
    {
        return $this->baseUrl() !== ''
            && config('services.levelion.api_key') !== ''
            && config('services.levelion.api_secret') !== '';
    }

    public function syncCitiesFromLevelion(): bool
    {
        if (! $this->isConfigured()) {
            return false;
        }

        try {
            $response = Http::timeout(30)
                ->withHeaders($this->headers(''))
                ->get($this->crmApiUrl('reference/cities'));

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

                $name = (string) ($row['city_name'] ?? '');
                $tz = $row['city_timezone'] ?? $row['timezone'] ?? null;
                $tz = is_string($tz) && $tz !== '' ? $tz : null;

                City::updateOrCreate(
                    ['levelion_city_id' => $cityId],
                    [
                        'name' => $name !== '' ? $name : ('Город #'.$cityId),
                        'timezone' => $tz,
                        'is_available' => true,
                        'load_percentage' => 0,
                    ]
                );
            }

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
            $response = Http::timeout(30)
                ->withHeaders($this->headers(''))
                ->get($this->crmApiUrl('reference/sources'));

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
            $body = json_encode(
                ['superpart_partner_id' => $superpartPartnerId],
                JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
            );

            $response = Http::timeout(30)
                ->withHeaders(array_merge($this->headers($body), [
                    'Content-Type' => 'application/json',
                ]))
                ->withBody($body, 'application/json')
                ->patch($this->crmApiUrl('reference/sources/'.$crmSourceId));

            if (! $response->successful()) {
                return ['ok' => false, 'error' => $this->formatPartnerOrderErrorHint($response->status(), $response->body())];
            }

            return ['ok' => true, 'error' => null];
        } catch (\Throwable $e) {
            Log::error('LevelionApiService: patchReferenceSource', ['error' => $e->getMessage()]);

            return ['ok' => false, 'error' => 'Ошибка запроса: '.$e->getMessage()];
        }
    }

    public function getEquipmentTypesCached(): array
    {
        return Cache::remember('levelion_equipment_types_v1', 3600, function () {
            if (! $this->isConfigured()) {
                return [];
            }

            try {
                $response = Http::timeout(30)
                    ->withHeaders($this->headers(''))
                    ->get($this->crmApiUrl('reference/work-types'));

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
            'datetime_order' => $order->order_time->format('Y-m-d H:i:s'),
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

            $payload['source_id'] = (int) $order->referenceSource->levelion_source_id;
        }

        $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        try {
            $response = Http::timeout(30)
                ->withHeaders(array_merge($this->headers($body), [
                    'Content-Type' => 'application/json',
                    'Idempotency-Key' => $idempotencyKey,
                ]))
                ->withBody($body, 'application/json')
                ->post($this->crmApiUrl('partner-orders'));

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

    public function fetchPartnerOrderFromCrm(int $levelionOrderId): ?array
    {
        if (! $this->isConfigured()) {
            return null;
        }

        try {
            $response = Http::timeout(20)
                ->withHeaders($this->headers(''))
                ->get($this->crmApiUrl('partner-orders/'.$levelionOrderId));

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
            Log::error('LevelionApiService: fetchPartnerOrderFromCrm', [
                'order_id' => $levelionOrderId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    public function syncOrderStatusFromCrmIfConfigured(Order $order): void
    {
        if (! $order->levelion_order_id || ! $this->isConfigured()) {
            return;
        }

        $data = $this->fetchPartnerOrderFromCrm((int) $order->levelion_order_id);
        if ($data === null) {
            return;
        }

        $crmStatus = $data['order_status'] ?? null;
        if (! is_string($crmStatus) || $crmStatus === '') {
            return;
        }

        if ($order->status === 'waiting_payment' && $order->charge_amount !== null && (float) $order->charge_amount > 0) {
            return;
        }

        $charge = $order->charge_amount !== null ? (float) $order->charge_amount : null;

        if ($crmStatus === 'completed') {
            $newStatus = CrmOrderStatusMapper::fromCrmCompleted($charge);
        } elseif (CrmOrderStatusMapper::mapsFromCrm($crmStatus)) {
            $newStatus = CrmOrderStatusMapper::toPortal($crmStatus, (bool) $order->is_non_profile);
        } else {
            return;
        }

        if ($newStatus === $order->status) {
            if (! empty($data['order_closed_at']) && $order->closed_local === null) {
                try {
                    $order->closed_local = Carbon::parse((string) $data['order_closed_at']);
                    $order->save();
                } catch (\Throwable) {
                    // ignore
                }
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
                ->where('role', User::ROLE_GENERAL_DIRECTOR)
                ->get()
                ->each(fn (User $director) => $recipients->push($director));

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
            return false;
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