<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Transaction extends Model
{
    protected $fillable = [
        'user_id',
        'order_id',
        'related_transaction_id',
        'amount',
        'operation_type',
        'reason_code',
        'idempotency_key',
        'reward_rule',
        'balance_after',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:0',
            'balance_after' => 'decimal:0',
            'completed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
