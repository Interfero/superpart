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

    public static function fromCrmCompleted(?float $charge, ?string $orderType = null): string
    {
        if ($orderType === 'warranty') {
            return 'warranty';
        }

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
            'in_progress',
            'work',
            'working',
            'accepted',
            'в работе' => 'in_work',

            'in_work_sd',
            'in_progress_sd',
            'в работе сд' => 'in_work_sd',

            'on_way',
            'on the way',
            'в пути' => 'on_way',

            'ready',
            'done',
            'готов',
            'готова' => 'ready',

            'clarification',
            'need_clarification',
            'callback',
            'уточнение',
            'на уточнении',
            'прозвон' => 'clarification',

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
            'cancelled_cc',
            'cancelled_city',
            'отмена',
            'отменена',
            'отмена кц',
            'отменена кц' => 'cancelled',

            'warranty',
            'warranty_closed',
            'гарантия' => 'warranty',

            default => null,
        };

        if ($mapped === 'refusal' && $isNonProfile) {
            return 'refusal_non_profile';
        }

        return $mapped;
    }
}
