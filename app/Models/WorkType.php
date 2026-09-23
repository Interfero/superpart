<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkType extends Model
{
    protected $fillable = [
        'sort_order',
        'equipment_code',
        'name',
        'description',
        'is_profile',
    ];

    protected function casts(): array
    {
        return [
            'is_profile' => 'boolean',
        ];
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /** Текст описания без ведущих пробелов на строках. */
    public function displayDescription(): string
    {
        return \App\Support\OrderEquipmentRegulation::normalizeDescriptionText(
            (string) $this->description
        );
    }
}
