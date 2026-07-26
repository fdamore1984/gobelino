<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Adds everything needed to manage Android devices via our own
     * agent APK (Device Owner + polling), without going through the
     * Android Management API / Android Enterprise.
     */
    public function up(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            // Secret used by the agent APK to authenticate polling requests.
            $table->string('device_token', 64)->nullable()->unique()->after('google_device_id');

            $table->string('serial_number')->nullable()->after('android_version');
            $table->string('imei')->nullable()->after('serial_number');
            $table->unsignedInteger('battery_level')->nullable()->after('imei');

            $table->unsignedInteger('poll_interval_seconds')->default(300)->after('battery_level');
            $table->timestamp('last_poll_at')->nullable()->after('poll_interval_seconds');

            $table->boolean('kiosk_enabled')->default(false)->after('last_poll_at');
            // JSON array of package names allowed to run when kiosk mode is on.
            $table->json('kiosk_allowed_packages')->nullable()->after('kiosk_enabled');

            $table->boolean('is_device_owner')->default(false)->after('kiosk_allowed_packages');
            $table->string('agent_app_version')->nullable()->after('is_device_owner');
        });

        Schema::table('enrollment_tokens', function (Blueprint $table) {
            // Our own enrollment secret (used for android agent tokens; for
            // iOS/legacy Android Enterprise rows this stays null).
            $table->string('token', 64)->nullable()->unique()->after('google_name');
            $table->string('apk_checksum')->nullable()->after('token');
        });

        Schema::create('device_commands', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            // lock, wipe, reboot, install_app, uninstall_app, set_kiosk, apply_policy
            $table->string('type');
            $table->json('payload')->nullable();

            // pending -> sent (delivered at next poll) -> acked | failed
            $table->string('status')->default('pending');
            $table->text('result')->nullable();

            $table->timestamp('sent_at')->nullable();
            $table->timestamp('acked_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_commands');

        Schema::table('enrollment_tokens', function (Blueprint $table) {
            $table->dropColumn(['token', 'apk_checksum']);
        });

        Schema::table('devices', function (Blueprint $table) {
            $table->dropColumn([
                'device_token',
                'serial_number',
                'imei',
                'battery_level',
                'poll_interval_seconds',
                'last_poll_at',
                'kiosk_enabled',
                'kiosk_allowed_packages',
                'is_device_owner',
                'agent_app_version',
            ]);
        });
    }
};
