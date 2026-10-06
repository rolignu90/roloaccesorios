<?php

namespace Tests\Unit;

use App\Models\LogisticsShipment;
use PHPUnit\Framework\TestCase;

class LogisticsPayableFormulaTest extends TestCase
{
    public function test_payable_matches_cod_minus_sistrack_shipping_and_service(): void
    {
        // COD $9 − ($9 × 2.5%) − $2.75 flete − $1.00 comisión ROLO
        $payable = LogisticsShipment::computePayable(
            collectAmount: 9.00,
            carrierShippingCost: 2.75,
            carrierCommissionAmount: 0.23, // round(9 * 0.025, 2)
            serviceCommission: 1.00,
        );

        $this->assertSame(5.02, $payable);
    }

    public function test_payable_never_goes_negative(): void
    {
        $payable = LogisticsShipment::computePayable(
            collectAmount: 2.00,
            carrierShippingCost: 2.75,
            carrierCommissionAmount: 0.05,
            serviceCommission: 1.00,
        );

        $this->assertSame(0.0, $payable);
    }
}
