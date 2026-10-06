<?php

namespace Tests\Unit;

use App\Models\Sale;
use App\Models\ShippingCarrier;
use Tests\TestCase;

class SaleSistrackResendTest extends TestCase
{
    private function carrier(): ShippingCarrier
    {
        return new ShippingCarrier([
            'sistrack_enabled' => true,
            'sistrack_email' => 'sistrack@example.com',
            'sistrack_password' => 'secret',
        ]);
    }

    public function test_sent_sale_can_be_resent_not_sent_again(): void
    {
        $sale = new Sale([
            'status' => Sale::STATUS_CONFIRMED,
            'has_shipping' => true,
            'sistrack_status' => Sale::SISTRACK_SENT,
        ]);
        $sale->setRelation('shippingCarrier', $this->carrier());

        $this->assertFalse($sale->canSendToSistrack());
        $this->assertTrue($sale->canResendToSistrack());
    }

    public function test_unsent_sale_cannot_be_resent(): void
    {
        $sale = new Sale([
            'status' => Sale::STATUS_CONFIRMED,
            'has_shipping' => true,
            'sistrack_status' => Sale::SISTRACK_PENDING,
        ]);
        $sale->setRelation('shippingCarrier', $this->carrier());

        $this->assertTrue($sale->canSendToSistrack());
        $this->assertFalse($sale->canResendToSistrack());
    }
}
