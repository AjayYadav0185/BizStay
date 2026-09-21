<?php

namespace Database\Seeders;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Enums\RoomStatus;
use App\Models\Booking;
use App\Models\Complaint;
use App\Models\Expense;
use App\Models\Payment;
use App\Models\Property;
use App\Models\Room;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::create([
            'name' => 'Ajay (Manager)',
            'email' => 'admin@bizstay.in',
            'password' => 'password',
        ]);

        $properties = $this->seedProperties();

        $this->seedTenantsAndFinance($admin, $properties);
        $this->seedOps($admin, $properties);

        $this->command->info('BizStay demo data seeded. Login: admin@bizstay.in / password');
    }

    private function seedProperties(): array
    {
        $amenities = ['High-Speed WiFi', 'AC Rooms', 'Power Backup', 'Meals Included', 'Laundry', 'CCTV Security', 'Hot Water', 'Housekeeping', 'RO Water', 'Common TV'];

        $data = [
            ['BizStay Homes — Sector 44', 'BSH-44', 'colive', 'H.No 12, Huda Market Road, Sector 44', 'Sector 44 (near Cyber Hub)', '122003', 'Ramesh Kumar', 3, [['101', 1, 3, 9500, true], ['102', 1, 2, 12000, true], ['201', 2, 2, 13000, false], ['202', 2, 1, 16000, true]]],
            ['BizStay Girls Nest — Sector 56', 'BGN-56', 'girls', 'Plot 8, Gol Chakkar, Sector 56', 'Sector 56 (Golf Course Road)', '122011', 'Sunita Devi', 2, [['101', 1, 2, 14000, true], ['102', 1, 3, 11000, false], ['201', 2, 2, 15000, true]]],
            ['BizStay Comfort Stay — DLF Phase 3', 'BCS-03', 'boys', 'R-2, Vyapar Kendra Road, DLF Phase 3', 'DLF Phase 3 (Cyber City)', '122002', 'Deepak Singh', 2, [['G1', 0, 4, 8500, false], ['G2', 0, 3, 9500, false], ['101', 1, 2, 12500, true]]],
        ];

        $properties = [];

        foreach ($data as [$name, $code, $type, $address, $locality, $pincode, $manager, $floors, $rooms]) {
            $property = Property::create([
                'name' => $name, 'code' => $code, 'type' => $type,
                'address' => $address, 'locality' => $locality,
                'city' => 'Gurgaon', 'state' => 'Haryana', 'pincode' => $pincode,
                'manager_name' => $manager,
                'contact_phone' => '98'.random_int(10000000, 99999999),
                'total_floors' => $floors, 'amenities' => $amenities,
                'security_deposit_months' => 1, 'notice_period_days' => 30, 'is_active' => true,
            ]);

            foreach ($rooms as [$number, $floor, $sharing, $rent, $ac]) {
                $room = Room::create([
                    'property_id' => $property->id, 'room_number' => $number,
                    'floor' => $floor, 'sharing_capacity' => $sharing,
                    'monthly_rent' => $rent, 'security_deposit' => $rent,
                    'has_ac' => $ac, 'attached_bathroom' => true,
                    'status' => RoomStatus::Available,
                ]);

                foreach (range(1, $sharing) as $i) {
                    $room->beds()->create(['bed_number' => chr(64 + $i)]);
                }
            }

            $properties[] = $property;
        }

        return $properties;
    }

    private function seedTenantsAndFinance(User $admin, array $properties): void
    {
        // [propertyIdx, name, gender, phone, company, occupation, homeCity]
        $people = [
            [0, 'Rohit Sharma', 'male', '9810012345', 'TCS Cyber City', 'Software Engineer', 'Patna'],
            [0, 'Amit Verma', 'male', '9810022345', 'Genpact Udyog Vihar', 'Analyst', 'Lucknow'],
            [0, 'Priya Nair', 'female', '9810032345', 'EXL Sector 44', 'Consultant', 'Kochi'],
            [1, 'Sneha Gupta', 'female', '9810042345', 'MakeMyTrip Sector 56', 'HR Executive', 'Delhi'],
            [1, 'Anjali Singh', 'female', '9810052345', 'Cognizant Golf Course Rd', 'Developer', 'Jaipur'],
            [2, 'Vikas Yadav', 'male', '9810062345', 'Zomato Sector 18', 'Ops Executive', 'Agra'],
            [2, 'Suresh Reddy', 'male', '9810072345', 'Accenture Cyber Hub', 'Team Lead', 'Hyderabad'],
            [2, 'Manoj Kumar', 'male', '9810082345', 'Amazon DLF Phase 3', 'Fulfilment Staff', 'Varanasi'],
        ];

        foreach ($people as $i => [$pIdx, $name, $gender, $phone, $company, $occupation, $homeCity]) {
            $property = $properties[$pIdx];

            $bed = \App\Models\Bed::where('status', 'vacant')
                ->whereHas('room', fn ($q) => $q->where('property_id', $property->id))
                ->first();

            if (! $bed) {
                continue;
            }

            $status = ($i === 5) ? 'notice_period' : 'active';

            $tenant = Tenant::create([
                'property_id' => $property->id,
                'bed_id' => $bed->id,
                'full_name' => $name,
                'phone' => $phone,
                'email' => strtolower(str_replace(' ', '.', $name)).'@gmail.com',
                'gender' => $gender,
                'id_proof_type' => 'aadhaar',
                'id_proof_number' => 'XXXX XXXX '.random_int(1000, 9999),
                'kyc_verified' => $i % 3 !== 0,
                'permanent_address' => "H.No ".random_int(1, 200).", {$homeCity}",
                'home_city' => $homeCity,
                'occupation' => $occupation,
                'company_name' => $company,
                'emergency_contact_name' => 'Family Member',
                'emergency_contact_phone' => '99'.random_int(100000000, 999999999),
                'monthly_rent' => $bed->room->monthly_rent,
                'security_deposit' => $bed->room->monthly_rent,
                'joining_date' => Carbon::now()->subMonths(random_int(2, 8))->startOfMonth(),
                'rent_due_day' => 5,
                'status' => $status,
                'notice_date' => $status === 'notice_period' ? Carbon::now()->subDays(5) : null,
            ]);

            $this->seedPayments($admin, $tenant, $i);
        }
    }

    private function seedPayments(User $admin, Tenant $tenant, int $i): void
    {
        Payment::create([
            'property_id' => $tenant->property_id, 'tenant_id' => $tenant->id,
            'type' => PaymentType::Deposit, 'amount' => $tenant->security_deposit,
            'status' => PaymentStatus::Paid, 'method' => PaymentMethod::Upi,
            'paid_at' => $tenant->joining_date, 'collected_by' => $admin->id,
        ]);

        $lastMonth = Carbon::now()->subMonthNoOverflow()->startOfMonth();

        Payment::create([
            'property_id' => $tenant->property_id, 'tenant_id' => $tenant->id,
            'type' => PaymentType::Rent, 'amount' => $tenant->monthly_rent,
            'period_month' => $lastMonth, 'due_date' => $lastMonth->copy()->day(5),
            'status' => PaymentStatus::Paid, 'method' => PaymentMethod::Upi,
            'paid_at' => $lastMonth->copy()->day(random_int(3, 6)), 'collected_by' => $admin->id,
        ]);

        $thisMonth = Carbon::now()->startOfMonth();
        $currentStatus = ($i % 3 === 0) ? PaymentStatus::Overdue : PaymentStatus::Pending;

        Payment::create([
            'property_id' => $tenant->property_id, 'tenant_id' => $tenant->id,
            'type' => PaymentType::Rent, 'amount' => $tenant->monthly_rent,
            'period_month' => $thisMonth, 'due_date' => $thisMonth->copy()->day(5),
            'status' => $currentStatus,
        ]);
    }

    private function seedOps(User $admin, array $properties): void
    {
        $leads = [
            ['Aman Tiwari', '99acres'], ['Neha Jain', 'nobroker'],
            ['Karan Malhotra', 'walk_in'], ['Pooja Rani', 'reference'],
        ];

        foreach ($leads as $i => [$name, $source]) {
            Booking::create([
                'property_id' => $properties[$i % 3]->id,
                'name' => $name,
                'phone' => '70'.random_int(100000000, 999999999),
                'gender' => $i % 2 === 0 ? 'male' : 'female',
                'source' => $source,
                'budget' => random_int(9, 16) * 1000,
                'interested_in' => '2 Sharing AC',
                'preferred_move_in' => Carbon::now()->addDays(random_int(2, 20)),
                'follow_up_date' => Carbon::now()->addDays(random_int(-3, 4)),
                'status' => ['new', 'contacted', 'visited', 'new'][$i],
                'assigned_to' => $admin->id,
                'message' => 'Interested in PG near Cyber Hub / Sector 44 area.',
            ]);
        }

        $expenseRows = [
            [0, 'electricity', 18450, 'DHBVN Electricity Board'],
            [0, 'internet', 2160, 'Airtel Broadband'],
            [1, 'housekeeping', 8000, 'CleanCo Services'],
            [1, 'gas', 3100, 'HP Gas Agency'],
            [2, 'repairs', 4500, 'Geyser repair — Gurgaon Electricians'],
            [2, 'water', 1200, 'Tanker supply'],
        ];

        foreach ($expenseRows as [$pIdx, $category, $amount, $vendor]) {
            Expense::create([
                'property_id' => $properties[$pIdx]->id,
                'category' => $category,
                'amount' => $amount,
                'spent_on' => Carbon::now()->subDays(random_int(1, 25)),
                'vendor' => $vendor,
                'method' => PaymentMethod::Upi,
            ]);
        }

        $complaintRows = [
            [0, 'AC not cooling in Room 201', 'electrical', 'high', 'open', 'Ramesh (Electrician)', null],
            [0, 'WiFi slow on 2nd floor', 'internet', 'medium', 'in_progress', 'Airtel Support', null],
            [1, 'Water leakage in bathroom', 'plumbing', 'high', 'open', null, null],
            [1, 'Room cleaning missed twice', 'housekeeping', 'low', 'resolved', 'CleanCo', 'Discussed with housekeeping staff; schedule fixed.'],
            [2, 'Geyser not working', 'electrical', 'medium', 'in_progress', 'Gurgaon Electricians', null],
            [2, 'Food quality on Sunday', 'food', 'low', 'resolved', 'Cook (Raju)', 'Menu revised; added paneer items.'],
        ];

        foreach ($complaintRows as [$pIdx, $title, $category, $priority, $status, $assigned, $resolution]) {
            $tenant = Tenant::where('property_id', $properties[$pIdx]->id)->first();

            Complaint::create([
                'property_id' => $properties[$pIdx]->id,
                'tenant_id' => $tenant?->id,
                'title' => $title,
                'description' => 'Reported by resident. Please check at the earliest.',
                'category' => $category,
                'priority' => $priority,
                'status' => $status,
                'assigned_to' => $assigned,
                'resolved_at' => $status === 'resolved' ? Carbon::now()->subDays(2) : null,
                'resolution_notes' => $resolution,
            ]);
        }
    }
}
