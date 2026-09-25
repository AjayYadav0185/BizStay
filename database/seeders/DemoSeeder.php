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
    public function run(): void
    {
        $staff = [
            ['email' => 'admin@bizstay.local', 'name' => 'BizStay Admin', 'role' => 'manager'],
            ['email' => 'manager@bizstay.local', 'name' => 'Rakesh Dutta (Property Manager)', 'role' => 'manager'],
            ['email' => 'warden@bizstay.local', 'name' => 'Suresh Nair (Night Warden)', 'role' => 'manager'],
        ];
        foreach ($staff as $row) {
            User::query()->firstOrCreate(
                ['email' => $row['email']],
                ['name' => $row['name'], 'password' => 'password', 'role' => $row['role']]
            );
        }
        $property = Property::query()->first();
        if (! $property) {
            $property = Property::factory()->create();
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
                    'email' => $guest->phone.'@tenant.bizstay.local',
                    'password' => 'password',
                    'role' => 'tenant',
                ]
            );
        }

        $this->command?->info('Demo seed: manager admin@bizstay.local / password, tenant login 9810000001 / password, '.count($rooms).' rooms ready.');
    }
}
