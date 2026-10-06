<?php

namespace Tests\Unit;

use App\Services\ProductSalesReportService;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class ProductSalesReportRangeTest extends TestCase
{
    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_periods_resolve_to_expected_bounds(): void
    {
        CarbonImmutable::setTestNow('2026-03-31 15:00:00');
        $report = new ProductSalesReportService;

        $this->assertSame([null, null], $report->range('all'));

        [$from, $to] = $report->range('last_month');
        $this->assertSame('2026-02-01 00:00:00', $from->toDateTimeString());
        $this->assertSame('2026-02-28 23:59:59', $to->toDateTimeString());

        [$from, $to] = $report->range('7d');
        $this->assertSame('2026-03-25 00:00:00', $from->toDateTimeString());
        $this->assertSame('2026-03-31 23:59:59', $to->toDateTimeString());

        [$from, $to] = $report->range('custom', '2026-01-10', null);
        $this->assertSame('2026-01-10 00:00:00', $from->toDateTimeString());
        $this->assertNull($to);
    }
}
