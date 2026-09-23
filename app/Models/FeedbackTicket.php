<?php


namespace App\Models;


use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Relations\BelongsTo;


class FeedbackTicket extends Model

{

    protected $fillable = [

        'user_id',

        'subject',

        'message',

        'status',

        'screenshot_path',

        'attachments',

    ];


    protected function casts(): array

    {

        return [

            'attachments' => 'array',

        ];

    }


    public function user(): BelongsTo

    {

        return $this->belongsTo(User::class);

    }


    public function getStatusLabelAttribute(): string

    {

        return match ($this->status) {

            'new' => 'Новое',

            'in_work' => 'В работе',

            'closed' => 'Закрыто',

            default => $this->status,

        };

    }

}
