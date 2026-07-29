@extends('layouts.app')

@section('title', '– Add device')

@section('content')
    <div class="max-w-md mx-auto text-center bg-white rounded-xl shadow p-8">
        <h1 class="text-lg font-semibold text-gray-800 mb-2">Scan to enroll the device</h1>

        @if ($enrollmentToken->platform === 'ios')
            <p class="text-sm text-gray-500 mb-6">
                Open the Camera app on the iPhone/iPad and point it at this
                code: Safari will open with the management profile to
                install. Then go to <strong>Settings → Downloaded
                Profile</strong> and tap "Install". The device must be
                connected to the Internet (Wi-Fi or mobile data).
            </p>
        @else
            <p class="text-sm text-gray-500 mb-6">
                On a new or factory-reset Android device, on the welcome
                screen tap an empty spot 6 times to activate QR
                provisioning mode, then connect to Wi-Fi and scan
                this code.
            </p>
        @endif

        <div id="qrcode" class="flex justify-center mb-4"></div>

        <p class="text-xs text-gray-400">
            This code expires on {{ $enrollmentToken->expires_at?->format('d/m/Y H:i') }}.
        </p>

        @if ($enrollmentToken->platform === 'android')
            <div class="mt-6 pt-6 border-t border-gray-200 text-left">
                <p class="text-sm font-semibold text-gray-700 mb-2">
                    Oppure: profilo di lavoro (device già in uso)
                </p>
                <p class="text-xs text-gray-500 mb-4">
                    Installa l'app agent sul dispositivo, tocca "Crea profilo di
                    lavoro" e incolla questi due valori nel form che compare.
                </p>

                <label class="block text-xs text-gray-400 mb-1">URL server</label>
                <div class="flex items-center gap-2 mb-3">
                    <input type="text" readonly value="{{ rtrim(config('app.url'), '/') }}"
                        class="w-full text-xs bg-gray-50 border border-gray-200 rounded px-2 py-1 text-gray-700" />
                </div>

                <label class="block text-xs text-gray-400 mb-1">Token di enrollment</label>
                <div class="flex items-center gap-2">
                    <input type="text" readonly value="{{ $enrollmentToken->token }}"
                        class="w-full text-xs bg-gray-50 border border-gray-200 rounded px-2 py-1 text-gray-700" />
                </div>
            </div>
        @endif

        <a href="{{ route('devices.index') }}" class="inline-block mt-6 text-sm text-green-800 hover:underline">
            Back to devices
        </a>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcode/1.4.4/qrcode.min.js"></script>
    <script>
        const qrData = @json($enrollmentToken->qr_code_json);
        
        QRCode.toCanvas(document.createElement('canvas'), qrData, { width: 260 }, function (error, canvas) {
            if (error) {
                document.getElementById('qrcode').innerText = 'Error generating the QR code.';
                console.error(error);
                return;
            }
            // Clear the div first for safety (avoids duplicates if the script runs twice)
            document.getElementById('qrcode').innerHTML = '';
            document.getElementById('qrcode').appendChild(canvas);
        });
    </script>
@endsection
