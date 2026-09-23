<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgentAccountLock extends Model
{
    protected $fillable = [
        'user_id',
        'lock_auth',
        'lock_profile',
        'reason',
    ];

    protected function casts(): array
    {
        return [
            'lock_auth' => 'boolean',
            'lock_profile' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
