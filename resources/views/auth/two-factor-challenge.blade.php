<x-guest-layout>
    <form method="POST" action="{{ route('two-factor.verify') }}">
        @csrf

        <div>
            <x-input-label for="code" :value="__('Authentication code (TOTP or recovery code)')" />
            <x-text-input id="code" class="block mt-1 w-full font-mono" type="text" name="code" required autofocus />
            <x-input-error :messages="$errors->get('code')" class="mt-2" />
        </div>

        <div class="mt-6 flex items-center justify-between">
            <x-primary-button>
                {{ __('Verify') }}
            </x-primary-button>

            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="underline text-sm text-gray-600 hover:text-gray-900">
                    {{ __('Log out') }}
                </button>
            </form>
        </div>
    </form>
</x-guest-layout>