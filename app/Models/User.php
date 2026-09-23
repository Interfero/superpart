<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    public const ROLE_DEVELOPER = 'developer';

    public const ROLE_GENERAL_DIRECTOR = 'general_director';

    public const ROLE_PARTNER = 'partner';

    public const ROLE_MANAGER = 'manager';

    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'last_name',
        'first_name',
        'middle_name',
        'email',
        'password',
        'balance',
        'theme',
        'role',
        'parent_user_id',
        'comment',
        'last_login_at',
        'partner_code',
        'allowed_directions',
        'legal_form',
        'legal_name',
        'inn',
        'ogrn',
        'legal_address',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'api_login',
        'api_password',
        'two_factor_secret',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'balance' => 'decimal:0',
            'last_login_at' => 'datetime',
            'api_password' => 'encrypted',
            'allowed_directions' => 'array',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    /** Роли с обязательной 2FA (ТЗ). Вкл. через TWO_FACTOR_ENFORCE=true. */
    public function requiresTwoFactor(): bool
    {
        if (! config('security.two_factor_enforce', false)) {
            return false;
        }

        return $this->isDeveloper() || $this->isGeneralDirector();
    }

    public function hasTwoFactorEnabled(): bool
    {
        return filled($this->two_factor_secret) && $this->two_factor_confirmed_at !== null;
    }

    /** @return array<string, string> */
    public static function directionOptions(): array
    {
        return [
            'computer_help' => 'КП',
            'appliance_repair' => 'РБТ',
            'handyman' => 'МНЧ',
        ];
    }

    public function agentAccountLock(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(AgentAccountLock::class);
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function withdrawalRequests(): HasMany
    {
        return $this->hasMany(WithdrawalRequest::class);
    }

    public function partnerBankCards(): HasMany
    {
        return $this->hasMany(PartnerBankCard::class);
    }

    public function partnerPhones(): HasMany
    {
        return $this->hasMany(PartnerPhone::class);
    }

    public function sources(): HasMany
    {
        return $this->hasMany(Source::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'parent_user_id');
    }

    public function managedUsers(): HasMany
    {
        return $this->hasMany(User::class, 'parent_user_id');
    }

    public function allowedCities(): BelongsToMany
    {
        return $this->belongsToMany(City::class, 'user_allowed_cities')
            ->select('cities.*');
    }

    public function allowedSources(): BelongsToMany
    {
        return $this->belongsToMany(Source::class, 'user_allowed_sources');
    }

    public function allowedReferenceSources(): BelongsToMany
    {
        return $this->belongsToMany(ReferenceSource::class, 'user_allowed_reference_sources');
    }

    public function isDeveloper(): bool
    {
        return $this->role === self::ROLE_DEVELOPER;
    }

    public function isGeneralDirector(): bool
    {
        return $this->role === self::ROLE_GENERAL_DIRECTOR;
    }

    public function isPartner(): bool
    {
        return $this->role === self::ROLE_PARTNER;
    }

    public function isManager(): bool
    {
        return $this->role === self::ROLE_MANAGER;
    }

    /** Партнёр или менеджер: города ограничены связью user_allowed_cities. */
    public function hasRestrictedCityAccess(): bool
    {
        return $this->isPartner() || $this->isManager();
    }

    /** Роли с полным доступом к порталу. */
    public static function portalAdminRoles(): array
    {
        return [
            self::ROLE_DEVELOPER,
            self::ROLE_GENERAL_DIRECTOR,
        ];
    }

    public function hasElevatedAccess(): bool
    {
        return in_array($this->role, self::portalAdminRoles(), true);
    }

    /**
     * @param  Builder<User>  $query
     * @return Builder<User>
     */
    public function scopePortalAdmins(Builder $query): Builder
    {
        return $query->whereIn('role', self::portalAdminRoles());
    }

    public function roleLabel(): string
    {
        return match ($this->role) {
            self::ROLE_DEVELOPER => 'Разработчик',
            self::ROLE_GENERAL_DIRECTOR => 'Генеральный директор',
            self::ROLE_PARTNER => 'Партнёр',
            self::ROLE_MANAGER => 'Менеджер партнёра',
            default => (string) $this->role,
        };
    }

    public function effectiveOwnerId(): int
    {
        if ($this->isManager() && $this->parent_user_id) {
            return (int) $this->parent_user_id;
        }

        return (int) $this->id;
    }

    /**
     * Баланс кошелька для UI: у менеджера — баланс партнёра-владельца,
     * у админов портала — сумма балансов всех партнёров (чтобы бейдж не был пустым).
     */
    public function walletBalance(): float
    {
        if ($this->hasElevatedAccess()) {
            return round((float) static::query()->sum('balance'));
        }

        if (\App\Support\PartnerFinancePool::usesPool($this)) {
            return \App\Support\PartnerFinancePool::balance();
        }

        $ownerId = $this->effectiveOwnerId();

        if ($ownerId === (int) $this->id) {
            return round((float) ($this->balance ?? 0));
        }

        $ownerBalance = static::query()->whereKey($ownerId)->value('balance');

        return round((float) ($ownerBalance ?? 0));
    }

    /** Бейдж баланса в шапке — не для менеджеров партнёра. */
    public function showsWalletInNavbar(): bool
    {
        return ! $this->isManager();
    }

    /**
     * @return list<int>|null null — без ограничения (разработчик)
     */
    public function visibleOrderUserIds(): ?array
    {
        if ($this->hasElevatedAccess()) {
            return null;
        }

        if ($this->isPartner()) {
            return $this->managedUsers()
                ->pluck('id')
                ->push($this->id)
                ->unique()
                ->sort()
                ->values()
                ->map(fn ($id) => (int) $id)
                ->all();
        }

        return [(int) $this->id];
    }

    /**
     * Отображаемое ФИО: части из полей или legacy name.
     */
    public function displayFullName(): string
    {
        $parts = array_filter([
            $this->last_name,
            $this->first_name,
            $this->middle_name,
        ], fn (?string $v) => $v !== null && $v !== '');

        if ($parts !== []) {
            return implode(' ', $parts);
        }

        return $this->name;
    }

    public function legalFormLabel(): ?string
    {
        return match ($this->legal_form) {
            'ip' => 'ИП',
            'ooo' => 'ООО',
            default => null,
        };
    }
}
