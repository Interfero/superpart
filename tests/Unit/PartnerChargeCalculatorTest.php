<?php

namespace Tests\Unit;

use App\Support\PartnerChargeCalculator;
use PHPUnit\Framework\TestCase;

class PartnerChargeCalculatorTest extends TestCase
{
    public function test_from_crm_payload_uses_paid_amount_keys(): void
    {
        $charge = PartnerChargeCalculator::fromCrmPayload([
            'paid_amount' => 1500,
            'parts_amount' => 0,
        ]);

        $this->assertSame(450.0, $charge);
    }

    public function test_from_crm_payload_caps_at_2500(): void
    {
        $charge = PartnerChargeCalculator::fromCrmPayload([
            'amount_paid' => 20000,
            'amount_comp' => 0,
        ]);

        $this->assertSame(2500.0, $charge);
    }

    public function test_null_when_no_money_fields(): void
    {
        $this->assertNull(PartnerChargeCalculator::fromCrmPayload([
            'order_status' => 'completed',
        ]));
    }
}
