<?php

namespace App\Support;

/**
 * Канонический checksum снимка заявки CRM→SP (ТЗ FR-SYNC-04).
 */
final class OrderSnapshotChecksum
{
    /**
     * @param  array<string, mixed>  $orderBlock
     */
    public static function compute(array $orderBlock, int $eventVersion): string
    {
        $forChecksum = [
            'id' => (int) ($orderBlock['id'] ?? 0),
            'event_version' => $eventVersion,
            'created_at' => $orderBlock['created_at'] ?? null,
            'closed_at' => $orderBlock['closed_at'] ?? null,
            'status' => $orderBlock['status'] ?? null,
            'source_id' => array_key_exists('source_id', $orderBlock) && $orderBlock['source_id'] !== null
                ? (int) $orderBlock['source_id'] : null,
            'source_available_for_superpart' => (bool) ($orderBlock['source_available_for_superpart'] ?? false),
            'partner_user_id' => array_key_exists('partner_user_id', $orderBlock) && $orderBlock['partner_user_id'] !== null
                ? (int) $orderBlock['partner_user_id'] : null,
            'equipment_type' => $orderBlock['equipment_type'] ?? null,
            'order_type' => $orderBlock['order_type'] ?? null,
            'amount_paid' => (int) ($orderBlock['amount_paid'] ?? 0),
            'amount_parts' => (int) ($orderBlock['amount_parts'] ?? $orderBlock['amount_comp'] ?? 0),
            'reward_amount' => (int) ($orderBlock['reward_amount'] ?? 0),
            'reward_rule' => $orderBlock['reward_rule'] ?? null,
        ];
        ksort($forChecksum);

        return hash('sha256', json_encode($forChecksum, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }
}
