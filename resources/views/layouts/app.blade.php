<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
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
                    <a href="{{ route('devices.index') }}" class="hover:text-green-200">Dispositivi</a>
                    @if (auth()->user()->canManageUsers())
                        <a href="{{ route('team.index') }}" class="hover:text-green-200">Utenti</a>
                    @endif
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button class="hover:text-green-200">Esci</button>
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
                <a href="{{ route('devices.index') }}" class="hover:text-green-200">Dispositivi</a>
                @if (auth()->user()->canManageUsers())
                    <a href="{{ route('team.index') }}" class="hover:text-green-200">Utenti</a>
                @endif
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button class="hover:text-green-200 text-left">Esci</button>
                </form>
            </div>
        </div>
    </nav>

    @if (auth()->user()->company->onTrial())
        <div class="bg-yellow-50 text-yellow-800 text-sm text-center py-2 px-4">
            Prova gratuita attiva fino al {{ auth()->user()->company->trial_ends_at->format('d/m/Y') }}.
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
</body>
</html>
