<?php

namespace Tests\Unit;

use App\Support\PartnerSourceAccess;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class PartnerSourceAccessVisibilityTest extends TestCase
{
    public function test_restrict_transactions_method_exists(): void
    {
        $this->assertTrue(method_exists(PartnerSourceAccess::class, 'restrictTransactionsToAccessibleOrders'));
    }

    public function test_accessible_source_ids_filters_require_available_flag_in_code(): void
    {
        $ref = new ReflectionClass(PartnerSourceAccess::class);
        $src = file_get_contents($ref->getFileName());
        $this->assertStringContainsString("where('available_for_superpart', true)", $src);
        $this->assertStringContainsString('restrictTransactionsToAccessibleOrders', $src);
    }
}
