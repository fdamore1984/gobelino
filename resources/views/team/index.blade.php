@extends('layouts.app')

@section('title', '– Utenti')

@section('content')
    <h1 class="text-xl sm:text-2xl font-semibold text-gray-800 mb-4">Utenti del team</h1>

    @if (session('temporary_password'))
        <div class="bg-green-50 text-green-800 text-sm rounded-xl p-4 mb-6">
            Utenza creata per <strong>{{ session('temporary_password_email') }}</strong>.
            Password temporanea: <code class="bg-white px-2 py-1 rounded">{{ session('temporary_password') }}</code>
        </div>
    @endif

    <div class="bg-white rounded-xl shadow p-5 mb-6">
        <h2 class="font-medium text-gray-800 mb-3">Crea una nuova utenza</h2>
        <form method="POST" action="{{ route('team.store') }}" class="flex flex-col sm:flex-row gap-3">
            @csrf
            <input type="email" name="email" placeholder="Email" required
                   class="flex-1 rounded-lg border-gray-300 shadow-sm focus:ring-green-600 focus:border-green-600 text-sm">
            <select name="role" class="rounded-lg border-gray-300 shadow-sm focus:ring-green-600 focus:border-green-600 text-sm">
                <option value="member">Membro (accesso base)</option>
                <option value="admin">Amministratore</option>
            </select>
            <button type="submit" class="bg-green-800 text-white px-4 py-2 rounded-lg hover:bg-green-900 transition text-sm">
                Crea utenza
            </button>
        </form>
        @error('email')
            <p class="text-red-600 text-xs mt-2">{{ $message }}</p>
        @enderror
    </div>

    <div class="bg-white rounded-xl shadow divide-y">
        @foreach ($users as $user)
            <div class="p-4 flex items-center justify-between">
                <div>
                    <p class="font-medium text-gray-800">{{ $user->email }}</p>
                    <p class="text-xs text-gray-500 capitalize">{{ $user->role }}</p>
                </div>
                @if (! $user->isOwner() && $user->id !== auth()->id())
                    <form method="POST" action="{{ route('team.destroy', $user) }}">
                        @csrf
                        @method('DELETE')
                        <button class="text-red-600 text-sm hover:underline">Rimuovi</button>
                    </form>
                @endif
            </div>
        @endforeach
    </div>
@endsection
