<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Aggiunge tutto ciò che serve per gestire, oltre ad Android,
     * anche l'iscrizione di iPhone/iPad tramite Apple MDM.
     *
     * Nota: qui aggiungiamo solo colonne/dati. La configurazione
     * vera e propria (certificato push APNs, certificato vendor)
     * va caricata dall'azienda una volta ottenuta da Apple.
     */
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            // Percorso/contenuto del certificato push APNs (rilasciato da Apple,
            // valido 1 anno, va rinnovato). Salviamo il contenuto cifrato,
            // non un path fisso, per compatibilità con Railway (filesystem effimero).
            $table->text('apns_certificate_pem')->nullable()
                ->comment('Certificato push APNs (.pem) rilasciato da Apple per questa azienda, cifrato a riposo');
            $table->text('apns_private_key_pem')->nullable()
                ->comment('Chiave privata associata al certificato push APNs, cifrata a riposo');
            $table->string('apns_topic')->nullable()
                ->comment('Topic APNs, es. com.apple.mgmt.External.<UUID> ricavato dal certificato');
            $table->timestamp('apns_expires_at')->nullable()
                ->comment('Scadenza del certificato push APNs, per avvisare prima che scada');
        });

        Schema::table('enrollment_tokens', function (Blueprint $table) {
            $table->string('platform')->default('android')->after('company_id')
                ->comment('android oppure ios');
        });
        // Nota: google_name resta NOT NULL (per non richiedere doctrine/dbal
        // per un ->change()). Per i token iOS, che non hanno un nome Google,
        // il controller salva una stringa vuota '' invece di null.

        Schema::table('devices', function (Blueprint $table) {
            $table->string('platform')->default('android')->after('company_id')
                ->comment('android oppure ios');

            // Campi specifici Apple MDM (popolati durante/dopo il check-in del device)
            $table->string('udid')->nullable()->unique()
                ->comment('Identificativo univoco del device iOS, ricevuto al check-in MDM');
            $table->string('push_magic')->nullable()
                ->comment('Valore che Apple restituisce al check-in, serve per costruire il push APNs');
            $table->string('mdm_token')->nullable()
                ->comment('Token che identifica il device MDM lato Apple');

            // Nota: google_device_id (già nullable/unique) resta valorizzato
            // solo per i device Android; per iOS si usa udid/mdm_token sopra.
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
