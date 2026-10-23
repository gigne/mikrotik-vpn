<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Edit VPN User</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
            <form method="POST" action="{{ route('vpn-users.update', $vpnUser) }}" class="bg-white shadow-sm rounded-lg p-8 space-y-6">
                @csrf
                @method('PUT')

                <div>
                    <label class="block font-medium text-sm text-gray-700">Username</label>
                    <input type="text" name="username" value="{{ old('username', $vpnUser->username) }}" required
                           class="mt-1 block w-full border-gray-300 rounded-md text-sm">
                    @error('username') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block font-medium text-sm text-gray-700">Password</label>
                    <input type="text" name="password" value="{{ old('password') }}"
                           class="mt-1 block w-full border-gray-300 rounded-md text-sm font-mono" placeholder="leave blank to keep">
                    @error('password') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block font-medium text-sm text-gray-700">Assigned VPN IP</label>
                    <input type="text" name="assigned_ip" value="{{ old('assigned_ip', $vpnUser->assigned_ip) }}"
                           class="mt-1 block w-full border-gray-300 rounded-md text-sm font-mono">
                    @error('assigned_ip') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block font-medium text-sm text-gray-700">Notes</label>
                    <textarea name="notes" rows="3"
                              class="mt-1 block w-full border-gray-300 rounded-md text-sm">{{ old('notes', $vpnUser->notes) }}</textarea>
                    @error('notes') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block font-medium text-sm text-gray-700">Expires at</label>
                    <input type="datetime-local" name="expired_at"
                           value="{{ old('expired_at', $vpnUser->expired_at?->format('Y-m-d\TH:i')) }}"
                           class="mt-1 block w-full border-gray-300 rounded-md text-sm">
                    @error('expired_at') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <label class="flex items-center gap-2">
                    <input type="checkbox" name="is_enabled" value="1" @checked(old('is_enabled', $vpnUser->is_enabled)) class="rounded">
                    <span class="text-sm text-gray-700">Enabled</span>
                </label>

                <div class="flex justify-end gap-2">
                    <a href="{{ route('vpn-users.index') }}" class="px-4 py-2 rounded-md text-sm text-gray-600">Cancel</a>
                    <button type="submit"
                            class="px-4 py-2 bg-indigo-600 rounded-md font-semibold text-sm text-white hover:bg-indigo-500">
                        Save
                    </button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
