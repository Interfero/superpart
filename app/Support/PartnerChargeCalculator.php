<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * Начисление партнёру: 30% по умолчанию; computer 40% с 2026-09-09 MSK (ТЗ FR-FIN-02).
 * Потолок 2500 ₽.
 */
final class PartnerChargeCalculator
{
    public const RATE_DEFAULT = 0.30;

    public const RATE_COMPUTER = 0.40;

    public const CAP = 2500.0;

    public const RULE_DEFAULT = 'default-30-cap2500-v1';

    public const RULE_COMPUTER = 'computer-40-20260909-v1';

    public const COMPUTER_CUTOFF_MSK = '2026-09-09 00:00:00';

    /**
     * @return array{amount: float, rule: string, rate: float}
     */
    public static function calculate(
        float|int|null $paid,
        float|int|null $parts,
        ?string $equipmentType = null,
        Carbon|string|null $createdAt = null,
    ): array {
        $net = max(0.0, (float) $paid - (float) $parts);
        $isComputer = self::isComputerRule($equipmentType, $createdAt);
        $rate = $isComputer ? self::RATE_COMPUTER : self::RATE_DEFAULT;
        $rule = $isComputer ? self::RULE_COMPUTER : self::RULE_DEFAULT;
        $amount = min(round($net * $rate), self::CAP);

        return [
            'amount' => $amount,
            'rule' => $rule,
            'rate' => $rate,
        ];
    }

    public static function fromNet(float $net, ?string $equipmentType = null, Carbon|string|null $createdAt = null): float
    {
        return self::calculate(max(0.0, $net), 0, $equipmentType, $createdAt)['amount'];
    }

    public static function fromPaidAndParts(
        float|int|null $paid,
        float|int|null $parts,
        ?string $equipmentType = null,
        Carbon|string|null $createdAt = null,
    ): float {
        return self::calculate($paid, $parts, $equipmentType, $createdAt)['amount'];
    }

    public static function usesComputerRate(?string $equipmentType, Carbon|string|null $createdAt): bool
    {
        return self::isComputerRule($equipmentType, $createdAt);
    }

    public static function isComputerRule(?string $equipmentType, Carbon|string|null $createdAt): bool
    {
        if (mb_strtolower(trim((string) $equipmentType)) !== 'computer') {
            return false;
        }
        if ($createdAt === null || $createdAt === '') {
            return false;
        }
        $created = $createdAt instanceof Carbon
            ? $createdAt->copy()->timezone('Europe/Moscow')
            : Carbon::parse($createdAt, 'Europe/Moscow');

        return $created->greaterThanOrEqualTo(self::computerRuleStartsAt());
    }

    public static function computerRuleStartsAt(): Carbon
    {
        return Carbon::parse(self::COMPUTER_CUTOFF_MSK, 'Europe/Moscow');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromCrmPayload(array $data): ?float
    {
        $equipment = isset($data['equipment_type']) ? (string) $data['equipment_type'] : null;
        $createdAt = $data['created_at'] ?? $data['order_created_at'] ?? null;

        if (isset($data['charge_amount']) && is_numeric($data['charge_amount'])) {
            // Независимо пересчитать при наличии сумм.
            $hasPaid = array_key_exists('paid_amount', $data) || array_key_exists('amount_paid', $data);
            $hasParts = array_key_exists('parts_amount', $data) || array_key_exists('amount_comp', $data) || array_key_exists('amount_parts', $data);
            if ($hasPaid || $hasParts) {
                $paid = (float) ($data['paid_amount'] ?? $data['amount_paid'] ?? 0);
                $parts = (float) ($data['parts_amount'] ?? $data['amount_parts'] ?? $data['amount_comp'] ?? 0);

                return self::fromPaidAndParts($paid, $parts, $equipment, $createdAt);
            }

            return min(round((float) $data['charge_amount']), self::CAP);
        }

        $hasPaid = array_key_exists('paid_amount', $data) || array_key_exists('amount_paid', $data);
        $hasParts = array_key_exists('parts_amount', $data) || array_key_exists('amount_comp', $data) || array_key_exists('amount_parts', $data);

        if (! $hasPaid && ! $hasParts) {
            return null;
        }

        $paid = (float) ($data['paid_amount'] ?? $data['amount_paid'] ?? 0);
        $parts = (float) ($data['parts_amount'] ?? $data['amount_parts'] ?? $data['amount_comp'] ?? 0);

        return self::fromPaidAndParts($paid, $parts, $equipment, $createdAt);
    }
}
