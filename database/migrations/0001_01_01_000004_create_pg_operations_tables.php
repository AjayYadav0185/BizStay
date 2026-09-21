<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('phone', 20);
            $table->string('email')->nullable();
            $table->string('gender')->default('male');
            $table->string('source')->default('walk_in'); // walk_in / nobroker / 99acres / magicbricks / reference / facebook / other
            $table->decimal('budget', 10, 2)->nullable();
            $table->string('interested_in')->nullable(); // e.g. "2 sharing AC"
            $table->date('preferred_move_in')->nullable();
            $table->date('follow_up_date')->nullable();
            $table->string('status')->default('new'); // new / contacted / visited / booked / cancelled
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->text('message')->nullable();
            $table->timestamps();

            $table->index(['status', 'follow_up_date']);
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type')->default('rent'); // rent / deposit / utility / maintenance / other
            $table->decimal('amount', 10, 2);
            $table->date('period_month')->nullable(); // which month this rent is for
            $table->date('due_date')->nullable();
            $table->date('paid_at')->nullable();
            $table->string('status')->default('pending'); // paid / pending / overdue
            $table->string('method')->nullable(); // cash / upi / bank_transfer / card / cheque
            $table->string('transaction_ref')->nullable();
            $table->foreignId('collected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['status', 'period_month']);
        });

        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->string('category')->default('other'); // electricity / water / internet / housekeeping / repairs / gas / salary / other
            $table->decimal('amount', 10, 2);
            $table->date('spent_on');
            $table->string('vendor')->nullable();
            $table->string('method')->nullable();
            $table->string('receipt_file')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('complaints', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('category')->default('other'); // electrical / plumbing / housekeeping / security / internet / food / other
            $table->string('priority')->default('medium'); // low / medium / high
            $table->string('status')->default('open'); // open / in_progress / resolved / closed
            $table->string('assigned_to')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->text('resolution_notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('complaints');
        Schema::dropIfExists('expenses');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('bookings');
    }
};
