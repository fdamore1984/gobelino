# Gobelino – Setup 100% cloud (nessun passaggio in locale)



Guida per creare tutto direttamente su GitHub e Railway, dal browser,
senza installare nulla sul tuo computer/Steam Deck.

---

## A) Google Cloud (Android Enterprise / AMAPI)

Tutto da browser, su https://console.cloud.google.com

1. Crea un progetto (o riusa uno esistente).
2. Menu → **API e servizi** → **Libreria** → cerca **"Android Management API"** → **Abilita**.
3. Menu → **IAM e amministrazione** → **Service Account** → **Crea service account**.
   - Dagli un nome (es. `gobelino-amapi`)
   - Assegna il ruolo **"Android Management User"**
4. Apri il service account appena creato → tab **Chiavi** → **Aggiungi chiave** → **Crea nuova chiave** → formato **JSON** → si scarica un file.
   - **Apri quel file con un editor di testo e tieni il contenuto a portata di mano**: lo incollerai su Railway come variabile d'ambiente (non va mai messo su GitHub).
5. Annota il **Project ID** (in alto nella dashboard del progetto Google Cloud, non il "project number").

---

## B) Crea il repository GitHub dal template ufficiale di Laravel

1. Vai su https://github.com/laravel/laravel
2. Click sul pulsante verde **"Use this template"** → **"Create a new repository"**
3. Scegli un nome (es. `gobelino`), imposta **Private**, crea il repository.

Ora hai un progetto Laravel completo e funzionante sul tuo account GitHub,
senza aver scaricato o installato nulla.

---

## C) Carica i file di Gobelino

1. Scarica ed estrai lo zip `gobelino-railway.zip` che ti ho preparato (sul tuo dispositivo, va bene anche Steam Deck in modalità Desktop: tasto Steam → Passa a Desktop, poi usa il file manager per estrarre lo zip).
2. Apri la cartella estratta `gobelino-railway`. **Non caricare questa cartella stessa**: apri il suo contenuto (le cartelle `app`, `database`, `public`, `resources`, `routes`, il file `Procfile`) e seleziona tutto quello che c'è dentro.
3. Sul tuo repository GitHub (quello creato al punto B), click **"Add file"** → **"Upload files"**.
4. Trascina dentro la finestra del browser tutti gli elementi selezionati al punto 2 (le cartelle, non il loro contenitore). GitHub in Chrome/Edge preserva la struttura delle sottocartelle.
5. In fondo alla pagina, scrivi un messaggio di commit (es. "Aggiungo Gobelino") e clicca **"Commit changes"**.

Questo *aggiunge* i file senza cancellare quelli base di Laravel (index.php,
Controller.php, ecc.) perché l'upload unisce, non sostituisce le cartelle.

Puoi ignorare/non caricare `README.md` e `bootstrap-app.php` di questo pacchetto
(quest'ultimo lo usi al punto D, non va caricato così com'è).

---

## D) Modifiche a 3 file esistenti (via editor web di GitHub)

Per ognuno: apri il file sul repository GitHub, clicca l'icona a matita
(in alto a destra nel riquadro del file) per modificarlo, incolla il
contenuto, poi **"Commit changes"** in fondo.

### 1. `bootstrap/app.php`
Cancella tutto il contenuto e incolla questo (è il contenuto del file
`bootstrap-app.php` che hai nello zip, se preferisci copialo da lì):

```php
<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'subscription.active' => \App\Http\Middleware\EnsureSubscriptionActive::class,
            'can-manage-users' => \App\Http\Middleware\EnsureCanManageUsers::class,
        ]);

        $middleware->redirectGuestsTo('/login');
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
```

### 2. `composer.json`
Trova la sezione `"require": {`  e aggiungi una riga per `google/apiclient`,
es.:
```json
"require": {
    "php": "^8.2",
    "google/apiclient": "^2.16",
    "laravel/framework": "^11.0",
    ...
},
```
(le altre righe esistenti restano come sono, aggiungi solo quella di
`google/apiclient`, con la virgola alla fine se non è l'ultima riga)

### 3. Elimina `composer.lock`
Vai sul file `composer.lock` nella root del repo → icona cestino (Delete
file) → Commit changes.
(serve perché hai aggiunto una dipendenza nuova: così Railway la
installa da zero invece di usare un lock file non aggiornato)

---

## E) Elimina la migrazione `users` di default (in conflitto)

Vai in `database/migrations/` sul repo GitHub. Troverai **due** file che
creano la tabella `users`:
- quello originale di Laravel, tipo `0001_01_01_000000_create_users_table.php`
- quello di Gobelino, `2024_01_01_000001_create_users_table.php`

Apri quello **originale** (il primo, con il nome che inizia con `0001_`)
→ icona cestino → Commit changes. Lascia quello di Gobelino.

---

## F) Crea il progetto su Railway

1. Vai su https://railway.app → **New Project** → **Deploy from GitHub repo** → autorizza Railway ad accedere al tuo GitHub se richiesto → seleziona il repo `gobelino`.
2. Nello stesso progetto Railway: **+ New** → **Database** → **MySQL**.

## G) Variabili d'ambiente

Nel servizio dell'app web (non nel servizio MySQL) → tab **Variables** →
aggiungi queste (una per una, o con l'opzione "Raw Editor" per incollarle
tutte insieme):

```
APP_NAME=Gobelino
APP_ENV=production
APP_DEBUG=false
APP_KEY=base64:CrCafXgt8XC4ab6rgylDLQ48zUFcx96YBLdDWY+NQuY=
APP_URL=${{RAILWAY_PUBLIC_DOMAIN}}

AMAPI_PROJECT_ID=incolla-qui-il-project-id-di-google
GOOGLE_AMAPI_JSON=incolla-qui-tutto-il-contenuto-del-file-json-scaricato-al-punto-A4

DB_CONNECTION=mysql
DB_HOST=${{MySQL.MYSQLHOST}}
DB_PORT=${{MySQL.MYSQLPORT}}
DB_DATABASE=${{MySQL.MYSQLDATABASE}}
DB_USERNAME=${{MySQL.MYSQLUSER}}
DB_PASSWORD=${{MySQL.MYSQLPASSWORD}}

SESSION_DRIVER=database
```

Note:
- L'`APP_KEY` sopra è già generata e pronta all'uso (valida solo per
  questa istanza: non deve combaciare con nient'altro, va bene così).
- Per `GOOGLE_AMAPI_JSON`, apri il file `.json` scaricato da Google con un
  editor di testo, seleziona tutto il contenuto e incollalo come valore
  di quella variabile (deve stare su un'unica riga: la maggior parte
  degli editor JSON già lo tengono su una riga, altrimenti va bene anche
  su più righe, Railway lo gestisce).
- I valori tipo `${{MySQL.MYSQLHOST}}` sono riferimenti automatici alle
  variabili generate dal plugin MySQL: Railway te li suggerisce in
  autocompletamento mentre digiti `${{`.

## H) Deploy

Railway builda ed esegue il deploy automaticamente dopo il commit delle
variabili. Vai sul tab **Deployments** e controlla i log: cerca la riga
`php artisan migrate --force` (fase "release" del `Procfile`) per
verificare che le tabelle vengano create senza errori.

Se il deploy fallisce, incollami l'errore esatto dai log.

## I) Trova il tuo dominio

Nel servizio web → tab **Settings** → **Networking** → **Generate Domain**
(se non c'è già). Otterrai un URL tipo `gobelino-production.up.railway.app`.

## J) Aggiorna il redirect Google con l'URL definitivo

Se nel tuo progetto Google Cloud hai già configurato un redirect URI per
l'OAuth (fatto in una fase precedente su un altro dispositivo), aggiornalo
con:
```
https://IL-TUO-DOMINIO.up.railway.app/android-enterprise/callback
```

## K) Test

Apri `https://IL-TUO-DOMINIO.up.railway.app/register` e crea il primo
account. Da lì: Dashboard → Dispositivi → Collega Android Enterprise →
Aggiungi dispositivo (QR).

---

## Come funziona il flusso dispositivi (riepilogo)

1. L'owner/admin va su **Dispositivi** → **Collega Android Enterprise** →
   viene rimandato a Google per associare l'account Android Enterprise
   dell'azienda (una tantum).
2. Google reindirizza a `/android-enterprise/callback`, che salva
   `android_enterprise_name` sull'azienda.
3. Da quel momento, **Aggiungi dispositivo** genera un QR code (token di
   iscrizione valido 1 ora): lo si scansiona su un dispositivo Android
   nuovo o resettato in fase di configurazione guidata.
4. **Sincronizza dispositivi** interroga Google e aggiorna l'elenco locale
   con lo stato reale dei dispositivi iscritti.

## Prossimi passi

- Collegare Stripe (o altro) per l'abbonamento dopo il trial.
- Aggiungere policy Android personalizzate (oggi viene creata solo una
  policy di base vuota).
- Azioni sui dispositivi (blocco, wipe, localizzazione) tramite
  `enterprises.devices.patch` / `.delete` dell'Android Management API.
