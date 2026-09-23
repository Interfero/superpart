<?php

namespace App\Support;

class CrmOrderStatusMapper
{
    public static function mapsFromCrm(?string $crmStatus): bool
    {
        if ($crmStatus === null || $crmStatus === '') {
            return false;
        }

        if (mb_strtolower(trim($crmStatus)) === 'completed') {
            return true;
        }

        return self::toPortal($crmStatus) !== null;
    }

    public static function mapFromCrm(?string $crmStatus, bool $isNonProfile = false): ?string
    {
        return self::toPortal($crmStatus, $isNonProfile);
    }

    public static function fromCrmCompleted(?float $charge): string
    {
        if ($charge !== null && $charge > 0) {
            return 'waiting_payment';
        }

        return 'refusal';
    }

    public static function toPortal(?string $crmStatus, bool $isNonProfile = false): ?string
    {
        if ($crmStatus === null || $crmStatus === '') {
            return null;
        }

        $status = mb_strtolower(trim($crmStatus));

        $mapped = match ($status) {
            'waiting',
            'wait',
            'new',
            'created',
            'pending',
            'ожидает' => 'waiting',

            'waiting_payment',
            'payment_wait',
            'ожидает оплаты',
            'ожидает выплаты' => 'waiting_payment',

            'in_work',
            'work',
            'working',
            'accepted',
            'в работе' => 'in_work',

            'clarification',
            'need_clarification',
            'уточнение',
            'на уточнении' => 'clarification',

            'not_processed',
            'not processed',
            'не оформлена',
            'не обработана' => 'not_processed',

            'refusal',
            'reject',
            'rejected',
            'отказ' => 'refusal',

            'refusal_non_profile',
            'non_profile',
            'непрофиль',
            'отказ непрофиль' => 'refusal_non_profile',

            'cancelled',
            'canceled',
            'cancel',
            'отмена',
            'отменена' => 'cancelled',

            default => null,
        };

        if ($mapped === 'refusal' && $isNonProfile) {
            return 'refusal_non_profile';
        }

        return $mapped;
    }
}
