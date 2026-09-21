<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Properties = PG buildings (e.g. BizStay Homes, Sector 44, Gurgaon)
        Schema::create('properties', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique(); // e.g. BSH-44
            $table->string('type')->default('colive'); // boys / girls / colive
            $table->string('address');
            $table->string('locality')->nullable(); // Sector 44, DLF Phase 3, Golf Course Road...
            $table->string('city')->default('Gurgaon');
            $table->string('state')->default('Haryana');
            $table->string('pincode', 10)->nullable();
            $table->string('manager_name')->nullable();
            $table->string('contact_phone', 20)->nullable();
            $table->unsignedTinyInteger('total_floors')->default(1);
            $table->json('amenities')->nullable(); // WiFi, AC, Power Backup, Meals...
            $table->unsignedTinyInteger('security_deposit_months')->default(1);
            $table->unsignedTinyInteger('notice_period_days')->default(30);
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('rooms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->string('room_number');
            $table->unsignedTinyInteger('floor')->default(0);
            $table->unsignedTinyInteger('sharing_capacity')->default(2); // 1/2/3/4 sharing
            $table->decimal('monthly_rent', 10, 2);
            $table->decimal('security_deposit', 10, 2)->nullable();
            $table->boolean('has_ac')->default(false);
            $table->boolean('attached_bathroom')->default(true);
            $table->string('status')->default('available'); // available / full / maintenance
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['property_id', 'room_number']);
        });

        Schema::create('beds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_id')->constrained()->cascadeOnDelete();
            $table->string('bed_number'); // e.g. A, B, C, D
            $table->string('status')->default('vacant'); // vacant / occupied / blocked
            $table->decimal('monthly_rent', 10, 2)->nullable(); // overrides room rent
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['room_id', 'bed_number']);
        });

        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained()->restrictOnDelete();
            $table->foreignId('bed_id')->nullable()->constrained()->nullOnDelete();
            $table->string('full_name');
            $table->string('phone', 20);
            $table->string('email')->nullable();
            $table->string('gender')->default('male'); // male / female / other
            $table->string('id_proof_type')->default('aadhaar'); // aadhaar / pan / passport / dl
            $table->string('id_proof_number', 50)->nullable();
            $table->string('id_proof_file')->nullable();
            $table->boolean('kyc_verified')->default(false);
            $table->text('permanent_address')->nullable();
            $table->string('home_city')->nullable();
            $table->string('occupation')->nullable();
            $table->string('company_name')->nullable(); // e.g. working in Cyber Hub / Udyog Vihar
            $table->string('emergency_contact_name')->nullable();
            $table->string('emergency_contact_phone', 20)->nullable();
            $table->decimal('monthly_rent', 10, 2);
            $table->decimal('security_deposit', 10, 2)->default(0);
            $table->date('joining_date');
            $table->unsignedTinyInteger('rent_due_day')->default(5);
            $table->date('notice_date')->nullable();
            $table->date('vacated_date')->nullable();
            $table->string('status')->default('active'); // inquiry / prospective / active / notice_period / vacated
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['property_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenants');
        Schema::dropIfExists('beds');
        Schema::dropIfExists('rooms');
        Schema::dropIfExists('properties');
    }
};
