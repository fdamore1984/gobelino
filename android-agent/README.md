# Gobelino Agent (Android)

App di gestione dispositivi Android che sostituisce l'integrazione con
Android Enterprise / Android Management API. Diventa **Device Owner**
tramite il flusso di provisioning nativo di Android (QR code durante il
setup wizard), senza alcuna registrazione EMM con Google. Comunica con
il backend Laravel esclusivamente tramite **polling periodico**
(WorkManager), non FCM.

## Struttura

- `receiver/AgentDeviceAdminReceiver.kt` — riceve l'evento di fine
  provisioning, legge `server_url` ed `enrollment_token` dagli extra
  del QR, pianifica il polling.
- `worker/PollWorker.kt` — job periodico: al primo giro consuma il
  token di enrollment (`/api/agent/enroll`), poi ad ogni ciclo chiama
  `/api/agent/poll` per inviare stato/esiti comandi e ricevere nuovi
  comandi.
- `worker/CommandExecutor.kt` — esegue i comandi (`lock`, `wipe`,
  `reboot`, `set_kiosk`) usando le API `DevicePolicyManager` di Device
  Owner.
- `MainActivity.kt` — schermata mostrata sul dispositivo; gestisce
  l'attivazione/disattivazione della kiosk mode (`startLockTask` /
  `stopLockTask`).

## Build

Requisiti: Android Studio (o Gradle CLI) con JDK 17, Android SDK 34.

```bash
cd android-agent
gradle wrapper --gradle-version 8.7   # genera gradlew/gradlew.bat (non versionati qui)
./gradlew assembleRelease
```

L'APK va **firmato** con una chiave di release stabile (la stessa ad
ogni build: il checksum nel QR deve corrispondere sempre alla firma
corrente). Configura la firma in `app/build.gradle.kts` con un
`signingConfig` che punti al tuo keystore, oppure firma manualmente
con `apksigner` dopo il build.

## Pubblicare una nuova build

1. Compila e firma l'APK di release.
2. Caricalo su uno storage pubblico HTTPS raggiungibile senza
   autenticazione (es. un bucket S3/R2, o `public/downloads/` servito
   da Laravel) e imposta l'URL in `AGENT_APK_DOWNLOAD_URL`.
3. Calcola il checksum SHA-256 richiesto da
   `PROVISIONING_DEVICE_ADMIN_SIGNATURE_CHECKSUM` (è l'hash del
   *file* APK, in base64 URL-safe senza padding):

   ```bash
   openssl dgst -binary -sha256 app-release.apk | openssl base64 | tr '+/' '-_' | tr -d '='
   ```

4. Imposta il risultato in `AGENT_APK_CHECKSUM` nel `.env` del
   backend.
5. Imposta `AGENT_ADMIN_COMPONENT` a
   `com.gobelino.agent/.receiver.AgentDeviceAdminReceiver`.

Ogni volta che pubblichi una nuova build firmata, ripeti i punti 3–4:
se il checksum non corrisponde, Android rifiuta il provisioning.

## Provisioning di un dispositivo

1. Dal pannello, "Add Android (generate QR)".
2. Su un Android nuovo o resettato di fabbrica, nella schermata di
   benvenuto tocca 6 volte un punto vuoto per attivare la modalità QR,
   connetti il Wi-Fi, poi inquadra il QR generato.
3. Android scarica l'APK dall'URL indicato, verifica il checksum,
   installa l'app e la imposta come Device Owner, poi le passa il
   controllo (`PROVISIONING_SUCCESSFUL`).
4. `AgentDeviceAdminReceiver` legge token/URL dagli extra e pianifica
   il primo poll: entro ~1 minuto il dispositivo compare come
   enrollato nel pannello.

## Note

- `minSdk 26`: il provisioning QR nativo funziona da API 24, ma
  WorkManager e le API usate qui sono più semplici da gestire da 26 in
  su. Abbassalo se ti serve supportare device più vecchi.
- L'icona in `res/drawable/ic_launcher.xml` è un placeholder: sostituiscila
  prima della pubblicazione.
- `gradlew`/`gradlew.bat` e `gradle-wrapper.jar` non sono inclusi (binari):
  generali con `gradle wrapper` come sopra, oppure aprendo il progetto in
  Android Studio.
