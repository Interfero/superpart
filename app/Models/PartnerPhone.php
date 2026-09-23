<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PartnerPhone extends Model
{
    protected $fillable = [
        'user_id',
        'phone',
        'source_id',
        'reference_source_id',
    ];

    protected function casts(): array
    {
        return [
            'source_id' => 'integer',
            'reference_source_id' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(Source::class);
    }

    public function referenceSource(): BelongsTo
    {
        return $this->belongsTo(ReferenceSource::class);
    }
}
