<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Delhi NCR operating defaults.
 *
 * Single source of truth for city/locality names, power distribution
 * companies, tenant police-verification portals, rent bands and GST slabs
 * so seeders, forms, invoices and the public page all agree.
 */
final class Ncr
{
    /**
     * Every NCR city BizStay is configured for.
     *
     * @return array<string, array{state: string, state_code: string, discom: string, discom_rate: float, police_portal: string, police_portal_url: string, localities: list<string>}>
     */
    public static function cities(): array
    {
        return [
            'Delhi' => [
                'state' => 'Delhi',
                'state_code' => '07',
                'discom' => 'BSES Rajdhani / BSES Yamuna / Tata Power-DDL',
                'discom_rate' => 8.00,
                'police_portal' => 'Delhi Police — Tenant Registration',
                'police_portal_url' => 'https://delhipolice.gov.in',
                'localities' => [
                    'Mukherjee Nagar', 'GTB Nagar', 'Kamla Nagar', 'Hudson Lane', 'Vijay Nagar', 'Old Rajinder Nagar',
                    'Karol Bagh', 'Patel Nagar', 'Laxmi Nagar', 'Preet Vihar', 'Mayur Vihar',
                    'Lajpat Nagar', 'Kalkaji', 'Saket', 'Malviya Nagar', 'Hauz Khas', 'Munirka', 'Ber Sarai', 'Katwaria Sarai',
                    'Kalu Sarai', 'Green Park', 'South Extension', 'Nehru Place', 'Okhla', 'Jamia Nagar',
                    'Dwarka', 'Janakpuri', 'Uttam Nagar', 'Rajouri Garden', 'Tilak Nagar', 'Pitampura', 'Rohini',
                    'Shakti Nagar', 'Satya Niketan', 'Aerocity', 'Mahipalpur',
                ],
            ],
            'Gurugram' => [
                'state' => 'Haryana',
                'state_code' => '06',
                'discom' => 'DHBVN',
                'discom_rate' => 9.00,
                'police_portal' => 'Haryana Police — Harsamay (Tenant Verification)',
                'police_portal_url' => 'https://harsamay.gov.in',
                'localities' => [
                    'Sector 14', 'Sector 15', 'Sector 29', 'Sector 38', 'Sector 39', 'Sector 40', 'Sector 43', 'Sector 44', 'Sector 45', 'Sector 46',
                    'Sector 48', 'Sector 49', 'Sector 50', 'Sector 52', 'Sector 53', 'Sector 54', 'Sector 56', 'Sector 57', 'Sector 62', 'Sector 65', 'Sector 66', 'Sector 67',
                    'DLF Phase 1', 'DLF Phase 2', 'DLF Phase 3', 'DLF Phase 4', 'DLF Phase 5',
                    'Golf Course Road', 'Golf Course Ext. Road', 'Sohna Road', 'Cyber City', 'Udyog Vihar',
                    'MG Road', 'South City', 'Sushant Lok', 'Palam Vihar', 'Nirvana Country', 'New Gurgaon', 'Manesar',
                ],
            ],
            'Noida' => [
                'state' => 'Uttar Pradesh',
                'state_code' => '09',
                'discom' => 'NPCL / PVVNL',
                'discom_rate' => 8.00,
                'police_portal' => 'UP Police — UPCOP (Tenant Verification)',
                'police_portal_url' => 'https://uppolice.gov.in',
                'localities' => [
                    'Sector 12', 'Sector 15', 'Sector 16', 'Sector 18', 'Sector 22', 'Sector 27', 'Sector 41', 'Sector 50',
                    'Sector 51', 'Sector 52', 'Sector 55', 'Sector 58', 'Sector 59', 'Sector 61', 'Sector 62', 'Sector 63',
                    'Sector 71', 'Sector 76', 'Sector 104', 'Sector 125', 'Sector 126', 'Sector 127', 'Sector 128', 'Sector 135',
                    'Sector 137', 'Sector 142', 'Noida Extension',
                ],
            ],
            'Greater Noida' => [
                'state' => 'Uttar Pradesh',
                'state_code' => '09',
                'discom' => 'NPCL',
                'discom_rate' => 8.00,
                'police_portal' => 'UP Police — UPCOP (Tenant Verification)',
                'police_portal_url' => 'https://uppolice.gov.in',
                'localities' => [
                    'Knowledge Park I', 'Knowledge Park II', 'Knowledge Park III', 'Alpha 1', 'Alpha 2', 'Beta 1', 'Beta 2',
                    'Gamma 1', 'Gamma 2', 'Delta 1', 'Pari Chowk', 'Techzone 4', 'Greater Noida West',
                ],
            ],
            'Ghaziabad' => [
                'state' => 'Uttar Pradesh',
                'state_code' => '09',
                'discom' => 'PVVNL',
                'discom_rate' => 7.50,
                'police_portal' => 'UP Police — UPCOP (Tenant Verification)',
                'police_portal_url' => 'https://uppolice.gov.in',
                'localities' => [
                    'Indirapuram', 'Vaishali', 'Vasundhara', 'Kaushambi', 'Raj Nagar Extension', 'Crossings Republik',
                    'Mohan Nagar', 'Shipra Suncity',
                ],
            ],
            'Faridabad' => [
                'state' => 'Haryana',
                'state_code' => '06',
                'discom' => 'DHBVN',
                'discom_rate' => 9.00,
                'police_portal' => 'Haryana Police — Harsamay (Tenant Verification)',
                'police_portal_url' => 'https://harsamay.gov.in',
                'localities' => [
                    'Sector 15', 'Sector 16', 'Sector 21', 'Sector 28', 'Sector 37', 'NIT', 'Old Faridabad',
                    'Greater Faridabad', 'Ballabgarh', 'Neharpar',
                ],
            ],
        ];
    }

    /** @return array<string, string> city => city for selects */
    public static function cityOptions(): array
    {
        $names = array_keys(self::cities());

        return array_combine($names, $names);
    }

    /**
     * Resolves common aliases ("Gurgaon", "New Delhi") to a configured city.
     */
    public static function normaliseCity(?string $city): ?string
    {
        if ($city === null || trim($city) === '') {
            return null;
        }

        $key = strtolower(trim($city));

        return match (true) {
            in_array($key, ['gurgaon', 'gurugram'], true) => 'Gurugram',
            in_array($key, ['delhi', 'new delhi', 'nct of delhi'], true) => 'Delhi',
            in_array($key, ['greater noida', 'gr. noida', 'greater noida west'], true) => 'Greater Noida',
            default => collect(array_keys(self::cities()))
                ->first(fn (string $name): bool => strtolower($name) === $key),
        };
    }

    /**
     * @return array{state: string, state_code: string, discom: string, discom_rate: float, police_portal: string, police_portal_url: string, localities: list<string>}|null
     */
    public static function city(?string $city): ?array
    {
        $name = self::normaliseCity($city);

        return $name !== null ? (self::cities()[$name] ?? null) : null;
    }

    /**
     * Localities for one city, or every NCR locality (prefixed with the city)
     * when no city is chosen yet.
     *
     * @return array<string, string>
     */
    public static function localityOptions(?string $city = null): array
    {
        $config = self::city($city);

        if ($config !== null) {
            return array_combine($config['localities'], $config['localities']);
        }

        $options = [];

        foreach (self::cities() as $config) {
            foreach ($config['localities'] as $locality) {
                $options[$locality] = $locality;
            }
        }

        ksort($options, SORT_NATURAL);

        return $options;
    }

    public static function stateFor(?string $city): ?string
    {
        return self::city($city)['state'] ?? null;
    }

    public static function discomFor(?string $city): string
    {
        return self::city($city)['discom'] ?? 'Local DISCOM';
    }

    public static function discomRate(?string $city = null): float
    {
        return self::city($city)['discom_rate'] ?? 9.00;
    }

    /**
     * @return array{name: string, url: string}
     */
    public static function policePortalFor(?string $city): array
    {
        $config = self::city($city);

        return [
            'name' => $config['police_portal'] ?? 'Local police station — tenant verification form',
            'url' => $config['police_portal_url'] ?? '',
        ];
    }

    /** @return list<string> */
    public static function workHubs(): array
    {
        return [
            'Cyber City', 'Cyber Hub', 'Udyog Vihar', 'Golf Course Road', 'Sohna Road', 'Manesar',
            'Noida Sector 62', 'Noida Sector 125-135', 'Noida Film City', 'Greater Noida Knowledge Park',
            'Connaught Place', 'Nehru Place', 'Okhla', 'Aerocity', 'Jasola', 'North Campus (DU)', 'South Campus (DU)',
        ];
    }

    /** @return array<string, string> */
    public static function inquirySources(): array
    {
        return [
            'walk_in' => 'Walk-in',
            'phone' => 'Phone',
            'whatsapp' => 'WhatsApp',
            'google' => 'Google / Maps',
            'instagram' => 'Instagram / Facebook',
            'referral' => 'Referral',
            'nobroker' => 'NoBroker',
            '99acres' => '99acres',
            'magicbricks' => 'MagicBricks',
            'housing' => 'Housing.com',
            'justdial' => 'Justdial',
            'stanza' => 'Stanza / Zolo',
            'corporate' => 'Corporate tie-up',
            'college' => 'College / Coaching tie-up',
            'website' => 'Website',
            'other' => 'Other',
        ];
    }

    /**
     * Sensible per-bed monthly rent band by sharing (Delhi NCR 2026).
     *
     * @return array{single: int, double: int, triple: int, quad: int}
     */
    public static function pgRentBands(): array
    {
        return ['single' => 18000, 'double' => 10000, 'triple' => 8000, 'quad' => 6500];
    }

    /**
     * GST on hotel / short-stay room tariff per unit per night (GST 2.0,
     * effective 22 Sep 2025): up to ₹7,500 → 5% (without ITC), above → 18%.
     * Long-term PG rent used as a residence is not taxed here.
     */
    public static function hotelGstPercent(float $nightlyTariff): float
    {
        if ($nightlyTariff <= 0) {
            return 0.0;
        }

        return $nightlyTariff <= 7500 ? 5.0 : 18.0;
    }

    /**
     * Annual rent above which a tenant's HRA claim needs the landlord's PAN.
     */
    public static function hraLandlordPanThreshold(): int
    {
        return 100000;
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
