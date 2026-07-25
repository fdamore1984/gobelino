@extends('layouts.app')

@section('title', '– Configurazione APNs')

@section('content')
    <div class="max-w-3xl">
        <h1 class="text-xl sm:text-2xl font-semibold text-gray-800 mb-6">
            Configurazione Certificato Push APNs
        </h1>

        {{-- Avviso Informativo --}}
        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
            <p class="text-sm text-blue-800">
                <strong>ℹ️ Info:</strong> Il certificato APNs permette a Gobelino di inviare notifiche push ai dispositivi iOS/iPadOS della tua azienda.
            </p>
        </div>

        {{-- Sezione: Certificato Caricato --}}
        @if ($hasApnsCertificate)
            <div class="bg-green-50 border border-green-200 rounded-lg p-6 mb-6">
                <h2 class="text-lg font-semibold text-green-800 mb-2">
                    ✅ Certificato Attivo
                </h2>
                <p class="text-sm text-green-700 mb-4">
                    Il tuo certificato APNs è configurato e funzionante.
                </p>
                
                @if ($company->apns_certificate_expires_at)
                    <p class="text-sm text-green-700">
                        <strong>Scadenza:</strong> {{ $company->apns_certificate_expires_at->format('d/m/Y') }}
                    </p>
                    @if ($company->apns_certificate_expires_at->diffInDays() < 30)
                        <p class="text-sm text-orange-600 mt-2">
                            ⚠️ Il certificato scade tra {{ $company->apns_certificate_expires_at->diffInDays() }} giorni.
                        </p>
                    @endif
                @endif

                <button onclick="document.getElementById('replace-modal').classList.remove('hidden')"
                        class="mt-4 bg-orange-600 text-white px-4 py-2 rounded-lg hover:bg-orange-700 transition text-sm">
                    Sostituisci Certificato
                </button>
            </div>
        @else
            {{-- Sezione: Genera CSR --}}
            <div class="bg-white rounded-xl shadow p-6 mb-6">
                <h2 class="text-lg font-semibold text-gray-800 mb-4">
                    Step 1: Genera il CSR
                </h2>
                <p class="text-sm text-gray-600 mb-4">
                    Clicca il bottone per generare il Certificate Signing Request (CSR). 
                    Questo file contiene i dati della tua azienda.
                </p>

                <form method="POST" action="{{ route('apns.generate-csr') }}" class="inline">
                    @csrf
                    <button type="submit"
                            class="bg-green-800 text-white px-6 py-2 rounded-lg hover:bg-green-900 transition text-sm font-medium">
                        📥 Genera e Scarica CSR
                    </button>
                </form>
            </div>

            {{-- Sezione: Istruzioni mdmcert.download --}}
            <div class="bg-white rounded-xl shadow p-6 mb-6">
                <h2 class="text-lg font-semibold text-gray-800 mb-4">
                    Step 2: Fai Firmare il CSR (GRATUITO)
                </h2>

                <div class="space-y-4 text-sm">
                    <div class="bg-gray-50 p-4 rounded-lg">
                        <p class="font-semibold text-gray-800 mb-2">🌐 Usa il servizio gratuito mdmcert.download:</p>
                        <ol class="list-decimal list-inside space-y-2 text-gray-700">
                            <li>
                                Vai a: <a href="https://mdmcert.download/" target="_blank" class="text-green-600 underline font-medium">mdmcert.download</a>
                            </li>
                            <li>
                                Clicca su <strong>"Create Certificate"</strong>
                            </li>
                            <li>
                                Carica il CSR che hai scaricato dal Step 1
                            </li>
                            <li>
                                Inserisci il tuo <strong>Apple ID aziendale</strong> (quello usato per sviluppare app)
                            </li>
                            <li>
                                Clicca <strong>"Create"</strong> e attendi 1-2 minuti
                            </li>
                            <li>
                                Scarica il file <strong>.pem</strong> che riceverai
                            </li>
                        </ol>
                    </div>

                    <div class="bg-yellow-50 border border-yellow-200 p-4 rounded-lg">
                        <p class="text-yellow-800">
                            <strong>💡 Suggerimento:</strong> Se non hai un Apple ID aziendale, puoi crearne uno gratuitamente con il tuo Apple ID personale.
                        </p>
                    </div>
                </div>
            </div>

            {{-- Sezione: Carica Certificato --}}
            <div class="bg-white rounded-xl shadow p-6">
                <h2 class="text-lg font-semibold text-gray-800 mb-4">
                    Step 3: Carica il Certificato Firmato
                </h2>
                <p class="text-sm text-gray-600 mb-4">
                    Una volta ricevuto il certificato .pem da mdmcert.download, caricalo qui:
                </p>

                <form method="POST" action="{{ route('apns.upload-certificate') }}" enctype="multipart/form-data" class="space-y-4">
                    @csrf

                    <div>
                        <label for="certificate" class="block text-sm font-medium text-gray-700 mb-2">
                            File Certificato (.pem)
                        </label>
                        <input type="file" id="certificate" name="certificate" accept=".pem,.cer,.crt"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-600 focus:border-transparent text-sm"
                               required>
                        @error('certificate')
                            <p class="text-red-600 text-xs mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <button type="submit"
                            class="bg-green-800 text-white px-6 py-2 rounded-lg hover:bg-green-900 transition text-sm font-medium">
                        ✅ Carica Certificato
                    </button>
                </form>
            </div>
        @endif

        {{-- Modal: Sostituisci Certificato --}}
        @if ($hasApnsCertificate)
            <div id="replace-modal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
                <div class="bg-white rounded-lg p-6 max-w-md mx-4">
                    <h3 class="text-lg font-semibold text-gray-800 mb-3">
                        Sostituisci Certificato APNs
                    </h3>
                    <p class="text-sm text-gray-600 mb-4">
                        Se vuoi sostituire il certificato, segui di nuovo i 3 step qui sopra. 
                        Il nuovo certificato sovrascriverà quello attuale.
                    </p>
                    
                    <div class="space-y-2">
                        <form method="POST" action="{{ route('apns.upload-certificate') }}" enctype="multipart/form-data">
                            @csrf
                            <input type="file" name="certificate" accept=".pem,.cer,.crt" required class="text-sm">
                            <button type="submit" class="w-full bg-orange-600 text-white px-4 py-2 rounded-lg hover:bg-orange-700 transition text-sm mt-2">
                                Sostituisci
                            </button>
                        </form>
                        <button onclick="document.getElementById('replace-modal').classList.add('hidden')"
                                class="w-full border border-gray-300 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-50 transition text-sm">
                            Annulla
                        </button>
                    </div>
                </div>
            </div>
        @endif
    </div>
@endsection
