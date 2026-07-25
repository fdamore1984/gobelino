@extends('layouts.app')

@section('title', '– Home')

@section('content')
    <h1 class="text-xl sm:text-2xl font-semibold text-gray-800">
        Welcome, {{ auth()->user()->company->name }}
    </h1>

    <div class="grid sm:grid-cols-2 gap-4 mt-6">
        <a href="{{ route('devices.index') }}" class="block bg-white p-5 rounded-xl shadow hover:shadow-md transition">
            <h2 class="font-medium text-gray-800">Devices</h2>
            <p class="text-sm text-gray-500 mt-1">Add and monitor your company's mobile devices.</p>
        </a>

        @if (auth()->user()->canManageUsers())
            <a href="{{ route('team.index') }}" class="block bg-white p-5 rounded-xl shadow hover:shadow-md transition">
                <h2 class="font-medium text-gray-800">Users</h2>
                <p class="text-sm text-gray-500 mt-1">Create new logins with different permissions for your team.</p>
            </a>
        @endif
    </div>
@endsection
