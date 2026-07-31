<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Lowers the polling interval further, to 15s: both the default
     * for newly enrolled devices and the value already stored on
     * devices enrolled before this change. 15s leaves a small margin
     * above the 10s floor enforced by PollScheduler.scheduleNext()
     * (coerceAtLeast(10)) — going lower would just get clamped there
     * anyway.
     *
     * Trade-off: devices now check in up to 4x more often than
     * before, which means more requests hitting the backend and
     * more battery/data used by every enrolled device around the
     * clock, not just when a command is actually pending.
     */
    public function up(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->unsignedInteger('poll_interval_seconds')->default(15)->change();
        });

        DB::table('devices')->update(['poll_interval_seconds' => 15]);
    }

    public function down(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->unsignedInteger('poll_interval_seconds')->default(60)->change();
        });

        DB::table('devices')->update(['poll_interval_seconds' => 60]);
    }
};
