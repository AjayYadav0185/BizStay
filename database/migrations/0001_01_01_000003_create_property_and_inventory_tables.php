<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Module A — Property profile + Inventory & Layout.
 *
 * BizStay runs ONE physical building, so `properties` is a singleton profile
 * table (exactly one row) that carries the operational + billing settings used
 * by the invoicing engine. No other table carries a property_id: the domain is
 * deliberately single-property, and keeping the setting in one place means
 * adding $table->foreignId('property_id') later is a purely additive change.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('properties', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('code', 30)->unique();
            $table->string('type', 20)->default('colive'); // boys / girls / colive
            $table->string('address');
            $table->string('locality')->nullable();
            $table->string('city')->default('Gurgaon');
            $table->string('state')->default('Haryana');
            $table->string('pincode', 10)->nullable();
            $table->string('manager_name')->nullable();
            $table->string('contact_phone', 20)->nullable();
            $table->string('contact_email')->nullable();
            $table->unsignedTinyInteger('total_floors')->default(1);
            $table->json('amenities')->nullable();

            // Commercial / billing rules (used by InvoiceService + ProrationEngine)
            $table->unsignedTinyInteger('security_deposit_months')->default(1);
            $table->unsignedSmallInteger('notice_period_days')->default(30);
            $table->unsignedTinyInteger('billing_cycle_start_day')->default(1);
            $table->decimal('electricity_rate_per_unit', 8, 2)->default(9.00);
            $table->decimal('water_rate_per_unit', 8, 2)->default(25.00);
            $table->decimal('meal_charge_per_day', 8, 2)->default(120.00);
            $table->decimal('maintenance_charge_per_bed', 10, 2)->default(0.00);
            $table->decimal('late_fee_percent', 5, 2)->default(0.00);

            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('rooms', function (Blueprint $table): void {
            $table->id();
            $table->unsignedTinyInteger('floor_no')->default(0);
            $table->string('room_number', 20)->unique();
            $table->string('sharing_type', 20)->default('double'); // single/double/triple/quad
            $table->decimal('base_rent_per_bed', 12, 2);
            $table->unsignedTinyInteger('security_deposit_default')->nullable();
            $table->boolean('has_ac')->default(false);
            $table->boolean('attached_bathroom')->default(true);
            $table->boolean('has_balcony')->default(false);
            $table->string('status', 20)->default('available');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Occupancy dashboard + room grid filter by floor heavily.
            $table->index(['floor_no', 'status']);
            $table->index('sharing_type');
        });

        Schema::create('beds', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('room_id')->constrained()->cascadeOnDelete();
            $table->string('bed_code', 20)->unique(); // 101-A, 101-B
            $table->unsignedTinyInteger('position')->default(1); // 1 = A, 2 = B ...
            $table->string('status', 20)->default('available'); // available/occupied/maintenance/reserved
            $table->decimal('rent_override', 12, 2)->nullable(); // premium/discount bed
            $table->date('maintenance_since')->nullable();
            $table->text('maintenance_note')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Hot path: "give me an allocatable bed", "occupancy of this room".
            $table->index(['room_id', 'status']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('beds');
        Schema::dropIfExists('rooms');
        Schema::dropIfExists('properties');
    }
};
