<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Gurgaon operating defaults.
 *
 * Single source of truth for locality names, rent bands, DHBVN power
 * tariff and GST slabs so seeders, forms and the invoice view agree.
 */
final class Gurgaon
{
    /** @return list<string> */
    public static function localities(): array
    {
        return [
            'Sector 14', 'Sector 29', 'Sector 38', 'Sector 44', 'Sector 45',
            'Sector 56', 'Sector 57', 'Sector 62', 'Sector 66',
            'DLF Phase 1', 'DLF Phase 2', 'DLF Phase 3', 'DLF Phase 4', 'DLF Phase 5',
            'Golf Course Road', 'Golf Course Ext. Road', 'Sohna Road',
            'Cyber City', 'Udyog Vihar', 'MG Road', 'South City',
            'New Gurgaon', 'Manesar',
        ];
    }

    /** @return array<string,string> value => label for selects */
    public static function localityOptions(): array
    {
        return array_combine(self::localities(), self::localities());
    }

    /** @return list<string> */
    public static function workHubs(): array
    {
        return ['Cyber City', 'Cyber Hub', 'Udyog Vihar', 'Golf Course Road', 'Sohna Road', 'MG Road', 'Manesar', 'IFFCO Chowk'];
    }

    /** @return array<string,string> */
    public static function inquirySources(): array
    {
        return [
            'walk_in' => 'Walk-in',
            'phone' => 'Phone',
            'google' => 'Google / Maps',
            'referral' => 'Referral',
            'nobroker' => 'NoBroker',
            '99acres' => '99acres',
            'magicbricks' => 'MagicBricks',
            'housing' => 'Housing.com',
            'stanza' => 'Stanza / Zolo',
            'corporate' => 'Corporate tie-up',
            'website' => 'Website',
            'other' => 'Other',
        ];
    }

    /**
     * Sensible per-bed monthly rent band by sharing (Gurgaon 2026).
     *
     * @return array{single:int,double:int,triple:int,quad:int}
     */
    public static function pgRentBands(): array
    {
        return ['single' => 18000, 'double' => 10000, 'triple' => 8000, 'quad' => 6500];
    }

    /** DHBVN domestic slab hint ₹/kWh actually recovered from tenants. */
    public static function dhbvnRate(): float
    {
        return 9.00;
    }

    /** Hotel GST slab on room tariff (per night, excl. food). 0/12/18%. */
    public static function hotelGstPercent(float $nightlyTariff): float
    {
        if ($nightlyTariff <= 0) {
            return 0.0;
        }

        return $nightlyTariff <= 7500 ? 12.0 : 18.0;
    }

    public static function defaultCheckIn(): string
    {
        return '12:00';
    }

    public static function defaultCheckOut(): string
    {
        return '11:00';
    }
}
