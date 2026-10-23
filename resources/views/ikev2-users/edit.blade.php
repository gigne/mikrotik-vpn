<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Edit IKEv2 User: {{ $ikev2User->username }}</h2>
            <div class="flex gap-2">
                <a href="{{ route('ikev2-users.show', $ikev2User) }}" class="inline-flex items-center px-4 py-2 bg-gray-600 border border-transparent rounded-md font-semibold text-xs text-white tracking-widest hover:bg-gray-500">Back</a>
                <a href="{{ route('ikev2-users.index') }}" class="inline-flex items-center px-4 py-2 bg-gray-600 border border-transparent rounded-md font-semibold text-xs text-white tracking-widest hover:bg-gray-500">All</a>
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow rounded-lg p-6">
                @if ($errors->any())
                    <div class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded mb-4">
                        <ul class="list-disc list-inside text-sm">@foreach ($errors->all() as $e) <li>{{ $e }}</li> @endforeach</ul>
                    </div>
                @endif
                <form method="POST" action="{{ route('ikev2-users.update', $ikev2User) }}">
                    @csrf @method('PUT')
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Username</label>
                            <input type="text" name="username" value="{{ old('username', $ikev2User->username) }}" required
                                   class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">New Password (kosongkan untuk tetap pakai yang lama)</label>
                            <input type="password" name="password" minlength="8" maxlength="64"
                                   class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">VPN IP</label>
                            <input type="text" name="assigned_ip" value="{{ old('assigned_ip', $ikev2User->assigned_ip) }}"
                                   class="mt-1 block w-full border-gray-300 rounded-md text-sm font-mono">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Expiry Date</label>
                            <input type="date" name="expired_at" value="{{ old('expired_at', $ikev2User->expired_at?->format('Y-m-d')) }}"
                                   class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Notes</label>
                            <textarea name="notes" rows="3" maxlength="1000"
                                      class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('notes', $ikev2User->notes) }}</textarea>
                        </div>
                        <div>
                            <label class="inline-flex items-center">
                                <input type="checkbox" name="is_enabled" value="1" @checked(old('is_enabled', $ikev2User->is_enabled))
                                       class="form-checkbox text-indigo-600 rounded border-gray-300">
                                <span class="ml-2 text-sm text-gray-600">Enabled</span>
                            </label>
                        </div>
                    </div>
                    <div class="mt-6">
                        <button type="submit" class="w-full inline-flex justify-center py-2 px-4 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white tracking-widest hover:bg-indigo-500">
                            Update IKEv2 User
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>