@extends('layouts.app')

@section('title', '– Configura APNs')

@section('content')
    <div class="flex items-center justify-between mb-4">
        <h1 class="text-xl sm:text-2xl font-semibold text-gray-800">Configura push APNs</h1>
    </div>

    <div class="bg-white rounded-xl shadow p-6 mb-6 text-sm text-gray-600 space-y-3">
        <p>
            Per iscrivere iPhone/iPad, Apple richiede che ogni MDM abbia un
            proprio <strong>certificato push APNs</strong>, valido 1 anno.
            A differenza di Android Enterprise, questo passaggio non è
            automatico: va fatto una volta, seguendo questi passaggi.
        </p>
        <ol class="list-decimal list-inside space-y-2">
            <li>
                Scarica qui sotto la richiesta di firma (CSR) generata per
                la tua azienda.
            </li>
            <li>
                Falla firmare da Apple. Se la tua azienda ha già un account
                <em>Apple Developer Enterprise Program</em> con un vendor
                certificate MDM proprio, usa quello. Altrimenti puoi usare
                un servizio di firma CSR gratuito come
                <a href="https://mdmcert.download" target="_blank" rel="noopener" class="text-green-800 underline">mdmcert.download</a>
                (basta un Apple ID).
            </li>
            <li>
                Carica la CSR firmata su
                <a href="https://identity.apple.com/pushcert/" target="_blank" rel="noopener" class="text-green-800 underline">identity.apple.com/pushcert</a>
                (Apple Push Certificates Portal). Apple restituirà un file
                <code class="bg-gray-100 px-1 rounded">.pem</code>: è il tuo
                certificato push.
            </li>
            <li>
                Ricarica quel file <code class="bg-gray-100 px-1 rounded">.pem</code>
                qui sotto, nel secondo modulo.
            </li>
        </ol>
        <p class="text-xs text-gray-400">
            Nota: il certificato push va rinnovato ogni anno con lo stesso
            Apple ID usato la prima volta, altrimenti tutti i dispositivi
            iOS già iscritti smettono di rispondere ai comandi MDM.
        </p>
    </div>

    <div class="bg-white rounded-xl shadow p-6 mb-6">
        <h2 class="font-medium text-gray-800 mb-2">1. Genera la richiesta di firma (CSR)</h2>
        <p class="text-sm text-gray-500 mb-4">
            Genera una nuova coppia di chiavi per la tua azienda e scarica
            la CSR da far firmare. Se ne generi una nuova, quella
            precedente (se non ancora caricata) viene sostituita.
        </p>
        <form method="POST" action="{{ route('apns.csr') }}">
            @csrf
            <button type="submit" class="bg-green-800 text-white px-4 py-2 rounded-lg hover:bg-green-900 transition text-sm">
                Genera e scarica CSR
            </button>
        </form>
    </div>

    <div class="bg-white rounded-xl shadow p-6">
        <h2 class="font-medium text-gray-800 mb-2">2. Carica il certificato push firmato da Apple</h2>
        <p class="text-sm text-gray-500 mb-4">
            Carica qui il file <code class="bg-gray-100 px-1 rounded">.pem</code>
            scaricato da identity.apple.com/pushcert dopo aver caricato la
            CSR firmata.
        </p>
        <form method="POST" action="{{ route('apns.upload') }}" enctype="multipart/form-data" class="flex flex-col sm:flex-row gap-3 items-start sm:items-center">
            @csrf
            <input type="file" name="certificate" accept=".pem,.crt,.cer" required
                   class="text-sm text-gray-600 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:bg-gray-100 file:text-sm file:text-gray-700 hover:file:bg-gray-200">
            <button type="submit" class="bg-gray-800 text-white px-4 py-2 rounded-lg hover:bg-black transition text-sm">
                Carica certificato
            </button>
        </form>

        @if ($company->hasApnsConfigured())
            <p class="text-xs text-green-700 mt-4">
                Certificato attivo, valido fino al {{ $company->apns_expires_at->format('d/m/Y') }}.
            </p>
        @endif
    </div>
@endsection
