<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Indian-style money formatting: ₹1,25,000.00 and amounts in words
 * ("One Lakh Twenty Five Thousand Rupees Only") as printed on receipts.
 */
final class IndianCurrency
{
    private const ONES = [
        '', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine', 'Ten',
        'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen',
    ];

    private const TENS = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];

    public static function format(float $amount, int $decimals = 2, bool $symbol = true): string
    {
        $negative = $amount < 0;
        $fixed = number_format(abs($amount), $decimals, '.', '');
        [$whole, $fraction] = array_pad(explode('.', $fixed), 2, '');

        if (strlen($whole) > 3) {
            $lastThree = substr($whole, -3);
            $rest = substr($whole, 0, -3);
            $rest = preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', $rest) ?? $rest;
            $whole = $rest.','.$lastThree;
        }

        return ($negative ? '−' : '').($symbol ? '₹' : '').$whole.($decimals > 0 ? '.'.$fraction : '');
    }

    public static function inWords(float $amount): string
    {
        $rupees = (int) floor(abs($amount));
        $paise = (int) round((abs($amount) - $rupees) * 100);

        $words = $rupees === 0 ? 'Zero' : self::convert($rupees);
        $result = $words.' Rupee'.($rupees === 1 ? '' : 's');

        if ($paise > 0) {
            $result .= ' and '.self::convert($paise).' Paise';
        }

        return $result.' Only';
    }

    private static function convert(int $number): string
    {
        $parts = [];

        foreach ([['Crore', 10000000], ['Lakh', 100000], ['Thousand', 1000], ['Hundred', 100]] as [$label, $size]) {
            if ($number >= $size) {
                $parts[] = self::convert(intdiv($number, $size)).' '.$label;
                $number %= $size;
            }
        }

        if ($number > 0) {
            $parts[] = $number < 20
                ? self::ONES[$number]
                : trim(self::TENS[intdiv($number, 10)].' '.self::ONES[$number % 10]);
        }

        return implode(' ', $parts);
    }
}
