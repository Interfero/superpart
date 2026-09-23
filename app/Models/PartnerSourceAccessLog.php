<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PartnerSourceAccessLog extends Model
{
    protected $fillable = [
        'actor_user_id',
        'partner_user_id',
        'reference_source_id',
        'source_id',
        'action',
        'source_name',
    ];

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'partner_user_id');
    }

    public function referenceSource(): BelongsTo
    {
        return $this->belongsTo(ReferenceSource::class, 'reference_source_id');
    }

    public function localSource(): BelongsTo
    {
        return $this->belongsTo(Source::class, 'source_id');
    }

    public function description(): string
    {
        $who = $this->actor?->email ?? $this->actor?->name ?? 'система';
        $partner = $this->partner?->displayFullName() ?? ('#'.$this->partner_user_id);
        $src = $this->source_name ?: ('#'.($this->reference_source_id ?? $this->source_id));
        $verb = $this->action === 'detach' ? 'удалил' : 'добавил';

        return $this->created_at?->format('d.m.Y H:i').' — '.$who.' '.$verb.' источник «'.$src.'» партнёру '.$partner.'.';
    }
}
