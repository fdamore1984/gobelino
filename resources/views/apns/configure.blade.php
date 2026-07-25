@extends('layouts.app')

@section('title', '– APNs Configuration')

@section('content')
    <div class="max-w-3xl">
        <h1 class="text-xl sm:text-2xl font-semibold text-gray-800 mb-2">
            APNs Push Certificate Configuration
        </h1>
        <p class="text-sm text-gray-500 mb-6">
            To enroll iPhone/iPad devices, Apple requires every MDM to have
            its own APNs push certificate, valid for 1 year. Unlike
            Android Enterprise, this step isn't automatic, but it only
            needs to be done once by following the two steps below.
        </p>

        {{-- Active certificate --}}
        @if ($company->hasApnsConfigured())
            <div class="bg-green-50 border border-green-200 rounded-lg p-6 mb-6">
                <h2 class="text-lg font-semibold text-green-800 mb-2">
                    ✅ Active certificate
                </h2>
                <p class="text-sm text-green-700">
                    The APNs push certificate is configured and working,
                    valid until {{ $company->apns_expires_at->format('d/m/Y') }}.
                </p>
                @if ($company->apns_expires_at->diffInDays() < 30)
                    <p class="text-sm text-orange-600 mt-2">
                        ⚠️ Expires in {{ $company->apns_expires_at->diffInDays() }} days: repeat the steps
                        below with the same Apple ID used the first time to renew it.
                    </p>
                @endif
            </div>
        @endif

        {{-- Step 1: request to mdmcert.download --}}
        <div class="bg-white rounded-xl shadow p-6 mb-6">
            <div class="flex items-center gap-2 mb-2">
                <span class="flex items-center justify-center w-6 h-6 rounded-full text-xs font-semibold
                    {{ $company->isAwaitingApnsCertificate() || $company->hasApnsConfigured() ? 'bg-green-800 text-white' : 'bg-gray-200 text-gray-700' }}">1</span>
                <h2 class="text-lg font-semibold text-gray-800">
                    Request the certificate signature
                </h2>
            </div>
            <p class="text-sm text-gray-600 mb-4">
                Enter the email you're registered with on
                <a href="https://mdmcert.download" target="_blank" rel="noopener" class="text-green-800 underline">mdmcert.download</a>
                (the free service that signs the push certificate on Apple's behalf).
                We'll generate the signing request (CSR) and send it to you: you'll receive
                the signed CSR directly at that email address.
            </p>

            <form method="POST" action="{{ route('apns.request-csr') }}" class="flex flex-col sm:flex-row gap-3 sm:items-start">
                @csrf
                <div class="flex-1">
                    <input type="email" name="email" placeholder="youremail@example.com"
                           value="{{ old('email', $company->apns_mdmcert_email) }}"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-600 focus:border-transparent text-sm"
                           required>
                    @error('email')
                        <p class="text-red-600 text-xs mt-1">{{ $message }}</p>
                    @enderror
                </div>
                <button type="submit"
                        class="bg-green-800 text-white px-6 py-2 rounded-lg hover:bg-green-900 transition text-sm font-medium whitespace-nowrap">
                    Send request
                </button>
            </form>

            @if ($company->isAwaitingApnsCertificate())
                <p class="text-xs text-green-700 mt-3">
                    Request sent to <strong>{{ $company->apns_mdmcert_email }}</strong>
                    on {{ $company->apns_csr_submitted_at?->format('d/m/Y H:i') }}.
                    Check your inbox: once you receive the signed CSR, follow
                    it through to Step 2 below.
                </p>
            @endif
        </div>

        {{-- Step 2: upload the final certificate --}}
        <div class="bg-white rounded-xl shadow p-6">
            <div class="flex items-center gap-2 mb-2">
                <span class="flex items-center justify-center w-6 h-6 rounded-full text-xs font-semibold
                    {{ $company->hasApnsConfigured() ? 'bg-green-800 text-white' : 'bg-gray-200 text-gray-700' }}">2</span>
                <h2 class="text-lg font-semibold text-gray-800">
                    Upload the signed certificate
                </h2>
            </div>
            <p class="text-sm text-gray-600 mb-4">
                Open the signed CSR you received by email and upload it to
                <a href="https://identity.apple.com/pushcert/" target="_blank" rel="noopener" class="text-green-800 underline">identity.apple.com/pushcert</a>
                (Apple Push Certificates Portal): Apple will give you back a
                <code class="bg-gray-100 px-1 rounded">.pem</code> file. Upload it below to complete the configuration.
            </p>

            <form method="POST" action="{{ route('apns.upload-certificate') }}" enctype="multipart/form-data" class="flex flex-col sm:flex-row gap-3 items-start sm:items-center">
                @csrf
                <input type="file" name="certificate" accept=".pem,.crt,.cer" required
                       class="text-sm text-gray-600 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:bg-gray-100 file:text-sm file:text-gray-700 hover:file:bg-gray-200">
                <button type="submit"
                        class="bg-gray-800 text-white px-4 py-2 rounded-lg hover:bg-black transition text-sm whitespace-nowrap">
                    Upload certificate
                </button>
            </form>
            @error('certificate')
                <p class="text-red-600 text-xs mt-2">{{ $message }}</p>
            @enderror
        </div>

        <p class="text-xs text-gray-400 mt-6">
            Note: the push certificate must be renewed every year with the same
            Apple ID/email used the first time, otherwise all already enrolled
            iOS devices stop responding to MDM commands.
        </p>
    </div>
@endsection
