@extends('layouts.app')

@section('title', '– Configurazione APNs')

@section('content')
    <div class="max-w-3xl">
        <h1 class="text-xl sm:text-2xl font-semibold text-gray-800 mb-2">
            Configurazione certificato push APNs
        </h1>
        <p class="text-sm text-gray-500 mb-6">
            Per iscrivere iPhone/iPad, Apple richiede che ogni MDM abbia un
            proprio certificato push APNs, valido 1 anno. A differenza di
            Android Enterprise questo passaggio non è automatico, ma va
            fatto una volta sola seguendo i due step qui sotto.
        </p>

        {{-- Certificato attivo --}}
        @if ($company->hasApnsConfigured())
            <div class="bg-green-50 border border-green-200 rounded-lg p-6 mb-6">
                <h2 class="text-lg font-semibold text-green-800 mb-2">
                    ✅ Certificato attivo
                </h2>
                <p class="text-sm text-green-700">
                    Il certificato push APNs è configurato e funzionante,
                    valido fino al {{ $company->apns_expires_at->format('d/m/Y') }}.
                </p>
                @if ($company->apns_expires_at->diffInDays() < 30)
                    <p class="text-sm text-orange-600 mt-2">
                        ⚠️ Scade tra {{ $company->apns_expires_at->diffInDays() }} giorni: ripeti gli step
                        qui sotto con lo stesso Apple ID usato la prima volta per rinnovarlo.
                    </p>
                @endif
            </div>
        @endif

        {{-- Step 1: richiesta a mdmcert.download --}}
        <div class="bg-white rounded-xl shadow p-6 mb-6">
            <div class="flex items-center gap-2 mb-2">
                <span class="flex items-center justify-center w-6 h-6 rounded-full text-xs font-semibold
                    {{ $company->isAwaitingApnsCertificate() || $company->hasApnsConfigured() ? 'bg-green-800 text-white' : 'bg-gray-200 text-gray-700' }}">1</span>
                <h2 class="text-lg font-semibold text-gray-800">
                    Richiedi la firma del certificato
                </h2>
            </div>
            <p class="text-sm text-gray-600 mb-4">
                Inserisci l'email con cui sei registrato su
                <a href="https://mdmcert.download" target="_blank" rel="noopener" class="text-green-800 underline">mdmcert.download</a>
                (il servizio gratuito che firma il certificato push per conto di Apple).
                Genereremo la richiesta di firma (CSR) e gliela invieremo: riceverai
                il CSR firmato direttamente su quella email.
            </p>

            <form method="POST" action="{{ route('apns.request-csr') }}" class="flex flex-col sm:flex-row gap-3 sm:items-start">
                @csrf
                <div class="flex-1">
                    <input type="email" name="email" placeholder="tuaemail@esempio.it"
                           value="{{ old('email', $company->apns_mdmcert_email) }}"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-600 focus:border-transparent text-sm"
                           required>
                    @error('email')
                        <p class="text-red-600 text-xs mt-1">{{ $message }}</p>
                    @enderror
                </div>
                <button type="submit"
                        class="bg-green-800 text-white px-6 py-2 rounded-lg hover:bg-green-900 transition text-sm font-medium whitespace-nowrap">
                    Invia richiesta
                </button>
            </form>

            @if ($company->isAwaitingApnsCertificate())
                <p class="text-xs text-green-700 mt-3">
                    Richiesta inviata a <strong>{{ $company->apns_mdmcert_email }}</strong>
                    il {{ $company->apns_csr_submitted_at?->format('d/m/Y H:i') }}.
                    Controlla la posta: una volta ricevuto il CSR firmato, seguilo
                    fino allo Step 2 qui sotto.
                </p>
            @endif
        </div>

        {{-- Step 2: upload del certificato finale --}}
        <div class="bg-white rounded-xl shadow p-6">
            <div class="flex items-center gap-2 mb-2">
                <span class="flex items-center justify-center w-6 h-6 rounded-full text-xs font-semibold
                    {{ $company->hasApnsConfigured() ? 'bg-green-800 text-white' : 'bg-gray-200 text-gray-700' }}">2</span>
                <h2 class="text-lg font-semibold text-gray-800">
                    Carica il certificato firmato
                </h2>
            </div>
            <p class="text-sm text-gray-600 mb-4">
                Apri il CSR firmato che hai ricevuto via email e caricalo su
                <a href="https://identity.apple.com/pushcert/" target="_blank" rel="noopener" class="text-green-800 underline">identity.apple.com/pushcert</a>
                (Apple Push Certificates Portal): Apple ti restituirà un file
                <code class="bg-gray-100 px-1 rounded">.pem</code>. Caricalo qui sotto per completare la configurazione.
            </p>

            <form method="POST" action="{{ route('apns.upload-certificate') }}" enctype="multipart/form-data" class="flex flex-col sm:flex-row gap-3 items-start sm:items-center">
                @csrf
                <input type="file" name="certificate" accept=".pem,.crt,.cer" required
                       class="text-sm text-gray-600 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:bg-gray-100 file:text-sm file:text-gray-700 hover:file:bg-gray-200">
                <button type="submit"
                        class="bg-gray-800 text-white px-4 py-2 rounded-lg hover:bg-black transition text-sm whitespace-nowrap">
                    Carica certificato
                </button>
            </form>
            @error('certificate')
                <p class="text-red-600 text-xs mt-2">{{ $message }}</p>
            @enderror
        </div>

        <p class="text-xs text-gray-400 mt-6">
            Nota: il certificato push va rinnovato ogni anno con lo stesso
            Apple ID/email usato la prima volta, altrimenti tutti i
            dispositivi iOS già iscritti smettono di rispondere ai comandi MDM.
        </p>
    </div>
@endsection
