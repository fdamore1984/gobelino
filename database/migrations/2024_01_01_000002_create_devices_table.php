<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('added_by')->nullable()->constrained('users')->nullOnDelete();

            $table->string('name')->nullable();

            // Identificativo dispositivo lato Android Management API,
            // es. enterprises/LC00abc/devices/xxxxx (popolato dopo l'enrollment)
            $table->string('google_device_id')->nullable()->unique();

            $table->string('status')->default('pending');
            $table->string('model')->nullable();
            $table->string('manufacturer')->nullable();
            $table->string('android_version')->nullable();
            $table->timestamp('last_synced_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('devices');
    }
};
