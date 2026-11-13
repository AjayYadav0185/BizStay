<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Delhi NCR market release (all additive, nullable or defaulted):
 *
 *  guests     : tenant police verification (mandatory across Delhi, Haryana and UP).
 *  properties : landlord name + PAN for HRA rent receipts; public listing toggle.
 *  invoices   : online payment link (Razorpay) id + short URL.
 *  users      : phone + is_active so staff can be switched off without deleting.
 *  visitors   : the gate register (who came, whom they met, in/out time).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('guests', function (Blueprint $table): void {
            $table->string('police_verification_status', 20)->default('not_submitted')->after('kyc_remarks');
            $table->string('police_verification_ref', 60)->nullable()->after('police_verification_status');
            $table->string('police_station')->nullable()->after('police_verification_ref');
            $table->date('police_verification_submitted_on')->nullable()->after('police_station');
            $table->date('police_verified_on')->nullable()->after('police_verification_submitted_on');
            $table->index('police_verification_status');
        });

        Schema::table('properties', function (Blueprint $table): void {
            $table->string('owner_name')->nullable()->after('manager_name');
            $table->string('owner_pan', 10)->nullable()->after('owner_name');
            $table->boolean('public_listing')->default(true)->after('is_active');
            $table->string('whatsapp_number', 20)->nullable()->after('contact_phone');
        });

        Schema::table('invoices', function (Blueprint $table): void {
            $table->string('payment_link_id', 60)->nullable()->after('last_reminded_at');
            $table->string('payment_link_url')->nullable()->after('payment_link_id');
            $table->index('payment_link_id');
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->string('phone', 20)->nullable()->after('email');
            $table->boolean('is_active')->default(true)->after('role');
        });

        Schema::create('visitors', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('guest_id')->nullable()->constrained()->nullOnDelete();
            $table->string('visitor_name');
            $table->string('visitor_phone', 20)->nullable();
            $table->string('relation', 40)->nullable();
            $table->string('purpose')->nullable();
            $table->string('id_proof', 60)->nullable();
            $table->timestamp('checked_in_at');
            $table->timestamp('checked_out_at')->nullable();
            $table->boolean('is_overnight')->default(false);
            $table->foreignId('logged_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('checked_in_at');
            $table->index(['checked_out_at', 'checked_in_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visitors');

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['phone', 'is_active']);
        });

        Schema::table('invoices', function (Blueprint $table): void {
            $table->dropIndex(['payment_link_id']);
            $table->dropColumn(['payment_link_id', 'payment_link_url']);
        });

        Schema::table('properties', function (Blueprint $table): void {
            $table->dropColumn(['owner_name', 'owner_pan', 'public_listing', 'whatsapp_number']);
        });

        Schema::table('guests', function (Blueprint $table): void {
            $table->dropIndex(['police_verification_status']);
            $table->dropColumn([
                'police_verification_status', 'police_verification_ref', 'police_station',
                'police_verification_submitted_on', 'police_verified_on',
            ]);
        });
    }
};
