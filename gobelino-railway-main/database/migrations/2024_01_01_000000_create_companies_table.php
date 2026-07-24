<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('name');

            $table->string('subscription_status')->default('trial');
            $table->timestamp('trial_ends_at')->nullable();
            $table->string('payment_provider_customer_id')->nullable();

            // Android Enterprise (Android Management API)
            $table->string('android_enterprise_name')->nullable()
                ->comment('Es. enterprises/LC00abc123, valorizzato dopo il collegamento');
            $table->string('android_signup_url_name')->nullable()
                ->comment('Valore temporaneo usato durante il collegamento, prima di ottenere android_enterprise_name');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};
