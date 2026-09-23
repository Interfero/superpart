<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Validation\Rule;

class Source extends Model
{
    use SoftDeletes;

    public const TYPE_ADVERTISING_PLATFORM = 'advertising_platform';

    public const TYPE_WEBSITE = 'website';

    public const TYPE_OTHER = 'other';

    /** @return array<string, string> */
    public static function typeLabels(): array
    {
        return [
            self::TYPE_ADVERTISING_PLATFORM => 'Рекламная площадка',
            self::TYPE_WEBSITE => 'Сайт',
            self::TYPE_OTHER => 'Другое',
        ];
    }

    public static function hasReviewForType(string $type): bool
    {
        return $type === self::TYPE_ADVERTISING_PLATFORM;
    }

    public function typeLabel(): string
    {
        return self::typeLabels()[$this->source_type] ?? '—';
    }

    protected static function booted(): void
    {
        static::saving(function (Source $source) {
            if ($source->source_type !== null) {
                $source->has_review = self::hasReviewForType($source->source_type);
            }
        });
    }

    /**
     * Источник назначен партнёру (pivot) или принадлежит ему как владельцу (legacy).
     */
    public static function existsRuleForOwnerId(int $ownerId): \Illuminate\Validation\Rules\Exists
    {
        return Rule::exists('sources', 'id')->where(function ($query) use ($ownerId) {
            $query->whereNull('deleted_at')
                ->where(function ($sources) use ($ownerId) {
                    $sources->where('user_id', $ownerId)
                        ->orWhereIn('id', function ($sub) use ($ownerId) {
                            $sub->select('source_id')
                                ->from('user_allowed_sources')
                                ->where('user_id', $ownerId);
                        });
                });
        });
    }

    /** Источник не удалён (для проверок без привязки к владельцу). */
    public static function existsRuleActive(): \Illuminate\Validation\Rules\Exists
    {
        return Rule::exists('sources', 'id')->whereNull('deleted_at');
    }

    protected $fillable = [
        'user_id',
        'name',
        'comment',
        'source_type',
        'has_review',
        'review_url',
    ];

    protected function casts(): array
    {
        return [
            'has_review' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Зеркало в справочнике CRM (reference_sources). */
    public function referenceMirror(): HasOne
    {
        return $this->hasOne(ReferenceSource::class, 'local_source_id');
    }

    /** ID как в CRM; если зеркала ещё нет — null (не путать с локальным id). */
    public function crmDisplayId(): ?int
    {
        $mirror = $this->relationLoaded('referenceMirror')
            ? $this->referenceMirror
            : $this->referenceMirror()->first();

        if (! $mirror || $mirror->levelion_source_id === null || (int) $mirror->levelion_source_id < 1) {
            return null;
        }

        return (int) $mirror->levelion_source_id;
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }
}
