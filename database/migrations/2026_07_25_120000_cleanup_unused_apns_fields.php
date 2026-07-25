<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Le pagine apns/connect e apns/configure sono state unificate in
     * un unico flusso a 2 step (vedi ApnsController). Ripuliamo qui le
     * colonne introdotte per il vecchio flusso a più step che non
     * servono più: la CSR "server" non viene più tenuta separata dalla
     * chiave privata principale, e non si carica più manualmente né la
     * CSR firmata intermedia né un secondo certificato duplicato.
     */
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn([
                'apns_csr_private_key',
                'apns_signed_csr',
                'apns_awaiting_signed_csr',
                'apns_certificate',
                'apns_certificate_expires_at',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->longText('apns_csr_private_key')->nullable();
            $table->longText('apns_signed_csr')->nullable();
            $table->boolean('apns_awaiting_signed_csr')->default(false);
            $table->longText('apns_certificate')->nullable();
            $table->timestamp('apns_certificate_expires_at')->nullable();
        });
    }
};
