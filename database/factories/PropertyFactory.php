<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\PropertyType;
use App\Models\Property;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Property>
 */
class PropertyFactory extends Factory
{
    protected $model = Property::class;

    public function definition(): array
    {
        return [
            'name' => 'BizStay Homes — Sector 44',
            'code' => 'BSH-44',
            'type' => PropertyType::CoLive->value,
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
        ];
    }
}
