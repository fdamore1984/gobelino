<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('enrollment_tokens', function (Blueprint $table) {
            $table->string('platform')->nullable()->after('created_by');
            $table->string('token')->nullable()->unique()->after('platform');
            $table->string('apk_checksum')->nullable()->after('token');
            $table->string('google_name')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('enrollment_tokens', function (Blueprint $table) {
            $table->dropColumn(['platform', 'token', 'apk_checksum']);
            $table->string('google_name')->nullable(false)->change();
        });
    }
};