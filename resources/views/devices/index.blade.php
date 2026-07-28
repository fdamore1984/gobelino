@extends('layouts.app')

@section('title', '– Devices')

@section('content')
    <div class="flex items-center justify-between mb-4">
        <h1 class="text-xl sm:text-2xl font-semibold text-gray-800">Devices</h1>
    </div>

    @if (! $company->hasApnsConfigured())
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
    @endif

    <div class="flex flex-col sm:flex-row gap-3 mb-6">
        <form method="POST" action="{{ route('devices.enroll') }}">
            @csrf
            <input type="hidden" name="platform" value="android">
            <button type="submit"
                    class="bg-green-800 text-white px-4 py-2 rounded-lg hover:bg-green-900 transition text-sm">
                Add Android (generate QR)
            </button>
        </form>

        <form method="POST" action="{{ route('devices.enroll') }}">
            @csrf
            <input type="hidden" name="platform" value="ios">
            <button type="submit"
                    @disabled(! $company->hasApnsConfigured())
                    title="{{ $company->hasApnsConfigured() ? '' : 'Configure the APNs push certificate first' }}"
                    class="bg-gray-800 text-white px-4 py-2 rounded-lg hover:bg-black transition text-sm disabled:opacity-40 disabled:cursor-not-allowed">
                Add iPhone/iPad (generate QR)
            </button>
        </form>
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
                                <span class="text-[10px] px-1.5 py-0.5 rounded-full ml-1 {{ $device->isOnline() ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-500' }}">
                                    {{ $device->isOnline() ? 'online' : 'offline' }}
                                </span>
                                @if ($device->kiosk_enabled)
                                    <span class="text-[10px] px-1.5 py-0.5 rounded-full ml-1 bg-blue-100 text-blue-800">kiosk</span>
                                @endif
                            @endif
                        </p>
                        <p class="text-xs text-gray-500">
                            {{ $device->manufacturer }} {{ $device->model }}
                            @if ($device->android_version)
                                · Android {{ $device->android_version }}
                            @endif
                            @if ($device->isAndroid() && $device->last_poll_at)
                                · last check-in {{ $device->last_poll_at->diffForHumans() }}
                            @endif
                            @if ($device->battery_level !== null)
                                · {{ $device->battery_level }}% battery
                            @endif
                        </p>
                    </div>

                    @if ($device->isAndroid())
                        <div class="flex items-center gap-2 relative">
                            {{-- Command queue popover --}}
                            <div class="relative">
                                <button type="button"
                                        onclick="gobelinoToggle('queue-{{ $device->id }}')"
                                        title="Command queue"
                                        class="p-1.5 rounded-lg border border-gray-300 text-gray-600 hover:bg-gray-50">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3M3 11h18M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                    </svg>
                                </button>
                                <div id="queue-{{ $device->id }}" class="hidden absolute right-0 mt-2 w-64 bg-white border border-gray-200 rounded-lg shadow-lg z-20 text-sm">
                                    <div class="px-3 py-2 border-b text-xs font-medium text-gray-500">Command queue</div>
                                    @if ($device->commands->isEmpty())
                                        <p class="px-3 py-3 text-xs text-gray-400">No commands sent yet.</p>
                                    @else
                                        <ul class="max-h-64 overflow-y-auto divide-y">
                                            @foreach ($device->commands as $command)
                                                <li class="px-3 py-2 flex items-center justify-between gap-2">
                                                    <div>
                                                        <p class="text-gray-800 capitalize">{{ str_replace('_', ' ', $command->type) }}</p>
                                                        <p class="text-[11px] text-gray-400">{{ $command->created_at->diffForHumans() }}</p>
                                                    </div>
                                                    @php
                                                        $statusMap = [
                                                            'pending' => ['In coda', 'bg-gray-100 text-gray-600'],
                                                            'sent' => ['In coda', 'bg-yellow-100 text-yellow-800'],
                                                            'acked' => ['Consegnato', 'bg-green-100 text-green-800'],
                                                            'failed' => ['Fallito', 'bg-red-100 text-red-700'],
                                                        ];
                                                        [$label, $classes] = $statusMap[$command->status] ?? [$command->status, 'bg-gray-100 text-gray-600'];
                                                    @endphp
                                                    <span class="text-[10px] px-2 py-0.5 rounded-full whitespace-nowrap {{ $classes }}">{{ $label }}</span>
                                                </li>
                                            @endforeach
                                        </ul>
                                    @endif
                                </div>
                            </div>

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
                                <div id="actions-{{ $device->id }}" class="hidden absolute right-0 mt-2 w-44 bg-white border border-gray-200 rounded-lg shadow-lg z-20 text-sm overflow-hidden">
                                    <form method="POST" action="{{ route('devices.commands.store', $device) }}">
                                        @csrf
                                        <input type="hidden" name="type" value="lock">
                                        <button type="submit" class="w-full text-left px-3 py-2 text-gray-700 hover:bg-gray-50">Lock</button>
                                    </form>
                                    <form method="POST" action="{{ route('devices.commands.store', $device) }}">
                                        @csrf
                                        <input type="hidden" name="type" value="reboot">
                                        <button type="submit" class="w-full text-left px-3 py-2 text-gray-700 hover:bg-gray-50">Reboot</button>
                                    </form>
                                    <form method="POST" action="{{ route('devices.commands.store', $device) }}">
                                        @csrf
                                        <input type="hidden" name="type" value="set_kiosk">
                                        <button type="submit" class="w-full text-left px-3 py-2 text-gray-700 hover:bg-gray-50">
                                            {{ $device->kiosk_enabled ? 'Disable kiosk' : 'Enable kiosk' }}
                                        </button>
                                    </form>
                                    <form method="POST" action="{{ route('devices.commands.store', $device) }}"
                                          onsubmit="return confirm('Wipe this device? This cannot be undone.');">
                                        @csrf
                                        <input type="hidden" name="type" value="wipe">
                                        <button type="submit" class="w-full text-left px-3 py-2 text-red-700 hover:bg-red-50">Wipe</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    @else
                        <span class="text-xs px-2 py-1 rounded-full bg-yellow-100 text-yellow-800 self-start">
                            {{ $device->status }}
                        </span>
                    @endif
                </div>
            @endforeach
        </div>
    @endif

    <script>
        function gobelinoToggle(id) {
            const panel = document.getElementById(id);
            const isOpen = !panel.classList.contains('hidden');
            document.querySelectorAll('[id^="queue-"], [id^="actions-"]').forEach(el => el.classList.add('hidden'));
            if (!isOpen) panel.classList.remove('hidden');
        }
        document.addEventListener('click', function (event) {
            if (!event.target.closest('[onclick^="gobelinoToggle"]') && !event.target.closest('[id^="queue-"], [id^="actions-"]')) {
                document.querySelectorAll('[id^="queue-"], [id^="actions-"]').forEach(el => el.classList.add('hidden'));
            }
        });
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                document.querySelectorAll('[id^="queue-"], [id^="actions-"]').forEach(el => el.classList.add('hidden'));
            }
        });
    </script>
@endsection
