<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Profile') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                <div class="max-w-xl">
                    @include('profile.partials.update-profile-information-form')
                </div>
            </div>

            <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                <div class="max-w-xl">
                    @include('profile.partials.update-password-form')
                </div>
            </div>

            <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                <div class="max-w-xl">
                    <div class="flex items-center justify-between">
                        <h3 class="text-lg font-medium text-gray-900">Two-Factor Authentication</h3>
                        <a href="{{ route('two-factor.setup') }}"
                           class="text-sm text-indigo-600 hover:underline">
                            {{ auth()->user()->hasEnabledTwoFactor() ? 'Manage' : 'Enable' }}
                        </a>
                    </div>
                    <p class="mt-1 text-sm text-gray-500">
                        @if (auth()->user()->hasEnabledTwoFactor())
                            <span class="text-green-600">Enabled</span> — your account is protected with Google Authenticator (TOTP).
                        @else
                            Not enabled — add an extra layer of security to your account.
                        @endif
                    </p>
                </div>
            </div>

            <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                <div class="max-w-xl">
                    @include('profile.partials.delete-user-form')
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
