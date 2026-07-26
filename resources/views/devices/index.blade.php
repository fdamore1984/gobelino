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
                        <div class="flex flex-wrap gap-2">
                            <form method="POST" action="{{ route('devices.commands.store', $device) }}">
                                @csrf
                                <input type="hidden" name="type" value="lock">
                                <button type="submit" class="text-xs px-3 py-1.5 rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-50">Lock</button>
                            </form>
                            <form method="POST" action="{{ route('devices.commands.store', $device) }}">
                                @csrf
                                <input type="hidden" name="type" value="reboot">
                                <button type="submit" class="text-xs px-3 py-1.5 rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-50">Reboot</button>
                            </form>
                            <form method="POST" action="{{ route('devices.commands.store', $device) }}">
                                @csrf
                                <input type="hidden" name="type" value="set_kiosk">
                                <button type="submit" class="text-xs px-3 py-1.5 rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-50">
                                    {{ $device->kiosk_enabled ? 'Disable kiosk' : 'Enable kiosk' }}
                                </button>
                            </form>
                            <form method="POST" action="{{ route('devices.commands.store', $device) }}"
                                  onsubmit="return confirm('Wipe this device? This cannot be undone.');">
                                @csrf
                                <input type="hidden" name="type" value="wipe">
                                <button type="submit" class="text-xs px-3 py-1.5 rounded-lg border border-red-300 text-red-700 hover:bg-red-50">Wipe</button>
                            </form>
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
@endsection
