<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->longText('apns_server_cert')->nullable();
            $table->longText('apns_server_key')->nullable();
            $table->longText('apns_csr_pending')->nullable();
            $table->longText('apns_csr_private_key')->nullable();
            $table->longText('apns_signed_csr')->nullable();
            $table->string('apns_mdmcert_email')->nullable();
            $table->boolean('apns_awaiting_signed_csr')->default(false);
            $table->timestamp('apns_csr_submitted_at')->nullable();
            $table->longText('apns_certificate')->nullable();
            $table->timestamp('apns_certificate_expires_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn([
                'apns_server_cert',
                'apns_server_key',
                'apns_csr_pending',
                'apns_csr_private_key',
                'apns_signed_csr',
                'apns_mdmcert_email',
                'apns_awaiting_signed_csr',
                'apns_csr_submitted_at',
                'apns_certificate',
                'apns_certificate_expires_at',
            ]);
        });
    }
};
