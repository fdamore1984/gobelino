@extends('layouts.app')

@section('title', '– Command queue')

@section('content')
    <div class="flex items-start justify-between gap-3 mb-6">
        <div class="flex items-start gap-3">
            <a href="{{ route('devices.index') }}"
               title="Back to devices"
               class="p-1.5 mt-0.5 rounded-lg border border-gray-300 text-gray-600 hover:bg-gray-50 shrink-0">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                </svg>
            </a>
            <div>
                <h1 class="text-xl sm:text-2xl font-semibold text-gray-800">
                    {{ $device->name ?? 'Unnamed device' }}
                    <span id="online-badge-{{ $device->id }}"
                          class="text-[10px] px-1.5 py-0.5 rounded-full ml-1 align-middle {{ $device->isOnline() ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-500' }}">
                        {{ $device->isOnline() ? 'online' : 'offline' }}
                    </span>
                    <span id="kiosk-badge-{{ $device->id }}"
                          class="text-[10px] px-1.5 py-0.5 rounded-full ml-1 align-middle bg-blue-100 text-blue-800 {{ $device->kiosk_enabled ? '' : 'hidden' }}">
                        kiosk
                    </span>
                </h1>
                <p class="text-xs text-gray-500 mt-1">
                    {{ $device->manufacturer }} {{ $device->model }}
                    @if ($device->android_version)
                        · Android {{ $device->android_version }}
                    @endif
                    · S/N <span id="serial-{{ $device->id }}">{{ $device->serial_number ?? '—' }}</span>
                    @if ($device->last_poll_at)
                        · last check-in <span id="last-checkin-{{ $device->id }}">{{ $device->last_poll_at->diffForHumans() }}</span>
                    @endif
                    @if ($device->battery_level !== null)
                        · <span id="battery-{{ $device->id }}">{{ $device->battery_level }}</span>% battery
                    @endif
                </p>
            </div>
        </div>

        {{-- Actions dropdown: sends commands to this device (AJAX, no reload) --}}
        <div class="relative shrink-0">
            <button type="button"
                    onclick="gobelinoToggle('actions-{{ $device->id }}')"
                    title="Actions"
                    class="p-1.5 rounded-lg border border-gray-300 text-gray-600 hover:bg-gray-50">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                    <circle cx="5" cy="12" r="2"/><circle cx="12" cy="12" r="2"/><circle cx="19" cy="12" r="2"/>
                </svg>
            </button>
            <div id="actions-{{ $device->id }}" class="gobelino-popover hidden absolute right-0 mt-2 w-44 bg-white border border-gray-200 rounded-lg shadow-lg z-20 text-sm overflow-hidden">
                <button type="button" class="w-full text-left px-3 py-2 text-gray-700 hover:bg-gray-50"
                        onclick="gobelinoCommandClick(this, {{ $device->id }}, 'lock')">Lock</button>
                <button type="button" class="w-full text-left px-3 py-2 text-gray-700 hover:bg-gray-50"
                        onclick="gobelinoCommandClick(this, {{ $device->id }}, 'reboot')">Reboot</button>
                <button type="button" id="kiosk-action-label-{{ $device->id }}"
                        class="w-full text-left px-3 py-2 text-gray-700 hover:bg-gray-50"
                        onclick="gobelinoCommandClick(this, {{ $device->id }}, 'set_kiosk')">
                    {{ $device->kiosk_enabled ? 'Disable kiosk' : 'Enable kiosk' }}
                </button>
                <button type="button" class="w-full text-left px-3 py-2 text-red-700 hover:bg-red-50 border-t border-gray-100"
                        onclick="gobelinoCommandClick(this, {{ $device->id }}, 'wipe', 'Wipe this device? This cannot be undone.')">Wipe</button>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow">
        <div class="px-4 py-3 border-b text-sm font-medium text-gray-600">Command queue</div>
        <div id="queue-list-{{ $device->id }}">
            @include('devices.partials.queue-list', ['commands' => $device->commands])
        </div>
    </div>

    <script>
        // Refresh this device's status + full queue every 8s, same
        // cadence/logic as the devices list page. gobelinoCommandClick
        // (in layouts/app.blade.php) calls gobelinoRefreshNow() right
        // after a command is queued, so it shows up immediately too.
        function gobelinoRefreshNow() {
            fetch('{{ route('devices.device-status', $device) }}', { headers: { 'Accept': 'application/json' } })
                .then(res => res.ok ? res.json() : Promise.reject())
                .then(data => {
                    const device = data.device;

                    const onlineBadge = document.getElementById('online-badge-' + device.id);
                    if (onlineBadge && device.online !== null) {
                        onlineBadge.textContent = device.online ? 'online' : 'offline';
                        onlineBadge.className = 'text-[10px] px-1.5 py-0.5 rounded-full ml-1 align-middle ' +
                            (device.online ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-500');
                    }

                    const kioskBadge = document.getElementById('kiosk-badge-' + device.id);
                    if (kioskBadge) kioskBadge.classList.toggle('hidden', !device.kiosk_enabled);

                    const kioskLabel = document.getElementById('kiosk-action-label-' + device.id);
                    if (kioskLabel) kioskLabel.textContent = device.kiosk_enabled ? 'Disable kiosk' : 'Enable kiosk';

                    const serialEl = document.getElementById('serial-' + device.id);
                    if (serialEl && device.serial_number) serialEl.textContent = device.serial_number;

                    const lastCheckinEl = document.getElementById('last-checkin-' + device.id);
                    if (lastCheckinEl && device.last_poll_at_human) lastCheckinEl.textContent = device.last_poll_at_human;

                    const batteryEl = document.getElementById('battery-' + device.id);
                    if (batteryEl && device.battery_level !== null) batteryEl.textContent = device.battery_level;

                    const queueList = document.getElementById('queue-list-' + device.id);
                    if (queueList) queueList.innerHTML = gobelinoRenderQueue(device.commands);
                })
                .catch(() => { /* transient network hiccup: just try again next tick */ });
        }

        setInterval(gobelinoRefreshNow, 8000);
    </script>
@endsection
