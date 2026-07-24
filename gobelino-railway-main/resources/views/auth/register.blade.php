<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Gobelino – Crea il tuo account</title>
    <link rel="icon" href="/images/goblin-icon.svg" type="image/svg+xml">
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 min-h-screen flex items-center justify-center px-4">
    <div class="w-full max-w-md bg-white p-6 sm:p-8 rounded-xl shadow">
        <div class="flex items-center gap-2 mb-6">
            <img src="/images/goblin-icon.svg" alt="Gobelino" class="w-9 h-9">
            <h1 class="text-xl font-semibold text-gray-800">Crea il tuo account Gobelino</h1>
        </div>

        <p class="text-sm text-gray-500 mb-6">Un mese di prova gratuita, nessuna carta richiesta.</p>

        @if ($errors->any())
            <div class="mb-4 p-3 bg-red-50 text-red-700 rounded-lg text-sm">
                <ul class="list-disc list-inside space-y-1">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('register') }}" class="space-y-4">
            @csrf
            <div>
                <label class="block text-sm font-medium text-gray-700">Nome azienda</label>
                <input name="company_name" type="text" value="{{ old('company_name') }}" required
                       class="mt-1 w-full rounded-lg border-gray-300 shadow-sm focus:ring-green-600 focus:border-green-600">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Email</label>
                <input name="email" type="email" value="{{ old('email') }}" required
                       class="mt-1 w-full rounded-lg border-gray-300 shadow-sm focus:ring-green-600 focus:border-green-600">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Password</label>
                <input name="password" type="password" required
                       class="mt-1 w-full rounded-lg border-gray-300 shadow-sm focus:ring-green-600 focus:border-green-600">
                <p class="text-xs text-gray-500 mt-1">Minimo 8 caratteri, maiuscole, minuscole e numeri.</p>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Conferma password</label>
                <input name="password_confirmation" type="password" required
                       class="mt-1 w-full rounded-lg border-gray-300 shadow-sm focus:ring-green-600 focus:border-green-600">
            </div>
            <button type="submit" class="w-full bg-green-800 text-white py-2 rounded-lg hover:bg-green-900 transition">
                Crea account
            </button>
        </form>

        <p class="text-sm text-gray-500 mt-4 text-center">
            Hai già un account? <a href="{{ route('login') }}" class="text-green-800 hover:underline">Accedi</a>
        </p>
    </div>
</body>
</html>
