<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Edit {{ $mikrotik->site_name }}</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
            <form method="POST" action="{{ route('mikrotiks.update', $mikrotik) }}" class="bg-white shadow-sm rounded-lg p-8 space-y-6">
                @csrf
                @method('PUT')

                <div>
                    <label class="block font-medium text-sm text-gray-700">Site Name</label>
                    <input type="text" name="site_name" value="{{ old('site_name', $mikrotik->site_name) }}" required
                           class="mt-1 block w-full border-gray-300 rounded-md text-sm">
                    @error('site_name') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block font-medium text-sm text-gray-700">Description</label>
                    <textarea name="description" rows="2"
                              class="mt-1 block w-full border-gray-300 rounded-md text-sm">{{ old('description', $mikrotik->description) }}</textarea>
                    @error('description') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block font-medium text-sm text-gray-700">VPN IP</label>
                        <input type="text" name="vpn_ip" value="{{ old('vpn_ip', $mikrotik->vpn_ip) }}"
                               class="mt-1 block w-full border-gray-300 rounded-md text-sm font-mono">
                        @error('vpn_ip') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block font-medium text-sm text-gray-700">VPN Password</label>
                        <input type="password" name="vpn_password" autocomplete="new-password"
                               class="mt-1 block w-full border-gray-300 rounded-md text-sm font-mono" placeholder="leave blank to keep current">
                        @error('vpn_password') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block font-medium text-sm text-gray-700">Winbox Internal Port</label>
                        <input type="number" name="winbox_internal_port" value="{{ old('winbox_internal_port', $mikrotik->winbox_internal_port) }}"
                               class="mt-1 block w-full border-gray-300 rounded-md text-sm">
                        @error('winbox_internal_port') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block font-medium text-sm text-gray-700">WebFig Internal Port</label>
                        <input type="number" name="webfig_internal_port" value="{{ old('webfig_internal_port', $mikrotik->webfig_internal_port) }}"
                               class="mt-1 block w-full border-gray-300 rounded-md text-sm">
                        @error('webfig_internal_port') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block font-medium text-sm text-gray-700">Winbox Public Port</label>
                        <input type="number" name="winbox_public_port" value="{{ old('winbox_public_port', $mikrotik->winbox_public_port) }}"
                               class="mt-1 block w-full border-gray-300 rounded-md text-sm">
                        @error('winbox_public_port') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block font-medium text-sm text-gray-700">WebFig Public Port</label>
                        <input type="number" name="webfig_public_port" value="{{ old('webfig_public_port', $mikrotik->webfig_public_port) }}"
                               class="mt-1 block w-full border-gray-300 rounded-md text-sm">
                        @error('webfig_public_port') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div>
                    <label class="block font-medium text-sm text-gray-700">RouterOS Version</label>
                    <input type="text" name="routeros_version" value="{{ old('routeros_version', $mikrotik->routeros_version) }}"
                           class="mt-1 block w-full border-gray-300 rounded-md text-sm">
                    @error('routeros_version') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block font-medium text-sm text-gray-700">SSH Public Key</label>
                    <textarea name="ssh_public_key" rows="2"
                              class="mt-1 block w-full border-gray-300 rounded-md text-sm font-mono">{{ old('ssh_public_key', $mikrotik->ssh_public_key) }}</textarea>
                    @error('ssh_public_key') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <div class="flex justify-end gap-2">
                    <a href="{{ route('mikrotiks.show', $mikrotik) }}" class="px-4 py-2 rounded-md text-sm text-gray-600">Cancel</a>
                    <button type="submit"
                            class="px-4 py-2 bg-indigo-600 rounded-md font-semibold text-sm text-white hover:bg-indigo-500">
                        Save
                    </button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
