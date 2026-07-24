@extends('layouts.app')

@section('title', '– Aggiungi dispositivo')

@section('content')
    <div class="max-w-md mx-auto text-center bg-white rounded-xl shadow p-8">
        <h1 class="text-lg font-semibold text-gray-800 mb-2">Scansiona per iscrivere il dispositivo</h1>

        @if ($enrollmentToken->platform === 'ios')
            <p class="text-sm text-gray-500 mb-6">
                Apri l'app Fotocamera sull'iPhone/iPad e inquadra questo
                codice: si aprirà Safari con il profilo di gestione da
                installare. Vai poi in <strong>Impostazioni → Profilo
                scaricato</strong> e tocca "Installa". Il dispositivo dovrà
                essere connesso a Internet (Wi-Fi o dati).
            </p>
        @else
            <p class="text-sm text-gray-500 mb-6">
                Su un dispositivo Android nuovo o resettato, nella schermata di
                benvenuto tocca 6 volte un punto vuoto per attivare la modalità
                di configurazione tramite QR, poi collegati al Wi-Fi e scansiona
                questo codice.
            </p>
        @endif

        <div id="qrcode" class="flex justify-center mb-4"></div>

        <p class="text-xs text-gray-400">
            Il codice scade il {{ $enrollmentToken->expires_at?->format('d/m/Y H:i') }}.
        </p>

        <a href="{{ route('devices.index') }}" class="inline-block mt-6 text-sm text-green-800 hover:underline">
            Torna ai dispositivi
        </a>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcode/1.4.4/qrcode.min.js"></script>
    <script>
        const qrData = @json($enrollmentToken->qr_code_json);
        
        QRCode.toCanvas(document.createElement('canvas'), qrData, { width: 260 }, function (error, canvas) {
            if (error) {
                document.getElementById('qrcode').innerText = 'Errore nella generazione del QR.';
                console.error(error);
                return;
            }
            // Svuota prima il div per sicurezza (evita duplicati se lo script gira due volte)
            document.getElementById('qrcode').innerHTML = '';
            document.getElementById('qrcode').appendChild(canvas);
        });
    </script>
@endsection
