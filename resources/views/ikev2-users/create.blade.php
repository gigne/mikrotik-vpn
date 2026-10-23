<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Create IKEv2 User</h2>
            <a href="{{ route('ikev2-users.index') }}" class="inline-flex items-center px-4 py-2 bg-gray-600 border border-transparent rounded-md font-semibold text-xs text-white tracking-widest hover:bg-gray-500">Back</a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow rounded-lg p-6">
                @if (session('error'))
                    <div class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded mb-4">{{ session('error') }}</div>
                @endif
                @if (session('success'))
                    <div class="bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded mb-4">{{ session('success') }}</div>
                @endif
                @if ($errors->any())
                    <div class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded mb-4">
                        <ul class="list-disc list-inside text-sm">@foreach ($errors->all() as $e) <li>{{ $e }}</li> @endforeach</ul>
                    </div>
                @endif
                <form method="POST" action="{{ route('ikev2-users.store') }}">
                    @csrf
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Username</label>
                            <input type="text" name="username" value="{{ old('username') }}" required autofocus
                                   class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <p class="mt-1 text-xs text-gray-400">Unique identifier for IKEv2 authentication.</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Password (opsional)</label>
                            <input type="password" name="password" maxlength="64"
                                   id="ikev2_password"
                                   class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <p class="mt-1 text-xs text-gray-400">Kosongkan untuk auto-generate random password (min 8 karakter jika diisi).</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">VPN IP (optional)</label>
                            <input type="text" name="assigned_ip" value="{{ old('assigned_ip') }}"
                                   class="mt-1 block w-full border-gray-300 rounded-md text-sm font-mono" placeholder="192.168.43.x - auto if empty">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Expiry Date</label>
                            <input type="date" name="expired_at" value="{{ old('expired_at') }}"
                                   class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Notes</label>
                            <textarea name="notes" rows="3" maxlength="1000"
                                      class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('notes') }}</textarea>
                        </div>
                        <div>
                            <label class="inline-flex items-center">
                                <input type="checkbox" name="is_enabled" value="1" checked class="form-checkbox text-indigo-600 rounded border-gray-300">
                                <span class="ml-2 text-sm text-gray-600">Enable immediately</span>
                            </label>
                        </div>
                    </div>
                    <div class="mt-6">
                        <button type="submit" class="w-full inline-flex justify-center py-2 px-4 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white tracking-widest hover:bg-indigo-500">
                            Create IKEv2 User + Generate Certificate
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>