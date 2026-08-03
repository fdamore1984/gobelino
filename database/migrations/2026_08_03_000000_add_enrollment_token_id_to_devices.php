<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Collega ogni device al token con cui è stato creato, così
     * DeviceAgentController::enroll() può riconoscere un retry dello
     * stesso agent (stesso enrollment_token) e restituire di nuovo le
     * credenziali del device già creato, invece di crearne uno
     * duplicato o rispondere con un 409 senza via d'uscita.
     */
    public function up(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->foreignId('enrollment_token_id')->nullable()->unique()
                ->after('added_by')
                ->constrained('enrollment_tokens')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('enrollment_token_id');
        });
    }
};
