<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Review extends Model
{
    protected $fillable = [
        'user_id',
        'order_id',
        'status',
        'review_url',
        'comment',
        'result',
        'refund',
        'photos',
        'review_type',
        'city_id',
        'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'refund' => 'boolean',
            'photos' => 'array',
            'closed_at' => 'datetime',
        ];
    }

    public function scopeForPortalUser(Builder $query, User $user): void
    {
        if ($user->hasElevatedAccess()) {
            return;
        }

        $query->whereHas('order', function (Builder $orders) use ($user) {
            $orders->forPortalUser($user);
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }
}