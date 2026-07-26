<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Android device management no longer goes through the Android
     * Management API / Android Enterprise (see AndroidAgentService):
     * these columns are dead weight.
     */
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn(['android_enterprise_name', 'android_signup_url_name']);
        });

        Schema::table('devices', function (Blueprint $table) {
            $table->dropColumn('google_device_id');
        });

        // Note: enrollment_tokens.google_name is kept — IosMdmService
        // reuses it as the local unique identifier for iOS tokens.
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->string('android_enterprise_name')->nullable();
            $table->string('android_signup_url_name')->nullable();
        });

        Schema::table('devices', function (Blueprint $table) {
            $table->string('google_device_id')->nullable()->unique();
        });
    }
};
