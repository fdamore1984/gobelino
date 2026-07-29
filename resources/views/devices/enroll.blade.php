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
            <div class="mt-8 pt-6 border-t border-gray-200 text-left">
                <p class="text-sm font-semibold text-gray-700 mb-1">
                    Or: Work Profile (device already in use)
                </p>
                <p class="text-xs text-gray-500 mb-4">
                    Use this for a device that's already set up and in daily
                    use — no factory reset needed.
                </p>

                <div class="grid grid-cols-2 gap-4 mb-4">
                    <div class="text-center">
                        <p class="text-xs text-gray-500 mb-2">1. Download the app</p>
                        <div id="qrcode-apk" class="flex justify-center"></div>
                    </div>
                    <div class="text-center">
                        <p class="text-xs text-gray-500 mb-2">2. Scan to auto-fill setup</p>
                        <div id="qrcode-config" class="flex justify-center"></div>
                    </div>
                </div>

                <p class="text-xs text-gray-500 mb-4">
                    Install the app, open it, tap <strong>"Create work
                    profile"</strong>, then tap <strong>"Scan QR to
                    configure"</strong> inside the app and scan the second
                    code above. Or enter the values below manually.
                </p>

                <label class="block text-xs text-gray-400 mb-1">Server URL</label>
                <input type="text" readonly value="{{ rtrim(config('app.url'), '/') }}"
                    onclick="this.select()"
                    class="w-full text-xs bg-gray-50 border border-gray-200 rounded px-2 py-1 text-gray-700 mb-3" />

                <label class="block text-xs text-gray-400 mb-1">Enrollment token</label>
                <input type="text" readonly value="{{ $enrollmentToken->token }}"
                    onclick="this.select()"
                    class="w-full text-xs bg-gray-50 border border-gray-200 rounded px-2 py-1 text-gray-700" />
            </div>
        @endif

        <a href="{{ route('devices.index') }}" class="inline-block mt-6 text-sm text-green-800 hover:underline">
            Back to devices
        </a>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcode/1.4.4/qrcode.min.js"></script>
    <script>
        function renderQr(elementId, data) {
            QRCode.toCanvas(document.createElement('canvas'), data, { width: 200 }, function (error, canvas) {
                const el = document.getElementById(elementId);
                if (error) {
                    el.innerText = 'Error generating the QR code.';
                    console.error(error);
                    return;
                }
                el.innerHTML = '';
                el.appendChild(canvas);
            });
        }

        renderQr('qrcode', @json($enrollmentToken->qr_code_json));

        @if ($enrollmentToken->platform === 'android')
            renderQr('qrcode-apk', @json(route('agent.apk.download')));

            @php
                $agentConfigJson = json_encode([
                    'server_url' => rtrim(config('app.url'), '/'),
                    'enrollment_token' => $enrollmentToken->token,
                ]);
            @endphp
            renderQr('qrcode-config', @json($agentConfigJson));
        @endif
    </script>
@endsection