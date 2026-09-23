<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WithdrawalRequestItem extends Model
{
    protected $fillable = [
        'withdrawal_request_id',
        'transaction_id',
        'order_id',
        'amount',
        'city_id',
        'status',
        'created_local',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:0',
            'created_local' => 'datetime',
        ];
    }

    public function withdrawalRequest(): BelongsTo
    {
        return $this->belongsTo(WithdrawalRequest::class);
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
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
