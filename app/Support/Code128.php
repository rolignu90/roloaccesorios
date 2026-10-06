<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * Code 128 (subset B) rendered as SVG. Subset B covers printable ASCII (32–126),
 * which includes every product code accepted by the inventory forms.
 */
class Code128
{
    public const PATTERNS = [
        '212222', '222122', '222221', '121223', '121322', '131222', '122213', '122312', '132212', '221213',
        '221312', '231212', '112232', '122132', '122231', '113222', '123122', '123221', '223211', '221132',
        '221231', '213212', '223112', '312131', '311222', '321122', '321221', '312212', '322112', '322211',
        '212123', '212321', '232121', '111323', '131123', '131321', '112313', '132113', '132311', '211313',
        '231113', '231311', '112133', '112331', '132131', '113123', '113321', '133121', '313121', '211331',
        '231131', '213113', '213311', '213131', '311123', '311321', '331121', '312113', '312311', '332111',
        '314111', '221411', '431111', '111224', '111422', '121124', '121421', '141122', '141221', '112214',
        '112412', '122114', '122411', '142112', '142211', '241211', '221114', '413111', '241112', '134111',
        '111242', '121142', '121241', '114212', '124112', '124211', '411212', '421112', '421211', '212141',
        '214121', '412121', '111143', '111341', '131141', '114113', '114311', '411113', '411311', '113141',
        '114131', '311141', '411131', '211412', '211214', '211232', '2331112',
    ];

    private const START_B = 104;

    private const STOP = 106;

    private const QUIET_ZONE = 10;

    public static function supports(string $data): bool
    {
        return $data !== '' && preg_match('/^[\x20-\x7E]+$/', $data) === 1;
    }

    /** @return list<int> symbol values including start, checksum and stop */
    public static function symbols(string $data): array
    {
        if (! self::supports($data)) {
            throw new InvalidArgumentException("El código [{$data}] tiene caracteres que no se pueden codificar.");
        }

        $symbols = [self::START_B];
        $checksum = self::START_B;

        foreach (str_split($data) as $position => $char) {
            $value = ord($char) - 32;
            $symbols[] = $value;
            $checksum += $value * ($position + 1);
        }

        $symbols[] = $checksum % 103;
        $symbols[] = self::STOP;

        return $symbols;
    }

    /** Bar/space widths in modules, starting with a bar. */
    public static function widths(string $data): array
    {
        $widths = [];
        foreach (self::symbols($data) as $symbol) {
            foreach (str_split(self::PATTERNS[$symbol]) as $width) {
                $widths[] = (int) $width;
            }
        }

        return $widths;
    }

    public static function svg(string $data): string
    {
        $x = self::QUIET_ZONE;
        $rects = '';

        foreach (self::widths($data) as $index => $width) {
            if ($index % 2 === 0) {
                $rects .= '<rect x="'.$x.'" y="0" width="'.$width.'" height="1"/>';
            }
            $x += $width;
        }

        $total = $x + self::QUIET_ZONE;

        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 '.$total.' 1" preserveAspectRatio="none" shape-rendering="crispEdges" role="img" aria-label="'.e($data).'">'
            .'<rect width="'.$total.'" height="1" fill="#fff"/><g fill="#000">'.$rects.'</g></svg>';
    }
}
