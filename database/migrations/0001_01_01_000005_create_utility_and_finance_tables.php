<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Module C — Smart Utility & Metering.
 *
 * Each room carries at most one sub-meter per utility. Consumption is captured
 * as a delta between readings; invoice_id is filled the moment a reading is
 * pulled into a billing cycle, so every rupee of utility billing is traceable
 * back to the physical meter that produced it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('utility_meters', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('room_id')->constrained()->cascadeOnDelete();
            $table->string('meter_type', 20); // electricity / water
            $table->string('meter_serial_no', 60)->unique();
            $table->decimal('multiplier', 8, 3)->default(1.000); // CT ratio / dial factor
            $table->decimal('rate_per_unit', 8, 2)->nullable(); // null = use property setting
            $table->unsignedTinyInteger('fixed_share_count')->nullable(); // beds sharing this meter
            $table->date('installed_on')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['room_id', 'meter_type']);
            $table->index('meter_type');
        });

        Schema::create('invoices', function (Blueprint $table): void {
            $table->id();
            $table->string('invoice_number', 30)->unique(); // BZ-2026-09-0001
            $table->foreignId('booking_id')->constrained()->restrictOnDelete();
            $table->date('billing_cycle_start');
            $table->date('billing_cycle_end');

            $table->decimal('rent_amount', 12, 2)->default(0);
            $table->decimal('utility_amount', 12, 2)->default(0);
            $table->decimal('maintenance_charges', 12, 2)->default(0);
            $table->decimal('food_deduction', 12, 2)->default(0); // leave / mess opt-out credit
            $table->decimal('other_charges', 12, 2)->default(0);
            $table->decimal('previous_balance', 12, 2)->default(0);
            $table->decimal('total_due', 12, 2)->default(0);
            $table->decimal('amount_paid', 12, 2)->default(0);

            $table->date('due_date');
            $table->string('status', 20)->default('unpaid'); // unpaid/partially_paid/paid/overdue
            $table->boolean('is_checkout_settlement')->default(false);
            $table->timestamp('generated_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Idempotency: a booking can only be billed once per cycle start.
            $table->unique(['booking_id', 'billing_cycle_start'], 'invoices_booking_cycle_unique');
            $table->index(['status', 'due_date']);
        });

        Schema::create('meter_readings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('meter_id')->constrained('utility_meters')->cascadeOnDelete();
            $table->date('reading_date');
            $table->decimal('previous_reading', 12, 2)->default(0);
            $table->decimal('current_reading', 12, 2);
            $table->decimal('consumption', 12, 2); // (current - previous) * multiplier
            $table->decimal('rate_per_unit', 8, 2);
            $table->decimal('amount', 12, 2); // consumption * rate
            $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            // One reading per meter per day: makes the bulk-entry screen idempotent.
            $table->unique(['meter_id', 'reading_date']);
            $table->index('reading_date');
        });

        Schema::create('payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->restrictOnDelete();
            $table->foreignId('booking_id')->nullable()->constrained()->nullOnDelete();
            $table->string('payment_type', 20)->default('rent'); // rent/utility/deposit/settlement/refund
            $table->decimal('amount', 12, 2);
            $table->string('payment_method', 20); // upi/netbanking/cash/card/cheque
            $table->string('transaction_id', 80)->nullable();
            $table->string('status', 20)->default('success'); // pending/success/failed/refunded
            $table->date('paid_on');
            $table->foreignId('collected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['invoice_id', 'status']);
            $table->index(['paid_on', 'payment_method']);
            // A UTR can never be posted twice (NULLs stay repeatable).
            $table->unique('transaction_id');
        });

        Schema::create('expenses', function (Blueprint $table): void {
            $table->id();
            $table->string('category', 30)->default('other'); // electricity/water/gas/salary/repairs/other
            $table->decimal('amount', 12, 2);
            $table->date('spent_on');
            $table->string('vendor')->nullable();
            $table->string('payment_method', 20)->nullable();
            $table->string('receipt_path')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['category', 'spent_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('meter_readings');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('utility_meters');
    }
};
