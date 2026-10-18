<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\KycStatus;
use App\Enums\SharingType;
use App\Models\Guest;
use App\Models\Property;
use App\Models\Room;
use App\Models\User;
use App\Services\BedAllocationService;
use Illuminate\Database\Seeder;

class DemoSeeder extends Seeder
{
    /**
     * Render-safe: no Eloquent factories here, so this seeder runs with
     * `--no-dev` vendor (no fakerphp/faker needed) on SQLite AND Postgres.
     */
    public function run(): void
    {
        $staff = [
            ['email' => 'admin@mail.com', 'name' => 'BizStay Admin', 'role' => 'manager'],
            ['email' => 'manager@mail.com', 'name' => 'Rakesh Dutta (Property Manager)', 'role' => 'manager'],
            ['email' => 'warden@mail.com', 'name' => 'Suresh Nair (Night Warden)', 'role' => 'manager'],
        ];
        foreach ($staff as $row) {
            User::query()->firstOrCreate(
                ['email' => $row['email']],
                ['name' => $row['name'], 'password' => 'password', 'role' => $row['role']]
            );
        }
        $property = Property::query()->first();
        if (! $property) {
            // Same values as PropertyFactory (kept inline so seeding never
            // needs fakerphp/faker in production images).
            $property = Property::query()->create([
                'name' => 'BizStay Homes — Sector 44',
                'code' => 'BSH-44',
                'type' => \App\Enums\PropertyType::CoLive,
                'address' => 'Plot 12, HUDA Staff Colony',
                'locality' => 'Sector 44',
                'city' => 'Gurugram',
                'state' => 'Haryana',
                'pincode' => '122003',
                'gstin' => '06BIZST4401A1Z5',
                'upi_id' => 'bizstay44@okhdfc',
                'check_in_time' => '12:00',
                'check_out_time' => '11:00',
                'manager_name' => 'Duty Manager',
                'contact_phone' => '9999900001',
                'total_floors' => 3,
                'security_deposit_months' => 1,
                'notice_period_days' => 30,
                'billing_cycle_start_day' => 1,
                'electricity_rate_per_unit' => 9.00,
                'water_rate_per_unit' => 25.00,
                'meal_charge_per_day' => 120.00,
                'maintenance_charge_per_bed' => 0.00,
                'late_fee_percent' => 0.00,
                'is_active' => true,
            ]);
        }

        $floorPlan = [0 => ['101', '102'], 1 => ['201', '202'], 2 => ['301', '302']];
        $rooms = [];
        foreach ($floorPlan as $floor => $numbers) {
            foreach ($numbers as $i => $number) {
                $room = Room::query()->firstOrCreate(['room_number' => $number], [
                    'floor_no' => $floor,
                    'sharing_type' => $i === 0 ? SharingType::Double->value : SharingType::Triple->value,
                    'base_rent_per_bed' => $floor === 0 ? 7500 : 8500,
                    'has_ac' => $floor > 0,
                ]);
                $room->syncBedsToCapacity();
                $rooms[] = $room->refresh();
                $room->meters()->firstOrCreate(['meter_type' => 'electricity'], [
                    'meter_serial_no' => 'E-'.$number,
                    'multiplier' => 1.000,
                    'is_active' => true,
                    'installed_on' => now()->subMonths(2)->toDateString(),
                ]);
            }
        }

        $names = ['Aarav Sharma', 'Rohan Verma', 'Sahil Khan', 'Neha Gupta', 'Priya Singh'];
        $alloc = app(BedAllocationService::class);
        $beds = \App\Models\Bed::query()->where('status', 'available')->orderBy('bed_code')->get();
        foreach ($names as $i => $name) {
            if (! isset($beds[$i])) {
                break;
            }
            $guest = Guest::query()->firstOrCreate(['phone' => '98'.str_pad((string) (10000001 + $i), 8, '0', STR_PAD_LEFT)], [
                'full_name' => $name,
                'kyc_status' => KycStatus::Verified->value,
            ]);
            if (\App\Models\Booking::query()->where('guest_id', $guest->id)->live()->exists()) {
                // still make sure a tenant login exists for the app demo
            } else {
                $alloc->allocate($guest, $beds[$i]->id, [
                    'check_in_date' => now()->subDays(10 + $i)->toDateString(),
                    'monthly_rent' => (float) $beds[$i]->effectiveRent(),
                ]);
            }

            // Tenant login for the Flutter app: phone number + "password".
            User::query()->firstOrCreate(
                ['guest_id' => $guest->id],
                [
                    'name' => $guest->full_name,
                    'email' => $guest->phone.'@tenant.mail.com',
                    'password' => 'password',
                    'role' => 'tenant',
                ]
            );
        }

        $this->command?->info('Demo seed: manager admin@mail.com / password, tenant login 9810000001 / password, '.count($rooms).' rooms ready.');
    }
}
