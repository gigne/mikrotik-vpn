<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Two-Factor Authentication</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow-sm rounded-lg p-8 space-y-6">
                @if (session('success'))
                    <div class="bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded">{{ session('success') }}</div>
                @endif

                <div>
                    <h3 class="font-semibold text-gray-900">1. Scan the QR code</h3>
                    <p class="text-sm text-gray-500 mt-1">Use Google Authenticator, Aegis, or any TOTP app.</p>
                    <div class="mt-4 flex justify-center">
                        <img src="{{ $qrDataUrl }}" alt="TOTP QR code" class="w-56 h-56">
                    </div>
                    <p class="mt-4 text-xs text-gray-500 text-center">
                        Manual entry secret: <code class="font-mono">{{ $secret }}</code>
                    </p>
                </div>

                <form method="POST" action="{{ route('two-factor.activate') }}" class="space-y-4">
                    @csrf
                    <div>
                        <label class="block font-medium text-sm text-gray-700">2. Enter the 6-digit code</label>
                        <input type="text" name="code" required maxlength="6" inputmode="numeric"
                               class="mt-1 block w-full border-gray-300 rounded-md text-sm font-mono"
                               placeholder="000000">
                        @error('code') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    <button type="submit"
                            class="w-full px-4 py-2 bg-indigo-600 rounded-md font-semibold text-sm text-white hover:bg-indigo-500">
                        Activate 2FA
                    </button>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>