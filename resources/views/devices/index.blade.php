@extends('layouts.app')

@section('title', '– Devices')

@section('content')
    <div class="flex items-center justify-between mb-4">
        <h1 class="text-xl sm:text-2xl font-semibold text-gray-800">Devices</h1>
    </div>

    {{-- Apple/iOS management is on hold for now — only Android is
         active. Not deleted, just hidden: uncomment when iOS support
         is turned back on. --}}
    {{-- @if (! $company->hasApnsConfigured())
        <div class="bg-white rounded-xl shadow p-6 text-center mb-6">
            <h2 class="font-medium text-gray-800 mb-2">Configure the APNs push certificate</h2>
            <p class="text-sm text-gray-500 mb-4">
                Before you can add iPhone/iPad devices, you need to configure
                your company's APNs push certificate with Apple.
            </p>
            <a href="{{ route('apns.configure') }}"
               class="inline-block bg-gray-800 text-white px-4 py-2 rounded-lg hover:bg-black transition text-sm">
                Configure APNs
            </a>
        </div>
    @endif --}}

    <div class="flex flex-col sm:flex-row gap-3 mb-6">
        <form method="POST" action="{{ route('devices.enroll') }}">
            @csrf
            <input type="hidden" name="platform" value="android">
            <button type="submit"
                    class="bg-green-800 text-white px-4 py-2 rounded-lg hover:bg-green-900 transition text-sm">
                Add Android (generate QR)
            </button>
        </form>

        {{-- <form method="POST" action="{{ route('devices.enroll') }}">
            @csrf
            <input type="hidden" name="platform" value="ios">
            <button type="submit"
                    @disabled(! $company->hasApnsConfigured())
                    title="{{ $company->hasApnsConfigured() ? '' : 'Configure the APNs push certificate first' }}"
                    class="bg-gray-800 text-white px-4 py-2 rounded-lg hover:bg-black transition text-sm disabled:opacity-40 disabled:cursor-not-allowed">
                Add iPhone/iPad (generate QR)
            </button>
        </form> --}}
    </div>

    @if ($devices->isEmpty())
        <div class="bg-white rounded-xl shadow p-8 text-center text-gray-500 text-sm">
            No devices enrolled yet. Generate a QR code: for Android, scan
            it during the setup wizard of a new or factory-reset device
            (tap the welcome screen 6 times to unlock QR provisioning);
            our agent app will install itself and check in automatically.
        </div>
    @else
        <div class="bg-white rounded-xl shadow divide-y">
            @foreach ($devices as $device)
                <div class="p-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                    <div>
                        <p class="font-medium text-gray-800">
                            {{ $device->name ?? 'Unnamed device' }}
                            <span class="text-[10px] uppercase tracking-wide text-gray-400 ml-1">{{ $device->platform }}</span>
                            @if ($device->isAndroid())
                                <span id="online-badge-{{ $device->id }}"
                                      class="text-[10px] px-1.5 py-0.5 rounded-full ml-1 {{ $device->isOnline() ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-500' }}">
                                    {{ $device->isOnline() ? 'online' : 'offline' }}
                                </span>
                                <span id="kiosk-badge-{{ $device->id }}"
                                      class="text-[10px] px-1.5 py-0.5 rounded-full ml-1 bg-blue-100 text-blue-800 {{ $device->kiosk_enabled ? '' : 'hidden' }}">
                                    kiosk
                                </span>
                            @endif
                        </p>
                        <p class="text-xs text-gray-500">
                            {{ $device->manufacturer }} {{ $device->model }}
                            @if ($device->android_version)
                                · Android {{ $device->android_version }}
                            @endif
                            @if ($device->isAndroid())
                                · S/N <span id="serial-{{ $device->id }}">{{ $device->serial_number ?? '—' }}</span>
                                @if ($device->last_poll_at)
                                    · last check-in <span id="last-checkin-{{ $device->id }}">{{ $device->last_poll_at->diffForHumans() }}</span>
                                @endif
                            @endif
                            @if ($device->battery_level !== null)
                                · <span id="battery-{{ $device->id }}">{{ $device->battery_level }}</span>% battery
                            @endif
                        </p>
                    </div>

                    @if ($device->isAndroid())
                        <div class="flex items-center gap-2 relative">
                            {{-- Command queue: full page, not a popover --}}
                            <a href="{{ route('devices.queue', $device) }}"
                               title="Command queue"
                               class="p-1.5 rounded-lg border border-gray-300 text-gray-600 hover:bg-gray-50">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3M3 11h18M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                </svg>
                            </a>

                            {{-- Actions dropdown --}}
                            <div class="relative">
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
                                    <button type="button" class="w-full text-left px-3 py-2 text-red-700 hover:bg-red-50"
                                            onclick="gobelinoCommandClick(this, {{ $device->id }}, 'wipe', 'Wipe this device? This cannot be undone.')">Wipe</button>
                                    <form method="POST" action="{{ route('devices.destroy', $device) }}"
                                          class="border-t border-gray-100"
                                          onsubmit="return confirm('Remove this device from the panel? This only forgets it here, it does not wipe the physical device.');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="w-full text-left px-3 py-2 text-red-700 hover:bg-red-50">Remove device</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    @else
                        <div class="flex items-center gap-2">
                            <span class="text-xs px-2 py-1 rounded-full bg-yellow-100 text-yellow-800 self-start">
                                {{ $device->status }}
                            </span>
                            <form method="POST" action="{{ route('devices.destroy', $device) }}"
                                  onsubmit="return confirm('Remove this device from the panel?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" title="Remove device" class="p-1.5 rounded-lg border border-gray-300 text-red-700 hover:bg-red-50">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M9 7V4a1 1 0 011-1h4a1 1 0 011 1v3M5 7h14" />
                                    </svg>
                                </button>
                            </form>
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    @endif

    <script>
        // --- Live refresh: polls /devices/status every few seconds and
        // patches the DOM in place, no F5 needed. Doesn't touch open
        // popovers' visibility, only their content, so it won't
        // interrupt someone mid-click.
        // (gobelinoToggle, gobelinoToast, gobelinoCommandClick and
        // gobelinoRenderQueue live in layouts/app.blade.php, shared
        // with the command queue page.)
        function gobelinoRefreshDevices() {
            fetch('{{ route('devices.status') }}', { headers: { 'Accept': 'application/json' } })
                .then(res => res.ok ? res.json() : Promise.reject())
                .then(data => {
                    data.devices.forEach(function (device) {
                        const onlineBadge = document.getElementById('online-badge-' + device.id);
                        if (onlineBadge && device.online !== null) {
                            onlineBadge.textContent = device.online ? 'online' : 'offline';
                            onlineBadge.className = 'text-[10px] px-1.5 py-0.5 rounded-full ml-1 ' +
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
                    });
                })
                .catch(() => { /* transient network hiccup: just try again next tick */ });
        }

        setInterval(gobelinoRefreshDevices, 8000);
    </script>
@endsection
