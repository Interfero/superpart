<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PartnerBankCard extends Model
{
    public const TYPE_CARD = 'card';

    public const TYPE_IP = 'ip';

    protected $fillable = [
        'user_id',
        'type',
        'card_number',
        'account_number',
        'bik',
        'correspondent_account',
        'inn',
        'bank',
        'recipient',
        'recipient_birth_date',
    ];

    protected function casts(): array
    {
        return [
            'recipient_birth_date' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isIp(): bool
    {
        return $this->type === self::TYPE_IP;
    }

    public function isCard(): bool
    {
        return ! $this->isIp();
    }

    public function typeLabel(): string
    {
        return $this->isIp() ? 'ИП' : 'Карта';
    }

    public function formattedCardNumber(): string
    {
        $digits = preg_replace('/\D/', '', (string) $this->card_number) ?? '';

        return trim(chunk_split($digits, 4, ' '));
    }

    public function formattedAccountNumber(): string
    {
        $digits = preg_replace('/\D/', '', (string) $this->account_number) ?? '';

        return $digits;
    }

    /** Краткая подпись в селекте на форме вывода. */
    public function selectLabel(): string
    {
        if ($this->isIp()) {
            $account = $this->formattedAccountNumber();
            $tail = strlen($account) >= 4 ? substr($account, -4) : $account;

            return 'ИП · р/с …'.$tail.' · '.$this->bank.' · '.$this->recipient;
        }

        return $this->formattedCardNumber().' · '.$this->bank.' · '.$this->recipient;
    }

    public function requisitesText(): string
    {
        if ($this->isIp()) {
            $lines = [
                'Тип: ИП (расчётный счёт)',
                'Получатель: '.$this->recipient,
                'Банк: '.$this->bank,
                'БИК: '.$this->bik,
                'Р/с: '.$this->formattedAccountNumber(),
            ];

            if ($this->correspondent_account) {
                $lines[] = 'К/с: '.$this->correspondent_account;
            }

            if ($this->inn) {
                $lines[] = 'ИНН: '.$this->inn;
            }

            if ($this->recipient_birth_date) {
                $lines[] = 'Дата рождения: '.$this->recipient_birth_date->format('d.m.Y');
            }

            return implode("\n", $lines);
        }

        $lines = [
            'Карта: '.$this->formattedCardNumber(),
            'Банк: '.$this->bank,
            'Получатель: '.$this->recipient,
        ];

        if ($this->recipient_birth_date) {
            $lines[] = 'Дата рождения: '.$this->recipient_birth_date->format('d.m.Y');
        }

        return implode("\n", $lines);
    }
}
