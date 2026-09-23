<?php

namespace App\Models;

use App\Support\OrderCrmIdSync;
use App\Support\OrderSourceFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Schema;

class Order extends Model
{
    protected $fillable = [
        'user_id',
        'city_id',
        'server_type',
        'status',
        'type',
        'source_id',
        'reference_source_id',
        'work_type_id',
        'order_time',
        'client_name',
        'client_phone',
        'client_age',
        'without_call',
        'is_non_profile',
        'settlement',
        'address',
        'street',
        'house',
        'flat',
        'address_adds',
        'order_adds',
        'employee_id',
        'charge_amount',
        'created_local',
        'closed_local',
        'levelion_order_id',
        'sync_status',
        'sync_last_error',
        'crm_sync_version',
        'crm_checksum',
        'is_superpart_eligible',
        'excluded_at',
        'exclusion_reason',
        'expected_reward',
        'crm_source_id',
        'equipment_type',
        'order_core',
    ];

    protected static function booted(): void
    {
        static::creating(function (Order $order) {
            if ((int) ($order->getKey() ?? 0) > 0) {
                $order->incrementing = false;

                return;
            }

            // После parked-ID MySQL больше не выдаёт живые номера — задаём сами.
            $order->id = OrderCrmIdSync::nextLiveId();
            $order->incrementing = false;
        });
    }

    protected function casts(): array
    {
        return [
            'client_age' => 'integer',
            'order_time' => 'datetime',
            'without_call' => 'boolean',
            'is_non_profile' => 'boolean',
            'charge_amount' => 'decimal:2',
            'created_local' => 'datetime',
            'closed_local' => 'datetime',
            'crm_sync_version' => 'integer',
            'is_superpart_eligible' => 'boolean',
            'excluded_at' => 'datetime',
            'expected_reward' => 'integer',
            'crm_source_id' => 'integer',
        ];
    }

    public static function serverTypeLabels(): array
    {
        return [
            'computer_help' => 'Комп. помощь',
            'appliance_repair' => 'Ремонт бытовой техники',
            'handyman' => 'Муж на час',
        ];
    }

    public function serverTypeLabel(): string
    {
        return self::serverTypeLabels()[$this->server_type] ?? ($this->server_type ?: '—');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(Source::class)->withTrashed();
    }

    public function referenceSource(): BelongsTo
    {
        return $this->belongsTo(ReferenceSource::class, 'reference_source_id');
    }

    public function workType(): BelongsTo
    {
        return $this->belongsTo(WorkType::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function scopeWorkingVisible(Builder $query): void
    {
        if (Schema::hasColumn('orders', 'excluded_at')) {
            $query->whereNull('excluded_at');
        }
    }

    public function scopeForPortalUser(Builder $query, User $user, bool $applyManagerWindow = true): void
    {
        $query->workingVisible();

        if (! $user->hasElevatedAccess()) {
            $sourceIds = \App\Support\PartnerSourceAccess::accessibleSourceIdsFor($user);

            if (\App\Support\PartnerSourceAccess::usesCrmSources()) {
                if ($sourceIds->isEmpty()) {
                    $query->whereRaw('0 = 1');
                } else {
                    $query->whereIn('reference_source_id', $sourceIds->all());
                }
            } else {
                if ($sourceIds->isEmpty()) {
                    $query->whereRaw('0 = 1');
                } else {
                    $query->whereIn('source_id', $sourceIds->all());
                }
            }
        }

        // В списках на главной — только неделя; в отчёте по заявкам окно не режем.
        if ($applyManagerWindow && $user->isManager()) {
            $query->where(function (Builder $q) {
                $q->where('created_local', '>=', now()->subDays(7))
                    ->orWhereNull('created_local');
            });
        }
    }

    public function isParked(): bool
    {
        return OrderCrmIdSync::isParkedId((int) ($this->id ?? 0));
    }

    /** Номер для экрана: черновик не занимает боевой ID CRM. */
    public function displayNumber(): string
    {
        if ($this->isParked()) {
            return 'черновик #'.((int) $this->id - OrderCrmIdSync::PARKED_ID_MIN);
        }

        return (string) $this->id;
    }

    /** ID заявки в CRM. Пока заявка не ушла — null, локальный id не выдаём за CRM. */
    public function crmOrderId(): ?int
    {
        if ($this->isParked()) {
            return $this->levelion_order_id !== null ? (int) $this->levelion_order_id : null;
        }

        if ($this->levelion_order_id !== null) {
            return (int) $this->levelion_order_id;
        }

        if ((string) ($this->sync_status ?? '') === 'synced' && $this->id) {
            return (int) $this->id;
        }

        return null;
    }

    public function scopeWherePublicId(Builder $query, int $publicId): void
    {
        $query->where(function (Builder $q) use ($publicId) {
            $q->where('id', $publicId)
                ->orWhere('levelion_order_id', $publicId);
        });
    }

    /** Найти заявку, которая уже является записью CRM с этим ID — не черновик портала. */
    public function scopeWhereCrmRecord(Builder $query, int $crmId): void
    {
        $query->where(function (Builder $q) use ($crmId) {
            $q->where('levelion_order_id', $crmId)
                ->orWhere(function (Builder $q2) use ($crmId) {
                    $q2->where('id', $crmId)
                        ->where('sync_status', 'synced');
                });
        });
    }

    public function sourceDisplayName(): string
    {
        return OrderSourceFilter::displayName($this);
    }

    /** Пользователь партнёрского сервиса, создавший заявку. */
    public function creatorDisplayName(): string
    {
        return $this->user?->displayFullName() ?? '';
    }
}