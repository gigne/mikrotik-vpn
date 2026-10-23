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

                <div class="flex items-center gap-3">
                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800">
                        Enabled
                    </span>
                    <p class="text-sm text-gray-600">Your account is protected with TOTP.</p>
                </div>

                <form method="POST" action="{{ route('two-factor.disable') }}"
                      onsubmit="return confirm('Disable two-factor authentication?');">
                    @csrf
                    <button type="submit"
                            class="px-4 py-2 bg-red-600 rounded-md font-semibold text-sm text-white hover:bg-red-500">
                        Disable 2FA
                    </button>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>