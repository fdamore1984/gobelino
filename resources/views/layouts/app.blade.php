<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Gobelino @yield('title', '')</title>
    <link rel="icon" href="/images/goblin-icon.svg" type="image/svg+xml">
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 min-h-screen text-gray-800">

    @auth
    <nav class="bg-green-800 text-white">
        <div class="max-w-5xl mx-auto px-4">
            <div class="flex items-center justify-between h-14">
                <a href="{{ route('dashboard') }}" class="flex items-center gap-2 font-semibold">
                    <img src="/images/goblin-icon.svg" alt="Gobelino" class="w-7 h-7">
                    Gobelino
                </a>

                <div class="hidden sm:flex items-center gap-6 text-sm">
                    <a href="{{ route('dashboard') }}" class="hover:text-green-200">Home</a>
                    <a href="{{ route('devices.index') }}" class="hover:text-green-200">Devices</a>
                    @if (auth()->user()->canManageUsers())
                        <a href="{{ route('team.index') }}" class="hover:text-green-200">Users</a>
                    @endif
                    <a href="{{ route('profile.show') }}" class="hover:text-green-200">Profile</a>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button class="hover:text-green-200">Log out</button>
                    </form>
                </div>

                <button class="sm:hidden" onclick="document.getElementById('mobile-menu').classList.toggle('hidden')">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                    </svg>
                </button>
            </div>

            <div id="mobile-menu" class="hidden sm:hidden pb-4 flex flex-col gap-3 text-sm">
                <a href="{{ route('dashboard') }}" class="hover:text-green-200">Home</a>
                <a href="{{ route('devices.index') }}" class="hover:text-green-200">Devices</a>
                @if (auth()->user()->canManageUsers())
                    <a href="{{ route('team.index') }}" class="hover:text-green-200">Users</a>
                @endif
                <a href="{{ route('profile.show') }}" class="hover:text-green-200">Profile</a>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button class="hover:text-green-200 text-left">Log out</button>
                </form>
            </div>
        </div>
    </nav>

    @if (auth()->user()->company->onTrial())
        <div class="bg-yellow-50 text-yellow-800 text-sm text-center py-2 px-4">
            Free trial active until {{ auth()->user()->company->trial_ends_at->format('d/m/Y') }}.
        </div>
    @endif
    @endauth

    <main class="max-w-5xl mx-auto px-4 py-6">
        @if (session('success'))
            <div class="mb-4 p-3 bg-green-50 text-green-800 rounded-lg text-sm">{{ session('success') }}</div>
        @endif
        @if (session('error'))
            <div class="mb-4 p-3 bg-red-50 text-red-700 rounded-lg text-sm">{{ session('error') }}</div>
        @endif

        @yield('content')
    </main>

    {{-- Toast notifications (e.g. "Command queued") --}}
    <div id="gobelino-toast" class="fixed bottom-4 right-4 z-50 flex flex-col gap-2 pointer-events-none"></div>

    <script>
        // --- Toast notifications ---
        function gobelinoToast(message, isError = false) {
            const container = document.getElementById('gobelino-toast');
            if (!container) return;
            const el = document.createElement('div');
            el.className = 'pointer-events-auto px-4 py-2 rounded-lg shadow text-sm text-white transition-opacity duration-300 ' +
                (isError ? 'bg-red-600' : 'bg-gray-800');
            el.textContent = message;
            container.appendChild(el);
            setTimeout(() => {
                el.classList.add('opacity-0');
                setTimeout(() => el.remove(), 300);
            }, 2500);
        }

        // --- Popovers (actions dropdown, etc.) ---
        function gobelinoToggle(id) {
            const panel = document.getElementById(id);
            if (!panel) return;
            const isOpen = !panel.classList.contains('hidden');
            document.querySelectorAll('.gobelino-popover').forEach(el => el.classList.add('hidden'));
            if (!isOpen) panel.classList.remove('hidden');
        }
        document.addEventListener('click', function (event) {
            if (!event.target.closest('[onclick^="gobelinoToggle"]') && !event.target.closest('.gobelino-popover')) {
                document.querySelectorAll('.gobelino-popover').forEach(el => el.classList.add('hidden'));
            }
        });
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                document.querySelectorAll('.gobelino-popover').forEach(el => el.classList.add('hidden'));
            }
        });

        // --- Command sending (AJAX, no page reload) ---
        function gobelinoSendCommand(deviceId, type) {
            return fetch('{{ url('/devices') }}/' + deviceId + '/commands', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
                body: JSON.stringify({ type: type }),
            }).then(res => res.json().then(data => ({ ok: res.ok, data: data })));
        }

        function gobelinoCommandClick(button, deviceId, type, confirmMessage) {
            if (confirmMessage && !confirm(confirmMessage)) return;

            const popover = button.closest('.gobelino-popover');
            button.disabled = true;

            gobelinoSendCommand(deviceId, type).then(function (result) {
                button.disabled = false;
                if (popover) popover.classList.add('hidden');

                if (result.ok && result.data.success) {
                    gobelinoToast(result.data.message || 'Comando inviato.');

                    if (type === 'set_kiosk') {
                        const badge = document.getElementById('kiosk-badge-' + deviceId);
                        if (badge) badge.classList.toggle('hidden', !result.data.kiosk_enabled);
                        const label = document.getElementById('kiosk-action-label-' + deviceId);
                        if (label) label.textContent = result.data.kiosk_enabled ? 'Disable kiosk' : 'Enable kiosk';
                    }

                    // Se la pagina corrente espone un refresh immediato
                    // (es. la pagina della coda comandi), lo richiamiamo
                    // cosi' il nuovo comando appare subito senza aspettare
                    // il prossimo tick da 8s.
                    if (typeof gobelinoRefreshNow === 'function') gobelinoRefreshNow();
                } else {
                    gobelinoToast((result.data && result.data.message) || 'Errore durante l\'invio del comando.', true);
                }
            }).catch(function () {
                button.disabled = false;
                gobelinoToast('Errore di rete durante l\'invio del comando.', true);
            });
        }

        // --- Shared rendering for the command queue list ---
        const GOBELINO_STATUS_LABELS = {
            pending: ['In coda', 'bg-gray-100 text-gray-600'],
            sent: ['Consegnato', 'bg-yellow-100 text-yellow-800'],
            acked: ['Eseguito', 'bg-green-100 text-green-800'],
            failed: ['Fallito', 'bg-red-100 text-red-700'],
        };

        function gobelinoRenderQueue(commands) {
            if (!commands.length) {
                return '<p class="px-4 py-4 text-xs text-gray-400">No commands sent yet.</p>';
            }
            return '<ul class="divide-y">' + commands.map(function (command) {
                const [label, classes] = GOBELINO_STATUS_LABELS[command.status] || [command.status, 'bg-gray-100 text-gray-600'];
                const typeLabel = command.type.replace(/_/g, ' ');
                return '<li class="px-4 py-3 flex items-center justify-between gap-2">' +
                    '<div><p class="text-gray-800 capitalize">' + typeLabel + '</p>' +
                    '<p class="text-[11px] text-gray-400">' + command.created_at_human + '</p></div>' +
                    '<span class="text-[10px] px-2 py-0.5 rounded-full whitespace-nowrap ' + classes + '">' + label + '</span>' +
                    '</li>';
            }).join('') + '</ul>';
        }
    </script>
</body>
</html>
