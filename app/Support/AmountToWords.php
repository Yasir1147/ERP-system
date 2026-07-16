<?php

namespace App\Support;

class AmountToWords
{
    private const ONES = [
        0 => 'Zero', 1 => 'One', 2 => 'Two', 3 => 'Three', 4 => 'Four', 5 => 'Five',
        6 => 'Six', 7 => 'Seven', 8 => 'Eight', 9 => 'Nine', 10 => 'Ten',
        11 => 'Eleven', 12 => 'Twelve', 13 => 'Thirteen', 14 => 'Fourteen', 15 => 'Fifteen',
        16 => 'Sixteen', 17 => 'Seventeen', 18 => 'Eighteen', 19 => 'Nineteen',
    ];

    private const TENS = [
        2 => 'Twenty', 3 => 'Thirty', 4 => 'Forty', 5 => 'Fifty',
        6 => 'Sixty', 7 => 'Seventy', 8 => 'Eighty', 9 => 'Ninety',
    ];

    public static function convert(float|string $amount): string
    {
        $amount = round((float) $amount, 2);
        $whole = (int) floor($amount);
        $fraction = (int) round(($amount - $whole) * 100);
        $words = self::integer($whole);

        if ($fraction > 0) {
            $words .= ' And Fils '.self::integer($fraction);
        }

        return $words;
    }

    private static function integer(int $number): string
    {
        if ($number < 20) {
            return self::ONES[$number];
        }

        if ($number < 100) {
            return self::TENS[intdiv($number, 10)].($number % 10 ? ' '.self::ONES[$number % 10] : '');
        }

        if ($number < 1000) {
            return self::ONES[intdiv($number, 100)].' Hundred'.($number % 100 ? ' '.self::integer($number % 100) : '');
        }

        foreach ([1_000_000_000_000 => 'Trillion', 1_000_000_000 => 'Billion', 1_000_000 => 'Million', 1000 => 'Thousand'] as $value => $label) {
            if ($number >= $value) {
                $remainder = $number % $value;

                return self::integer(intdiv($number, $value)).' '.$label.($remainder ? ' '.self::integer($remainder) : '');
            }
        }

        return '';
    }
}
