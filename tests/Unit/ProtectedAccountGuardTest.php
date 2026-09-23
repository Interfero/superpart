<?php

namespace Tests\Unit;

use App\Support\ProtectedAccountGuard;
use PHPUnit\Framework\TestCase;

class ProtectedAccountGuardTest extends TestCase
{
    public function test_balance_is_always_allowed_ledger_field(): void
    {
        $this->assertContains('balance', ProtectedAccountGuard::ALWAYS_ALLOWED);
        $this->assertNotContains('balance', ProtectedAccountGuard::PROFILE_FIELDS);
    }

    public function test_password_remains_locked_field(): void
    {
        $this->assertContains('password', ProtectedAccountGuard::AUTH_FIELDS);
    }
}
