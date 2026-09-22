<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P1/P2 money-maturity + audit columns (all additive and nullable):
 *
 *  invoices.late_fee_charged_on — late-fee engine marker (charged once per
 *  invoice, never double-charged on a re-run).
 *  invoices.last_reminded_at    — overdue reminder throttle (max every 3 days).
 *  bookings.assigned_marketer / converted_inquiry_id — audit trail for the
 *  one-click inquiry conversion ("who turned this lead into a tenant").
 *
 * Additive-only, so existing rows and the P0 schema are untouched.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            $table->date('late_fee_charged_on')->nullable()->after('notes');
            $table->date('last_reminded_at')->nullable()->after('late_fee_charged_on');
            $table->index(['late_fee_charged_on', 'last_reminded_at']);
        });

        Schema::table('bookings', function (Blueprint $table): void {
            $table->foreignId('assigned_marketer')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('converted_inquiry_id')->nullable()->constrained('inquiries')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('converted_inquiry_id');
            $table->dropConstrainedForeignId('assigned_marketer');
        });

        Schema::table('invoices', function (Blueprint $table): void {
            $table->dropIndex(['late_fee_charged_on', 'last_reminded_at']);
            $table->dropColumn(['last_reminded_at', 'late_fee_charged_on']);
        });
    }
};
