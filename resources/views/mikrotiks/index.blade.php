<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">MikroTik Branches</h2>
            <a href="{{ route('mikrotiks.create') }}"
               class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white tracking-widest hover:bg-indigo-500">
                Add MikroTik
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
            @if (session('success'))
                <div class="bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded">{{ session('success') }}</div>
            @endif
            @if ($errors->any())
                <div class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded">
                    <ul class="list-disc list-inside text-sm">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <!-- Search -->
            <form method="GET" action="{{ route('mikrotiks.index') }}" class="bg-white shadow-sm rounded-lg p-4 flex gap-2 items-end">
                <div class="flex-1">
                    <label class="block text-xs font-medium text-gray-500 mb-1">Search</label>
                    <input type="text" name="search" value="{{ request('search') }}" placeholder="site, username, or IP"
                           class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md w-full text-sm">
                </div>
                <button class="px-4 py-2 bg-gray-800 rounded-md text-white text-sm">Search</button>
            </form>

            <!-- Table -->
            <div class="bg-white shadow-sm rounded-lg overflow-hidden">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-left text-gray-500">
                        <tr>
                            <th class="px-6 py-3">Site</th>
                            <th class="px-6 py-3">VPN IP</th>
                            <th class="px-6 py-3">Username</th>
                            <th class="px-6 py-3">Status</th>
                            <th class="px-6 py-3">Last Seen</th>
                            <th class="px-6 py-3">Winbox</th>
                            <th class="px-6 py-3">WebFig</th>
                            <th class="px-6 py-3">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($mikrotiks as $mikrotik)
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-3 font-medium text-gray-800">{{ $mikrotik->site_name }}</td>
                                <td class="px-6 py-3 font-mono text-gray-600">{{ $mikrotik->vpn_ip }}</td>
                                <td class="px-6 py-3 font-mono text-gray-600">{{ $mikrotik->vpn_username }}</td>
                                <td class="px-6 py-3">
                                    @if ($mikrotik->is_online)
                                        <span class="px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800">online</span>
                                    @else
                                        <span class="px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-800">offline</span>
                                    @endif
                                </td>
                                <td class="px-6 py-3 text-gray-500 text-xs">{{ $mikrotik->last_seen_at?->diffForHumans() ?? '-' }}</td>
                                <td class="px-6 py-3 font-mono text-indigo-600">{{ $mikrotik->winbox_url }}</td>
                                <td class="px-6 py-3 font-mono text-indigo-600">{{ $mikrotik->webfig_url }}</td>
                                <td class="px-6 py-3 space-x-1 whitespace-nowrap">
                                    <a href="{{ route('mikrotiks.show', $mikrotik) }}" class="text-indigo-600 hover:underline text-xs">Show</a>
                                    <a href="{{ route('mikrotiks.edit', $mikrotik) }}" class="text-gray-600 hover:underline text-xs">Edit</a>
                                    <form method="POST" action="{{ route('mikrotiks.check', $mikrotik) }}" class="inline">
                                        @csrf
                                        <button class="text-gray-600 hover:underline text-xs">Check</button>
                                    </form>
                                    <form method="POST" action="{{ route('mikrotiks.backup', $mikrotik) }}" class="inline">
                                        @csrf
                                        <button class="text-gray-600 hover:underline text-xs">Backup</button>
                                    </form>
                                    <form method="POST" action="{{ route('mikrotiks.destroy', $mikrotik) }}"
                                          onsubmit="return confirm('Decommission MikroTik {{ $mikrotik->site_name }}?');">
                                        @csrf
                                        @method('DELETE')
                                        <button class="text-red-600 hover:underline text-xs">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-6 py-8 text-center text-gray-400">No MikroTik devices yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
                <div class="px-6 py-3 border-t border-gray-100">
                    {{ $mikrotiks->links() }}
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
