@extends('layouts.app')

@section('title', '– Profilo')

@section('content')
    <div class="max-w-2xl">
        <h1 class="text-xl sm:text-2xl font-semibold text-gray-800 mb-6">
            Profilo
        </h1>

        {{-- Sezione: Dati Account --}}
        <div class="bg-white rounded-xl shadow p-6 mb-6">
            <h2 class="text-lg font-semibold text-gray-800 mb-4">Dati Account</h2>

            <form method="POST" action="{{ route('profile.update') }}" class="space-y-4">
                @csrf
                @method('PUT')

                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700 mb-1">
                        Email
                    </label>
                    <input type="email" id="email" name="email" value="{{ auth()->user()->email }}"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-600 focus:border-transparent text-sm"
                           required>
                    @error('email')
                        <p class="text-red-600 text-xs mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <button type="submit"
                        class="bg-green-800 text-white px-4 py-2 rounded-lg hover:bg-green-900 transition text-sm">
                    Salva Modifiche
                </button>
            </form>
        </div>

        {{-- Sezione: Cambia Password --}}
        <div class="bg-white rounded-xl shadow p-6 mb-6">
            <h2 class="text-lg font-semibold text-gray-800 mb-4">Cambia Password</h2>

            <form method="POST" action="{{ route('profile.change-password') }}" class="space-y-4">
                @csrf
                @method('PUT')

                <div>
                    <label for="current_password" class="block text-sm font-medium text-gray-700 mb-1">
                        Password Attuale
                    </label>
                    <input type="password" id="current_password" name="current_password"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-600 focus:border-transparent text-sm"
                           required>
                    @error('current_password')
                        <p class="text-red-600 text-xs mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="password" class="block text-sm font-medium text-gray-700 mb-1">
                        Nuova Password
                    </label>
                    <input type="password" id="password" name="password"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-600 focus:border-transparent text-sm"
                           required>
                    @error('password')
                        <p class="text-red-600 text-xs mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="password_confirmation" class="block text-sm font-medium text-gray-700 mb-1">
                        Conferma Password
                    </label>
                    <input type="password" id="password_confirmation" name="password_confirmation"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-600 focus:border-transparent text-sm"
                           required>
                </div>

                <button type="submit"
                        class="bg-green-800 text-white px-4 py-2 rounded-lg hover:bg-green-900 transition text-sm">
                    Cambia Password
                </button>
            </form>
        </div>

        {{-- Sezione: Elimina Account (solo per owner) --}}
        @if (auth()->user()->isOwner())
            <div class="bg-red-50 border border-red-200 rounded-xl p-6">
                <h2 class="text-lg font-semibold text-red-800 mb-4">Elimina Account</h2>
                <p class="text-sm text-red-700 mb-4">
                    Eliminando il tuo account, verranno eliminati anche l'azienda, tutti gli utenti
                    del team e tutti i dispositivi associati. Questa azione è irreversibile.
                </p>

                <button onclick="document.getElementById('delete-modal').classList.remove('hidden')"
                        class="bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700 transition text-sm">
                    Elimina Account
                </button>

                {{-- Modal di conferma eliminazione --}}
                <div id="delete-modal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
                    <div class="bg-white rounded-lg p-6 max-w-sm mx-4">
                        <h3 class="text-lg font-semibold text-gray-800 mb-2">
                            Sei Sicuro?
                        </h3>
                        <p class="text-sm text-gray-600 mb-4">
                            Questa azione eliminerà permanentemente il tuo account e tutti i dati associati.
                            Non potrà essere annullata.
                        </p>

                        <form method="POST" action="{{ route('profile.delete') }}" class="space-y-4">
                            @csrf
                            @method('DELETE')

                            <div>
                                <label for="delete_password" class="block text-sm font-medium text-gray-700 mb-1">
                                    Inserisci la tua password per confermare
                                </label>
                                <input type="password" id="delete_password" name="password"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-600 focus:border-transparent text-sm"
                                       required>
                                @error('password')
                                    <p class="text-red-600 text-xs mt-1">{{ $message }}</p>
                                @enderror
                            </div>

                            <div class="flex gap-3">
                                <button type="button"
                                        onclick="document.getElementById('delete-modal').classList.add('hidden')"
                                        class="flex-1 border border-gray-300 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-50 transition text-sm">
                                    Annulla
                                </button>
                                <button type="submit"
                                        class="flex-1 bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700 transition text-sm">
                                    Elimina Account
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        @endif
    </div>
@endsection
