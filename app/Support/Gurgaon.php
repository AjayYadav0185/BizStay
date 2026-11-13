<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Backwards-compatible alias for the original Gurgaon-only helper.
 *
 * @deprecated Use {@see Ncr}, which covers every Delhi NCR city.
 */
final class Gurgaon
{
    /** @return list<string> */
    public static function localities(): array
    {
        return Ncr::cities()['Gurugram']['localities'];
    }

    /** @return array<string, string> */
    public static function localityOptions(): array
    {
        return Ncr::localityOptions('Gurugram');
    }

    /** @return list<string> */
    public static function workHubs(): array
    {
        return Ncr::workHubs();
    }

    /** @return array<string, string> */
    public static function inquirySources(): array
    {
        return Ncr::inquirySources();
    }

    /** @return array{single: int, double: int, triple: int, quad: int} */
    public static function pgRentBands(): array
    {
        return Ncr::pgRentBands();
    }

    public static function dhbvnRate(): float
    {
        return Ncr::discomRate('Gurugram');
    }

    public static function hotelGstPercent(float $nightlyTariff): float
    {
        return Ncr::hotelGstPercent($nightlyTariff);
    }

    public static function defaultCheckIn(): string
    {
        return Ncr::defaultCheckIn();
    }

    public static function defaultCheckOut(): string
    {
        return Ncr::defaultCheckOut();
    }
}
