<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\ComplaintPriority;
use App\Enums\ComplaintStatus;
use App\Enums\ExpenseCategory;
use App\Enums\InquiryStatus;
use App\Enums\KycStatus;
use App\Enums\LeaveStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Enums\SharingType;
use App\Models\Bed;
use App\Models\Booking;
use App\Models\Complaint;
use App\Models\Expense;
use App\Models\Guest;
use App\Models\Inquiry;
use App\Models\Invoice;
use App\Models\LeaveLog;
use App\Models\MeterReading;
use App\Models\Payment;
use App\Models\Property;
use App\Models\Room;
use App\Models\User;
use App\Models\UtilityMeter;
use App\Services\BedAllocationService;
use App\Services\InvoiceService;
use App\Services\ProrationEngine;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Rich, re-runnable dummy data for local development and demos.
 *
 * Everything is idempotent (firstOrCreate / guarded creates), so this seeder
 * can run next to DemoSeeder and be re-executed without duplicating rows.
 * Allocations and billing go through the domain services (BedAllocationService,
 * InvoiceService) so all derived state — bed/room status, invoice totals,
 * payment balances — is exactly what the production code would have produced.
 */
class DummyDataSeeder extends Seeder
{
    public function run(): void
    {
        $admin = $this->seedUsers();
        $property = $this->seedProperty();
        $rooms = $this->seedRoomsAndMeters();

        $this->seedGuestsStaysAndBilling($admin);
        $this->seedExpenses();
        $this->seedComplaints($admin, $rooms);
        $this->seedInquiries($admin);

        $this->command?->info('Dummy data seeded: '.$property->name.' — '
            .$rooms->count().' rooms, '.Bed::query()->count().' beds, '
            .Guest::query()->count().' guests, '.Booking::query()->live()->count().' live stays, '
            .Invoice::query()->count().' invoices, '.Payment::query()->count().' payments.');
    }

    private function seedUsers(): User
    {
        $admin = User::query()->firstOrCreate(
            ['email' => 'admin@bizstay.local'],
            ['name' => 'BizStay Admin', 'password' => 'password'],
        );

        User::query()->firstOrCreate(
            ['email' => 'manager@bizstay.local'],
            ['name' => 'Rakesh Dutta (Property Manager)', 'password' => 'password'],
        );

        User::query()->firstOrCreate(
            ['email' => 'warden@bizstay.local'],
            ['name' => 'Suresh Nair (Night Warden)', 'password' => 'password'],
        );

        return $admin;
    }

    private function seedProperty(): Property
    {
        return Property::query()->firstOrCreate(
            ['code' => 'BSH-44'],
            [
                'name' => 'BizStay Homes — Sector 44',
                'type' => 'colive',
                'address' => 'Plot 12, Huda Staff Colony',
                'locality' => 'Sector 44',
                'city' => 'Gurgaon',
                'state' => 'Haryana',
                'pincode' => '122003',
                'manager_name' => 'Rakesh Dutta',
                'contact_phone' => '9999900001',
                'contact_email' => 'manager@bizstay.local',
                'total_floors' => 3,
                'amenities' => ['wifi', 'ac', 'laundry', 'power_backup', 'cctv', 'housekeeping', 'mess'],
                'security_deposit_months' => 1,
                'notice_period_days' => 30,
                'billing_cycle_start_day' => 1,
                'electricity_rate_per_unit' => 9.00,
                'water_rate_per_unit' => 25.00,
                'meal_charge_per_day' => 120.00,
                'maintenance_charge_per_bed' => 400.00,
                'late_fee_percent' => 2.00,
                'is_active' => true,
            ],
        );
    }

    private function seedRoomsAndMeters(): \Illuminate\Support\Collection
    {
        $plan = [
            ['floor' => 0, 'number' => '101', 'sharing' => SharingType::Double, 'rent' => 7500.00, 'ac' => false],
            ['floor' => 0, 'number' => '102', 'sharing' => SharingType::Triple, 'rent' => 7000.00, 'ac' => false],
            ['floor' => 1, 'number' => '201', 'sharing' => SharingType::Double, 'rent' => 8500.00, 'ac' => true],
            ['floor' => 1, 'number' => '202', 'sharing' => SharingType::Triple, 'rent' => 8000.00, 'ac' => true],
            ['floor' => 2, 'number' => '301', 'sharing' => SharingType::Single, 'rent' => 12000.00, 'ac' => true],
            ['floor' => 2, 'number' => '302', 'sharing' => SharingType::Triple, 'rent' => 8500.00, 'ac' => true],
        ];

        $rooms = collect();

        foreach ($plan as $entry) {
            /** @var Room $room */
            $room = Room::query()->firstOrCreate(
                ['room_number' => $entry['number']],
                [
                    'floor_no' => $entry['floor'],
                    'sharing_type' => $entry['sharing']->value,
                    'base_rent_per_bed' => $entry['rent'],
                    'security_deposit_default' => 1,
                    'has_ac' => $entry['ac'],
                    'attached_bathroom' => true,
                    'has_balcony' => $entry['floor'] > 0,
                    'status' => 'available',
                ],
            );

            $room->syncBedsToCapacity();
            $rooms->push($room->refresh());

            // Electricity meter on every room; water meter only on ground floor.
            $room->meters()->firstOrCreate(
                ['meter_type' => 'electricity'],
                [
                    'meter_serial_no' => 'E-'.$entry['number'],
                    'multiplier' => 1.000,
                    'is_active' => true,
                    'installed_on' => now()->subMonths(6)->toDateString(),
                ],
            );

            if ($entry['floor'] === 0) {
                $room->meters()->firstOrCreate(
                    ['meter_type' => 'water'],
                    [
                        'meter_serial_no' => 'W-'.$entry['number'],
                        'multiplier' => 1.000,
                        'is_active' => true,
                        'installed_on' => now()->subMonths(6)->toDateString(),
                    ],
                );
            }
        }

        // One bed out of service so the dashboard has something to show.
        $spare = Bed::query()->where('status', 'available')->where('bed_code', '302-C')->first();

        if ($spare && ! $spare->bookings()->live()->exists()) {
            app(BedAllocationService::class)->takeBedOffline($spare, 'Broken cot — awaiting replacement');
        }

        return $rooms;
    }

    private function seedGuestsStaysAndBilling(User $admin): void
    {
        [$cycleStart, $cycleEnd] = app(ProrationEngine::class)->cycleContaining(now());
        $readingDate = $cycleStart->copy()->addDays(6)->lessThan(today())
            ? $cycleStart->copy()->addDays(6)
            : today();

        $profiles = [
            ['name' => 'Vikram Malhotra', 'phone' => '9876500101', 'kyc' => KycStatus::Verified, 'occupation' => 'Software Engineer', 'company' => 'Zomato', 'city' => 'Delhi', 'aadhaar' => '432187650011'],
            ['name' => 'Arjun Mehta', 'phone' => '9876500102', 'kyc' => KycStatus::Verified, 'occupation' => 'Data Analyst', 'company' => 'MakeMyTrip', 'city' => 'Jaipur', 'aadhaar' => '432187650012'],
            ['name' => 'Karan Bhatia', 'phone' => '9876500103', 'kyc' => KycStatus::Verified, 'occupation' => 'Sales Executive', 'company' => 'HDFC Bank', 'city' => 'Ludhiana', 'aadhaar' => '432187650013'],
            ['name' => 'Rahul Khanna', 'phone' => '9876500104', 'kyc' => KycStatus::Verified, 'occupation' => 'UX Designer', 'company' => 'Swiggy', 'city' => 'Mumbai', 'aadhaar' => '432187650014'],
            ['name' => 'Aditya Rane', 'phone' => '9876500105', 'kyc' => KycStatus::Verified, 'occupation' => 'Consultant', 'company' => 'Deloitte', 'city' => 'Pune', 'aadhaar' => '432187650015'],
            ['name' => 'Mohit Chauhan', 'phone' => '9876500106', 'kyc' => KycStatus::Verified, 'occupation' => 'Chartered Accountant', 'company' => 'Self-employed', 'city' => 'Dehradun', 'aadhaar' => '432187650016'],
            ['name' => 'Sanjay Iyer', 'phone' => '9876500107', 'kyc' => KycStatus::Verified, 'occupation' => 'Product Manager', 'company' => 'Paytm', 'city' => 'Chennai', 'aadhaar' => '432187650017'],
            ['name' => 'Deepak Yadav', 'phone' => '9876500108', 'kyc' => KycStatus::Verified, 'occupation' => 'QA Engineer', 'company' => 'Info Edge', 'city' => 'Noida', 'aadhaar' => '432187650018'],
            ['name' => 'Nikhil Bansal', 'phone' => '9876500109', 'kyc' => KycStatus::Verified, 'occupation' => 'Marketing Lead', 'company' => 'Wakefit', 'city' => 'Bengaluru', 'aadhaar' => '432187650019'],
            ['name' => 'Harsh Vardhan', 'phone' => '9876500110', 'kyc' => KycStatus::Verified, 'occupation' => 'Doctor (Resident)', 'company' => 'Medanta', 'city' => 'Lucknow', 'aadhaar' => '432187650020'],
            ['name' => 'Ravi Tiwari', 'phone' => '9876500111', 'kyc' => KycStatus::Pending, 'occupation' => 'Intern', 'company' => "Byju's", 'city' => 'Varanasi', 'aadhaar' => '432187650021'],
            ['name' => 'Amit Dubey', 'phone' => '9876500112', 'kyc' => KycStatus::Verified, 'occupation' => 'Trainer', 'company' => 'NIIT', 'city' => 'Kanpur', 'aadhaar' => '432187650022'],
        ];

        $alloc = app(BedAllocationService::class);

        foreach ($profiles as $i => $profile) {
            /** @var Guest $guest */
            $guest = Guest::query()->firstOrCreate(
                ['phone' => $profile['phone']],
                [
                    'full_name' => $profile['name'],
                    'alt_phone' => '98'.str_pad((string) (76000100 + $i), 8, '0', STR_PAD_LEFT),
                    'email' => strtolower(str_replace(' ', '.', $profile['name'])).'@example.com',
                    'kyc_status' => $profile['kyc']->value,
                    'gender' => 'male',
                    'date_of_birth' => Carbon::parse('1993-0'.(($i % 9) + 1).'-1'.($i % 9)),
                    'permanent_address' => 'House '.($i + 10).', Shivaji Nagar, '.$profile['city'],
                    'home_city' => $profile['city'],
                    'occupation' => $profile['occupation'],
                    'company_name' => $profile['company'],
                    'emergency_contact_name' => 'Mr. '.explode(' ', $profile['name'])[0].' (Father)',
                    'emergency_contact_phone' => '98'.str_pad((string) (88000100 + $i), 8, '0', STR_PAD_LEFT),
                    'emergency_contact_relation' => 'Father',
                    'is_blacklisted' => false,
                ],
            );

            if (! $guest->adhaar_number_hash) {
                $guest->setAadhaarNumber($profile['aadhaar']);
                $guest->kyc_verified_at = $profile['kyc'] === KycStatus::Verified ? now()->subDays(30) : null;
                $guest->save();
            }

            // Pending-KYC guests stay in the verification queue, never allocated.
            if ($guest->kyc_status !== KycStatus::Verified) {
                continue;
            }

            if (Booking::query()->where('guest_id', $guest->id)->live()->exists()) {
                continue; // Already staying (e.g. seeded by DemoSeeder) — idempotent re-run.
            }

            $bed = Bed::query()->where('status', 'available')->orderBy('bed_code')->first();

            if (! $bed) {
                break; // Building is full.
            }

            try {
                $booking = $alloc->allocate($guest, $bed->id, [
                    'check_in_date' => now()->subDays(20 + $i * 3)->toDateString(),
                    'monthly_rent' => $bed->effectiveRent(),
                    'rent_due_day' => 5,
                    'food_included' => $i !== 5, // one guest is food-excluded
                ]);
            } catch (\Throwable $e) {
                $this->command?->warn('Skipped allocation for '.$guest->full_name.': '.$e->getMessage());

                continue;
            }

            // One guest has served notice — still billed, still on the bed.
            if ($i === 3) {
                $alloc->putOnNotice($booking, now()->subDays(2));
            }
        }

        $this->seedLeaveLogs($admin, $cycleStart);
        $this->seedMeterReadings($readingDate);
        $this->seedInvoicesAndPayments($admin);
    }

    private function seedLeaveLogs(User $admin, Carbon $cycleStart): void
    {
        $booking = Booking::query()->live()->orderBy('id')->first();

        if (! $booking) {
            return;
        }

        if (! LeaveLog::query()->where('booking_id', $booking->id)->exists()) {
            $leaveStart = $cycleStart->copy()->addDays(2);
            LeaveLog::query()->create([
                'booking_id' => $booking->id,
                'start_date' => $leaveStart->toDateString(),
                'end_date' => $leaveStart->copy()->addDays(4)->toDateString(),
                'total_days' => 5,
                'food_opt_out' => true,
                'reason' => 'Family function at home town',
                'status' => LeaveStatus::Approved->value,
                'approved_by' => $admin->id,
                'approved_at' => now()->subDays(3),
            ]);
        }

        if (LeaveLog::query()->where('booking_id', $booking->id)->count() < 2) {
            LeaveLog::query()->create([
                'booking_id' => $booking->id,
                'start_date' => now()->addDays(10)->toDateString(),
                'end_date' => now()->addDays(14)->toDateString(),
                'total_days' => 5,
                'food_opt_out' => false,
                'reason' => 'Personal work',
                'status' => LeaveStatus::Requested->value,
            ]);
        }
    }

    private function seedMeterReadings(Carbon $readingDate): void
    {
        $managerId = User::query()->where('email', 'manager@bizstay.local')->value('id');
        $meters = UtilityMeter::query()->active()->get();

        foreach ($meters as $meter) {
            $opening = $meter->latestReadingValue();

            // whereDate: SQLite stores date columns with a time component, so a
            // plain `firstOrCreate(['reading_date' => 'YYYY-MM-DD'])` would miss.
            $first = MeterReading::query()
                ->where('meter_id', $meter->id)
                ->whereDate('reading_date', $readingDate->toDateString())
                ->first();

            if (! $first) {
                $first = MeterReading::query()->create([
                    'meter_id' => $meter->id,
                    'reading_date' => $readingDate->toDateString(),
                    'current_reading' => $opening + 120.0,
                    'recorded_by' => $managerId,
                    'notes' => 'Seeded mid-cycle reading',
                ]);
            }

            // A second, later reading so the next cycle already has data too.
            $secondDate = $readingDate->copy()->addDays(10);
            if ($secondDate->lessThan(today())
                && ! MeterReading::query()
                    ->where('meter_id', $meter->id)
                    ->whereDate('reading_date', $secondDate->toDateString())
                    ->exists()
            ) {
                MeterReading::query()->create([
                    'meter_id' => $meter->id,
                    'reading_date' => $secondDate->toDateString(),
                    'current_reading' => (float) $first->current_reading + 140.0,
                    'recorded_by' => $managerId,
                    'notes' => 'Seeded late-cycle reading',
                ]);
            }
        }
    }

    private function seedInvoicesAndPayments(User $admin): void
    {
        $invoices = app(InvoiceService::class)->generateForCycle();

        $methods = [PaymentMethod::Upi, PaymentMethod::NetBanking, PaymentMethod::Cash, PaymentMethod::Card];
        $sequence = 0;

        foreach ($invoices as $index => $invoice) {
            if ((float) $invoice->total_due <= 0.0) {
                continue;
            }

            // Mix the ledger: 3 of 5 fully settled, 1 of 5 partial, 1 of 5 unpaid.
            $mode = $index % 5;

            if ($mode < 3) {
                $amount = (float) $invoice->total_due;
            } elseif ($mode === 3) {
                $amount = round((float) $invoice->total_due / 2, 2);
            } else {
                continue;
            }

            $method = $methods[$sequence % count($methods)];
            $sequence++;

            $transactionId = in_array($method, [PaymentMethod::Upi, PaymentMethod::NetBanking, PaymentMethod::Card], true)
                ? 'TXN-SEED-'.strtoupper(Str::random(10))
                : null;

            $existing = Payment::query()
                ->where('invoice_id', $invoice->id)
                ->where('amount', $amount)
                ->where('payment_method', $method->value)
                ->exists();

            if (! $existing) {
                Payment::query()->create([
                    'invoice_id' => $invoice->id,
                    'booking_id' => $invoice->booking_id,
                    'payment_type' => (float) $invoice->rent_amount > 0.0 ? PaymentType::Rent->value : PaymentType::Utility->value,
                    'amount' => $amount,
                    'payment_method' => $method->value,
                    'transaction_id' => $transactionId,
                    'status' => PaymentStatus::Success->value,
                    'paid_on' => now()->subDays(($sequence % 12) + 1)->toDateString(),
                    'collected_by' => $admin->id,
                    'notes' => 'Seeded collection',
                ]);
            }

            $invoice->refresh();
        }
    }

    private function seedExpenses(): void
    {
        $rows = [
            [ExpenseCategory::Salary->value, 45000.00, now()->startOfMonth()->addDays(1), 'Staff payroll (this month)', 'netbanking'],
            [ExpenseCategory::Electricity->value, 18500.00, now()->startOfMonth()->addDays(4), 'DHBVN monthly bill', 'netbanking'],
            [ExpenseCategory::Internet->value, 2499.00, now()->startOfMonth()->addDays(2), 'JioFiber 300Mbps', 'upi'],
            [ExpenseCategory::Housekeeping->value, 6200.00, now()->startOfMonth()->addDays(6), 'Deep cleaning contract', 'cash'],
            [ExpenseCategory::Repairs->value, 3400.00, now()->startOfMonth()->addDays(9), 'Plumbing — 202 bathroom', 'cash'],
            [ExpenseCategory::Gas->value, 1100.00, now()->startOfMonth()->addDays(8), 'LPG refill — kitchen', 'cash'],
            [ExpenseCategory::Water->value, 4500.00, now()->startOfMonth()->addDays(11), 'Water tanker supply', 'cash'],
            [ExpenseCategory::Salary->value, 45000.00, now()->subMonthNoOverflow()->startOfMonth()->addDays(1), 'Staff payroll (previous month)', 'netbanking'],
            [ExpenseCategory::Electricity->value, 21000.00, now()->subMonthNoOverflow()->startOfMonth()->addDays(5), 'DHBVN monthly bill (previous month)', 'netbanking'],
        ];

        foreach ($rows as [$category, $amount, $spentOn, $vendor, $method]) {
            Expense::query()->firstOrCreate(
                ['vendor' => $vendor, 'spent_on' => Carbon::parse($spentOn)->toDateString()],
                [
                    'category' => $category,
                    'amount' => $amount,
                    'payment_method' => $method,
                    'notes' => 'Seeded operating expense',
                ],
            );
        }
    }

    private function seedComplaints(User $admin, \Illuminate\Support\Collection $rooms): void
    {
        $guests = Guest::query()->staying()->orderBy('id')->get();

        $rows = [
            ['title' => 'AC not cooling in room 201', 'category' => 'electrical', 'priority' => ComplaintPriority::High->value, 'status' => ComplaintStatus::InProgress->value, 'room' => '201', 'resolution' => null],
            ['title' => 'Geyser leaking in 102 bathroom', 'category' => 'plumbing', 'priority' => ComplaintPriority::Medium->value, 'status' => ComplaintStatus::Open->value, 'room' => '102', 'resolution' => null],
            ['title' => 'Wi-Fi dropping every evening', 'category' => 'internet', 'priority' => ComplaintPriority::Medium->value, 'status' => ComplaintStatus::Resolved->value, 'room' => '202', 'resolution' => 'Router firmware updated by ISP technician'],
            ['title' => 'Room not cleaned for 3 days', 'category' => 'housekeeping', 'priority' => ComplaintPriority::Low->value, 'status' => ComplaintStatus::Resolved->value, 'room' => '101', 'resolution' => 'Extra housekeeping shift assigned'],
            ['title' => 'Main gate latch broken', 'category' => 'security', 'priority' => ComplaintPriority::High->value, 'status' => ComplaintStatus::Open->value, 'room' => null, 'resolution' => null],
            ['title' => 'Dinner served cold twice this week', 'category' => 'food', 'priority' => ComplaintPriority::Medium->value, 'status' => ComplaintStatus::Closed->value, 'room' => '302', 'resolution' => 'Kitchen timing adjusted; guest confirmed satisfied'],
        ];

        foreach ($rows as $i => $row) {
            if (Complaint::query()->where('title', $row['title'])->exists()) {
                continue;
            }

            $room = $row['room'] !== null ? $rooms->firstWhere('room_number', $row['room']) : null;
            $isDone = in_array($row['status'], [ComplaintStatus::Resolved->value, ComplaintStatus::Closed->value], true);

            Complaint::query()->create([
                'guest_id' => $guests->isNotEmpty() ? $guests[$i % $guests->count()]->id : null,
                'room_id' => $room?->id,
                'title' => $row['title'],
                'description' => 'Seeded maintenance request for demo purposes.',
                'category' => $row['category'],
                'priority' => $row['priority'],
                'status' => $row['status'],
                'assigned_to' => $row['status'] === ComplaintStatus::Open->value ? null : $admin->id,
                'resolved_at' => $isDone ? now()->subDays(1) : null,
                'resolution_notes' => $row['resolution'],
                'created_at' => now()->subDays(3 + $i),
            ]);
        }
    }

    private function seedInquiries(User $admin): void
    {
        $rows = [
            ['name' => 'Tushar Sethi', 'phone' => '9811100201', 'source' => 'walk_in', 'budget' => 8500.00, 'status' => InquiryStatus::New, 'follow_up' => now()->addDays(1)],
            ['name' => 'Manish Aggarwal', 'phone' => '9811100202', 'source' => '99acres', 'budget' => 9000.00, 'status' => InquiryStatus::Contacted, 'follow_up' => now()->addDays(2)],
            ['name' => 'Pranav Kohli', 'phone' => '9811100203', 'source' => 'facebook', 'budget' => 12000.00, 'status' => InquiryStatus::Visited, 'follow_up' => now()->addDays(3)],
            ['name' => 'Saurabh Jain', 'phone' => '9811100204', 'source' => 'referral', 'budget' => 7500.00, 'status' => InquiryStatus::Converted, 'follow_up' => null],
            ['name' => 'Ritesh Malviya', 'phone' => '9811100205', 'source' => 'nobroker', 'budget' => 8000.00, 'status' => InquiryStatus::Cancelled, 'follow_up' => null],
        ];

        foreach ($rows as $row) {
            /** @var Inquiry $inquiry */
            $inquiry = Inquiry::query()->firstOrCreate(
                ['phone' => $row['phone']],
                [
                    'name' => $row['name'],
                    'email' => strtolower(str_replace(' ', '.', $row['name'])).'@example.com',
                    'gender' => 'male',
                    'source' => $row['source'],
                    'budget' => $row['budget'],
                    'interested_in' => 'Double sharing with AC',
                    'preferred_move_in' => now()->addDays(7)->toDateString(),
                    'follow_up_date' => $row['follow_up']?->toDateString(),
                    'status' => $row['status']->value,
                    'assigned_to' => $admin->id,
                    'message' => 'Seeded lead for demo purposes.',
                ],
            );

            // Link the converted lead to a real guest + audit trail on the stay.
            if ($row['status'] === InquiryStatus::Converted && ! $inquiry->converted_guest_id) {
                $guest = Guest::query()->where('phone', '9876500112')->first();

                if ($guest) {
                    $inquiry->forceFill(['converted_guest_id' => $guest->id])->save();

                    Booking::query()
                        ->where('guest_id', $guest->id)
                        ->whereNull('converted_inquiry_id')
                        ->update([
                            'converted_inquiry_id' => $inquiry->id,
                            'assigned_marketer' => $admin->id,
                        ]);
                }
            }
        }
    }
}
