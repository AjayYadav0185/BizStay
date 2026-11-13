<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Upgrading installs had every staff login as "manager". The new Logins &
 * Staff screen is owner-only, so the oldest staff account becomes the owner
 * (admin) when no admin exists yet. Idempotent.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('users')->where('role', 'admin')->exists()) {
            return;
        }

        $firstStaffId = DB::table('users')
            ->where('role', '!=', 'tenant')
            ->orderBy('id')
            ->value('id');

        if ($firstStaffId !== null) {
            DB::table('users')->where('id', $firstStaffId)->update(['role' => 'admin']);
        }
    }

    public function down(): void
    {
        // Role promotion is not reversed: demoting the owner could lock them out.
    }
};
