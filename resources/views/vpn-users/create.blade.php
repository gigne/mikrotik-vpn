<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">New VPN User</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
            <form method="POST" action="{{ route('vpn-users.store') }}" class="bg-white shadow-sm rounded-lg p-8 space-y-6">
                @csrf

                <div>
                    <label class="block font-medium text-sm text-gray-700">Username</label>
                    <input type="text" name="username" value="{{ old('username') }}" required
                           class="mt-1 block w-full border-gray-300 rounded-md text-sm" placeholder="mt-cabang-a">
                    @error('username') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block font-medium text-sm text-gray-700">Password</label>
                    <input type="text" name="password" value="{{ old('password') }}"
                           class="mt-1 block w-full border-gray-300 rounded-md text-sm font-mono" placeholder="auto-generate if empty">
                    @error('password') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    <p class="text-xs text-gray-400 mt-1">Leave empty to auto-generate. If filled: 8-64 chars. Written to /etc/ppp/chap-secrets.</p>
                </div>

                <div>
                    <label class="block font-medium text-sm text-gray-700">Assigned VPN IP (optional)</label>
                    <input type="text" name="assigned_ip" value="{{ old('assigned_ip') }}"
                           class="mt-1 block w-full border-gray-300 rounded-md text-sm font-mono" placeholder="192.168.42.x - auto allocated if empty">
                    @error('assigned_ip') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block font-medium text-sm text-gray-700">Notes</label>
                    <textarea name="notes" rows="3"
                              class="mt-1 block w-full border-gray-300 rounded-md text-sm">{{ old('notes') }}</textarea>
                    @error('notes') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block font-medium text-sm text-gray-700">Expires at (optional)</label>
                    <input type="datetime-local" name="expired_at" value="{{ old('expired_at') }}"
                           class="mt-1 block w-full border-gray-300 rounded-md text-sm">
                    @error('expired_at') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <label class="flex items-center gap-2">
                    <input type="checkbox" name="is_enabled" value="1" checked class="rounded">
                    <span class="text-sm text-gray-700">Enabled</span>
                </label>

                <div class="flex justify-end gap-2">
                    <a href="{{ route('vpn-users.index') }}" class="px-4 py-2 rounded-md text-sm text-gray-600">Cancel</a>
                    <button type="submit"
                            class="px-4 py-2 bg-indigo-600 rounded-md font-semibold text-sm text-white hover:bg-indigo-500">
                        Create
                    </button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
