<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Adds everything needed to manage, in addition to Android,
     * the enrollment of iPhone/iPad devices via Apple MDM.
     *
     * Note: here we only add columns/data. The actual
     * configuration (APNs push certificate, vendor certificate)
     * must be uploaded by the company once obtained from Apple.
     */
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            // Path/content of the APNs push certificate (issued by Apple,
            // valid for 1 year, needs renewal). We store the encrypted content,
            // not a fixed path, for compatibility with Railway (ephemeral filesystem).
            $table->text('apns_certificate_pem')->nullable()
                ->comment('APNs push certificate (.pem) issued by Apple for this company, encrypted at rest');
            $table->text('apns_private_key_pem')->nullable()
                ->comment('Private key associated with the APNs push certificate, encrypted at rest');
            $table->string('apns_topic')->nullable()
                ->comment('APNs topic, e.g. com.apple.mgmt.External.<UUID> derived from the certificate');
            $table->timestamp('apns_expires_at')->nullable()
                ->comment('APNs push certificate expiration, to warn before it expires');
        });

        Schema::table('enrollment_tokens', function (Blueprint $table) {
            $table->string('platform')->default('android')->after('company_id')
                ->comment('android or ios');
        });
        // Note: google_name stays NOT NULL (to avoid requiring doctrine/dbal
        // for a ->change()). For iOS tokens, which don't have a Google name,
        // the controller saves an empty string '' instead of null.

        Schema::table('devices', function (Blueprint $table) {
            $table->string('platform')->default('android')->after('company_id')
                ->comment('android or ios');

            // Apple MDM specific fields (populated during/after the device check-in)
            $table->string('udid')->nullable()->unique()
                ->comment('Unique identifier of the iOS device, received at MDM check-in');
            $table->string('push_magic')->nullable()
                ->comment('Value Apple returns at check-in, needed to build the APNs push');
            $table->string('mdm_token')->nullable()
                ->comment('Token identifying the MDM device on Apple\'s side');

            // Note: google_device_id (already nullable/unique) stays populated
            // only for Android devices; for iOS udid/mdm_token above are used.
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn([
                'apns_certificate_pem',
                'apns_private_key_pem',
                'apns_topic',
                'apns_expires_at',
            ]);
        });

        Schema::table('enrollment_tokens', function (Blueprint $table) {
            $table->dropColumn('platform');
        });

        Schema::table('devices', function (Blueprint $table) {
            $table->dropColumn(['platform', 'udid', 'push_magic', 'mdm_token']);
        });
    }
};
