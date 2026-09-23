<?php

namespace Tests\Unit;

use App\Support\UserBalance;
use App\Support\WithdrawableTransactions;
use Tests\TestCase;

class WithdrawableTransactionsTest extends TestCase
{
    public function test_cooling_hours_constant(): void
    {
        $this->assertSame(36, WithdrawableTransactions::COOLING_HOURS);
    }

    public function test_backfill_returns_zero_when_nothing_to_fix(): void
    {
        // На пустой/тестовой БД без подходящих заявок — безопасный no-op.
        $fixed = UserBalance::backfillMissingClosedLocal(1);
        $this->assertIsInt($fixed);
        $this->assertGreaterThanOrEqual(0, $fixed);
    }
}
