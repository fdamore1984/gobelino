@extends('layouts.app')

@section('title', '– Dispositivi')

@section('content')
    <div class="flex items-center justify-between mb-4">
        <h1 class="text-xl sm:text-2xl font-semibold text-gray-800">Dispositivi</h1>
    </div>

    @if (! $company->hasAndroidEnterprise())
        <div class="bg-white rounded-xl shadow p-6 text-center mb-6">
            <h2 class="font-medium text-gray-800 mb-2">Collega Android Enterprise</h2>
            <p class="text-sm text-gray-500 mb-4">
                Prima di poter aggiungere dispositivi Android, devi collegare
                l'account Android Enterprise della tua azienda. È gratuito e
                richiede un account Google aziendale.
            </p>
            <a href="{{ route('android-enterprise.connect') }}"
               class="inline-block bg-green-800 text-white px-4 py-2 rounded-lg hover:bg-green-900 transition text-sm">
                Collega Android Enterprise
            </a>
        </div>
    @endif

    @if (! $company->hasApnsConfigured())
        <div class="bg-white rounded-xl shadow p-6 text-center mb-6">
            <h2 class="font-medium text-gray-800 mb-2">Configura il certificato push APNs</h2>
            <p class="text-sm text-gray-500 mb-4">
                Prima di poter aggiungere iPhone/iPad, devi configurare il
                certificato push APNs della tua azienda presso Apple.
            </p>
            <a href="{{ route('apns.configure') }}"
               class="inline-block bg-gray-800 text-white px-4 py-2 rounded-lg hover:bg-black transition text-sm">
                Configura APNs
            </a>
        </div>
    @endif

    <div class="flex flex-col sm:flex-row gap-3 mb-6">
        <form method="POST" action="{{ route('devices.enroll') }}">
            @csrf
            <input type="hidden" name="platform" value="android">
            <button type="submit"
                    @disabled(! $company->hasAndroidEnterprise())
                    class="bg-green-800 text-white px-4 py-2 rounded-lg hover:bg-green-900 transition text-sm disabled:opacity-40 disabled:cursor-not-allowed">
                Aggiungi Android (genera QR)
            </button>
        </form>

        <form method="POST" action="{{ route('devices.enroll') }}">
            @csrf
            <input type="hidden" name="platform" value="ios">
            <button type="submit"
                    @disabled(! $company->hasApnsConfigured())
                    title="{{ $company->hasApnsConfigured() ? '' : 'Configura prima il certificato push APNs' }}"
                    class="bg-gray-800 text-white px-4 py-2 rounded-lg hover:bg-black transition text-sm disabled:opacity-40 disabled:cursor-not-allowed">
                Aggiungi iPhone/iPad (genera QR)
            </button>
        </form>

        @if ($company->hasAndroidEnterprise())
            <form method="POST" action="{{ route('devices.sync') }}">
                @csrf
                <button type="submit" class="bg-white border border-gray-300 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-50 transition text-sm">
                    Sincronizza dispositivi
                </button>
            </form>
        @endif
    </div>



    @if ($company->hasAndroidEnterprise() || $company->hasApnsConfigured())

        @if ($devices->isEmpty())
            <div class="bg-white rounded-xl shadow p-8 text-center text-gray-500 text-sm">
                Nessun dispositivo ancora iscritto. Genera un QR e scansionalo
                con un dispositivo Android nuovo (o resettato) in fase di
                configurazione iniziale.
            </div>
        @else
            <div class="bg-white rounded-xl shadow divide-y">
                @foreach ($devices as $device)
                    <div class="p-4 flex items-center justify-between">
                        <div>
                            <p class="font-medium text-gray-800">
                                {{ $device->name ?? 'Dispositivo senza nome' }}
                                <span class="text-[10px] uppercase tracking-wide text-gray-400 ml-1">{{ $device->platform }}</span>
                            </p>
                            <p class="text-xs text-gray-500">
                                {{ $device->manufacturer }} {{ $device->model }}
                                @if ($device->android_version)
                                    · Android {{ $device->android_version }}
                                @endif
                            </p>
                        </div>
                        <span class="text-xs px-2 py-1 rounded-full bg-yellow-100 text-yellow-800">
                            {{ $device->status }}
                        </span>
                    </div>
                @endforeach
            </div>
        @endif
    @endif
@endsection
