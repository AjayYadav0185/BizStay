<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Module B — Tenant & Stay Management.
 *
 * guests    : the person (single source of truth, reusable across stays)
 * bookings  : a stay contract binding one guest to one bed
 * leave_logs: absences, with the food_opt_out flag that drives mess deductions
 * inquiries : lead pipeline (walk-ins / portals) that converts into a booking
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guests', function (Blueprint $table): void {
            $table->id();
            $table->string('full_name');
            $table->string('phone', 20)->unique();
            $table->string('alt_phone', 20)->nullable();
            $table->string('email')->nullable();

            // Aadhaar is never stored in clear text: only a SHA-256 digest for
            // duplicate detection plus the last four digits for display.
            $table->string('adhaar_number_hash', 64)->nullable()->unique();
            $table->string('adhaar_last4', 4)->nullable();
            $table->string('id_proof_type', 20)->default('aadhaar'); // aadhaar/pan/passport/dl
            $table->string('id_proof_number', 50)->nullable();

            $table->string('kyc_status', 20)->default('pending'); // pending/verified/rejected
            $table->json('kyc_documents')->nullable(); // ["path/to/aadhaar.pdf", ...]
            $table->timestamp('kyc_verified_at')->nullable();
            $table->string('kyc_remarks')->nullable();

            $table->string('gender', 10)->default('male');
            $table->date('date_of_birth')->nullable();
            $table->text('permanent_address')->nullable();
            $table->string('home_city')->nullable();
            $table->string('occupation')->nullable();
            $table->string('company_name')->nullable();

            $table->string('emergency_contact_name')->nullable();
            $table->string('emergency_contact_phone', 20)->nullable();
            $table->string('emergency_contact_relation', 40)->nullable();

            $table->boolean('is_blacklisted')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('kyc_status');
            $table->index('full_name');
        });

        Schema::create('bookings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('guest_id')->constrained()->restrictOnDelete();
            // Beds are never hard-deleted while historical bookings point at them.
            $table->foreignId('bed_id')->constrained()->restrictOnDelete();

            $table->date('check_in_date');
            $table->date('expected_check_out_date')->nullable();
            $table->date('actual_check_out_date')->nullable();

            // Commercial terms frozen at the time of signing.
            $table->decimal('monthly_rent', 12, 2);
            $table->decimal('security_deposit_amount', 12, 2)->default(0);
            $table->decimal('deposit_refunded_amount', 12, 2)->default(0);
            $table->unsignedTinyInteger('rent_due_day')->default(5);
            $table->boolean('food_included')->default(true);

            $table->date('notice_served_on')->nullable();
            $table->string('status', 20)->default('active'); // active/notice_period/checked_out
            $table->timestamp('checked_out_at')->nullable();
            $table->json('checkout_settlement')->nullable(); // frozen settlement breakdown
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Allocation hot path: "is this bed free?" + per-status reporting.
            $table->index(['bed_id', 'status']);
            $table->index(['guest_id', 'status']);
            $table->index(['status', 'expected_check_out_date']);
            $table->index('check_in_date');
        });

        Schema::create('leave_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->date('start_date');
            $table->date('end_date');
            $table->unsignedSmallInteger('total_days');
            $table->boolean('food_opt_out')->default(false);
            $table->string('reason')->nullable();
            $table->string('status', 20)->default('requested'); // requested/approved/rejected/completed
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['booking_id', 'status']);
            $table->index(['start_date', 'end_date']);
        });

        Schema::create('inquiries', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('phone', 20);
            $table->string('email')->nullable();
            $table->string('gender', 10)->default('male');
            $table->string('source', 20)->default('walk_in');
            $table->decimal('budget', 12, 2)->nullable();
            $table->string('interested_in')->nullable();
            $table->date('preferred_move_in')->nullable();
            $table->date('follow_up_date')->nullable();
            $table->string('status', 20)->default('new'); // new/contacted/visited/converted/cancelled
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('converted_guest_id')->nullable()->constrained('guests')->nullOnDelete();
            $table->text('message')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'follow_up_date']);
            $table->index('phone');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inquiries');
        Schema::dropIfExists('leave_logs');
        Schema::dropIfExists('bookings');
        Schema::dropIfExists('guests');
    }
};
