<?php

namespace Tests\Unit;

use App\Models\Sale;
use Tests\TestCase;

class SaleSistrackCollectAmountTest extends TestCase
{
    public function test_transfer_collects_zero_in_sistrack(): void
    {
        $sale = new Sale([
            'payment_method' => 'transfer',
            'total' => 42.50,
        ]);

        $this->assertTrue($sale->isPaidBeforeShipping());
        $this->assertSame(0.0, $sale->sistrackCollectAmount());
    }

    public function test_cash_collects_sale_total_in_sistrack(): void
    {
        $sale = new Sale([
            'payment_method' => 'cash',
            'total' => 42.50,
        ]);

        $this->assertFalse($sale->isPaidBeforeShipping());
        $this->assertSame(42.5, $sale->sistrackCollectAmount());
    }

    public function test_card_and_credit_still_collect_sale_total(): void
    {
        foreach (['card', 'credit'] as $method) {
            $sale = new Sale([
                'payment_method' => $method,
                'total' => 18.99,
            ]);

            $this->assertFalse($sale->isPaidBeforeShipping());
            $this->assertSame(18.99, $sale->sistrackCollectAmount());
        }
    }
}
