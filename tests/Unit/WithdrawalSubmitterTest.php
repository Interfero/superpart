<?php

namespace Tests\Unit;

use App\Support\WithdrawalSubmitter;
use PHPUnit\Framework\TestCase;

class WithdrawalSubmitterTest extends TestCase
{
    public function test_reserved_message_does_not_blame_cooling(): void
    {
        $message = WithdrawalSubmitter::formatUnavailableMessage([], [490, 578], []);

        $this->assertStringContainsString('уже в другой выплате: 490, 578', $message);
        $this->assertStringNotContainsString('часов после закрытия', $message);
    }

    public function test_cooling_message_lists_order_ids(): void
    {
        $message = WithdrawalSubmitter::formatUnavailableMessage([597], [], []);

        $this->assertStringContainsString('36 часов после закрытия: 597', $message);
        $this->assertStringContainsString('не показываются в списке к выводу', $message);
    }

    public function test_mixed_reasons_are_separated(): void
    {
        $message = WithdrawalSubmitter::formatUnavailableMessage([597], [490], [12]);

        $this->assertStringContainsString('уже в другой выплате: 490', $message);
        $this->assertStringContainsString('36 часов после закрытия: 597', $message);
        $this->assertStringContainsString('Начисления недоступны для вывода: 12', $message);
    }
}
