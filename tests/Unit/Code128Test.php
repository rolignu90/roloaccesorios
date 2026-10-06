<?php

namespace Tests\Unit;

use App\Support\Code128;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class Code128Test extends TestCase
{
    public function test_every_pattern_has_eleven_modules_and_stop_has_thirteen(): void
    {
        foreach (Code128::PATTERNS as $symbol => $pattern) {
            $expected = $symbol === 106 ? 13 : 11;
            $this->assertSame($expected, array_sum(str_split($pattern)), "Símbolo {$symbol}");
        }

        $this->assertCount(107, array_unique(Code128::PATTERNS));
    }

    public function test_checksum_matches_reference_example(): void
    {
        $symbols = Code128::symbols('PJJ123C');

        $this->assertSame(104, $symbols[0]);
        // (104 + 48·1 + 42·2 + 42·3 + 17·4 + 18·5 + 19·6 + 35·7) mod 103
        $this->assertSame(55, $symbols[count($symbols) - 2]);
        $this->assertSame(106, end($symbols));
    }

    public function test_width_sequence_length(): void
    {
        $widths = Code128::widths('AUD-001');

        $this->assertSame(11 * 9 + 13, array_sum($widths));
        $this->assertStringContainsString('<svg', Code128::svg('AUD-001'));
    }

    public function test_rejects_non_ascii(): void
    {
        $this->assertFalse(Code128::supports('AÑO-1'));
        $this->expectException(InvalidArgumentException::class);
        Code128::symbols('AÑO-1');
    }
}
