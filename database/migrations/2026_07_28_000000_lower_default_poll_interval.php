<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Lowers the polling interval to 60s (the minimum enforced by the
     * agent app itself, see PollWorker/coerceAtLeast(60)): both the
     * default for newly enrolled devices and the value already stored
     * on devices enrolled before this change.
     */
    public function up(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->unsignedInteger('poll_interval_seconds')->default(60)->change();
        });

        DB::table('devices')->update(['poll_interval_seconds' => 60]);
    }

    public function down(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->unsignedInteger('poll_interval_seconds')->default(300)->change();
        });

        DB::table('devices')->update(['poll_interval_seconds' => 300]);
    }
};
