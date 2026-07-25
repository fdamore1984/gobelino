@extends('layouts.app')

@section('title', '– Home')

@section('content')
    <h1 class="text-xl sm:text-2xl font-semibold text-gray-800">
        Benvenuto, {{ auth()->user()->company->name }}
    </h1>

    <div class="grid sm:grid-cols-2 gap-4 mt-6">
        <a href="{{ route('devices.index') }}" class="block bg-white p-5 rounded-xl shadow hover:shadow-md transition">
            <h2 class="font-medium text-gray-800">Dispositivi</h2>
            <p class="text-sm text-gray-500 mt-1">Aggiungi e monitora i dispositivi mobile aziendali.</p>
        </a>

        @if (auth()->user()->canManageUsers())
            <a href="{{ route('team.index') }}" class="block bg-white p-5 rounded-xl shadow hover:shadow-md transition">
                <h2 class="font-medium text-gray-800">Utenti</h2>
                <p class="text-sm text-gray-500 mt-1">Crea nuovi accessi con permessi diversi per il tuo team.</p>
            </a>
        @endif
    </div>
@endsection
