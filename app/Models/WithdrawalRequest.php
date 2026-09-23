<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WithdrawalRequest extends Model
{
    protected $fillable = [
        'user_id',
        'batch_id',
        'status',
        'total_amount',
        'bank_card',
        'recipient_birth_date',
        'requisites',
        'comment',
        'planned_collect_at',
        'collect_reminded_at',
    ];

    protected function casts(): array
    {
        return [
            'total_amount' => 'decimal:0',
            'recipient_birth_date' => 'date',
            'planned_collect_at' => 'date',
            'collect_reminded_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function ledgerTransaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'ledger_transaction_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(WithdrawalRequestItem::class);
    }
}
