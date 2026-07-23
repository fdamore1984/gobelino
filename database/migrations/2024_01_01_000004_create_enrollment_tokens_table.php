<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tiene traccia dei token di iscrizione (QR code) generati,
     * utile per sapere quali sono ancora validi/usabili.
     */
    public function up(): void
    {
        Schema::create('enrollment_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->string('google_name')->comment('es. enterprises/LC00abc/enrollmentTokens/xxxx');
            $table->text('qr_code_json');
            $table->timestamp('expires_at')->nullable();
            $table->boolean('used')->default(false);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('enrollment_tokens');
    }
};
