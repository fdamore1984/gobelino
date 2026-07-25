<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The apns/connect and apns/configure pages have been merged into
     * a single 2-step flow (see ApnsController). Here we clean up the
     * columns introduced for the old multi-step flow that are no
     * longer needed: the "server" CSR is no longer kept separate from
     * the main private key, and neither the intermediate signed CSR
     * nor a second duplicate certificate are manually uploaded anymore.
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
