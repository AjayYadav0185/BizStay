<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Gurgaon-fit columns (all additive + nullable so existing rows/tests pass):
 *  properties: GSTIN + UPI for invoices, hotel check-in/out times.
 *  rooms: nightly_rate for hotel/daily bookings (PG keeps base_rent_per_bed).
 *  bookings: stay_type pg|hotel, nightly_rate, guests count for hotel.
 *  invoices: gst_percent + gst_amount shown as its own line on the print view.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('properties', function (Blueprint $table): void {
            $table->string('gstin', 20)->nullable()->after('pincode');
            $table->string('upi_id', 60)->nullable()->after('gstin');
            $table->string('check_in_time', 5)->default('12:00')->after('upi_id');
            $table->string('check_out_time', 5)->default('11:00')->after('check_in_time');
        });

        Schema::table('rooms', function (Blueprint $table): void {
            $table->decimal('nightly_rate', 12, 2)->nullable()->after('base_rent_per_bed');
        });

        Schema::table('bookings', function (Blueprint $table): void {
            $table->string('stay_type', 10)->default('pg')->after('status');
            $table->decimal('nightly_rate', 12, 2)->nullable()->after('monthly_rent');
            $table->unsignedTinyInteger('guests_count')->default(1)->after('nightly_rate');
        });

        Schema::table('invoices', function (Blueprint $table): void {
            $table->decimal('gst_percent', 5, 2)->default(0)->after('other_charges');
            $table->decimal('gst_amount', 12, 2)->default(0)->after('gst_percent');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            $table->dropColumn(['gst_percent', 'gst_amount']);
        });
        Schema::table('bookings', function (Blueprint $table): void {
            $table->dropColumn(['stay_type', 'nightly_rate', 'guests_count']);
        });
        Schema::table('rooms', function (Blueprint $table): void {
            $table->dropColumn('nightly_rate');
        });
        Schema::table('properties', function (Blueprint $table): void {
            $table->dropColumn(['gstin', 'upi_id', 'check_in_time', 'check_out_time']);
        });
    }
};
