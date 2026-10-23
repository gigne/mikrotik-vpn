<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">VPN Users</h2>
            <div class="flex gap-2">
                <a href="{{ route('vpn-users.export') }}"
                   class="inline-flex items-center px-4 py-2 bg-gray-600 border border-transparent rounded-md font-semibold text-xs text-white tracking-widest hover:bg-gray-500">
                    Export CSV
                </a>
                <a href="{{ route('vpn-users.create') }}"
                   class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white tracking-widest hover:bg-indigo-500">
                    New VPN User
                </a>
            </div>
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

            <!-- Filters -->
            <form method="GET" action="{{ route('vpn-users.index') }}" class="bg-white shadow-sm rounded-lg p-4 flex gap-2 items-end">
                <div class="flex-1">
                    <label class="block text-xs font-medium text-gray-500 mb-1">Search</label>
                    <input type="text" name="search" value="{{ request('search') }}" placeholder="username or IP"
                           class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md w-full text-sm">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">Status</label>
                    <select name="status" class="border-gray-300 rounded-md text-sm">
                        @foreach (['all', 'active', 'expired', 'disabled'] as $opt)
                            <option value="{{ $opt }}" @selected(request('status') === $opt)>{{ ucfirst($opt) }}</option>
                        @endforeach
                    </select>
                </div>
                <button class="px-4 py-2 bg-gray-800 rounded-md text-white text-sm">Filter</button>
            </form>

            <!-- Table -->
            <div class="bg-white shadow-sm rounded-lg overflow-hidden">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-left text-gray-500">
                        <tr>
                            <th class="px-6 py-3">Username</th>
                            <th class="px-6 py-3">VPN IP</th>
                            <th class="px-6 py-3">Status</th>
                            <th class="px-6 py-3">Expires</th>
                            <th class="px-6 py-3">Notes</th>
                            <th class="px-6 py-3">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($vpnUsers as $vpnUser)
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-3 font-medium text-gray-800">
                                    {{ $vpnUser->username }}
                                    @if ($vpnUser->trashed())
                                        <span class="text-xs text-red-500">(deleted)</span>
                                    @endif
                                </td>
                                <td class="px-6 py-3 font-mono text-gray-600">{{ $vpnUser->assigned_ip ?? '-' }}</td>
                                <td class="px-6 py-3">
                                    @if (! $vpnUser->is_enabled)
                                        <span class="px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-600">disabled</span>
                                    @elseif ($vpnUser->isExpired())
                                        <span class="px-2 py-0.5 rounded text-xs font-medium bg-yellow-100 text-yellow-800">expired</span>
                                    @else
                                        <span class="px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800">active</span>
                                    @endif
                                </td>
                                <td class="px-6 py-3 text-gray-500">{{ $vpnUser->expired_at?->format('Y-m-d') ?? '-' }}</td>
                                <td class="px-6 py-3 text-gray-500 max-w-xs truncate">{{ $vpnUser->notes ?? '' }}</td>
                                <td class="px-6 py-3 space-x-1 whitespace-nowrap">
                                    <a href="{{ route('vpn-users.edit', $vpnUser) }}" class="text-indigo-600 hover:underline text-xs">Edit</a>
                                    <form method="POST" action="{{ route('vpn-users.toggle', $vpnUser) }}" class="inline">
                                        @csrf
                                        <button class="text-gray-600 hover:underline text-xs">{{ $vpnUser->is_enabled ? 'Disable' : 'Enable' }}</button>
                                    </form>
                                    <form method="POST" action="{{ route('vpn-users.reset-password', $vpnUser) }}" class="inline">
                                        @csrf
                                        <button class="text-gray-600 hover:underline text-xs">Reset Password</button>
                                    </form>
                                    <form method="POST" action="{{ route('vpn-users.destroy', $vpnUser) }}"
                                          onsubmit="return confirm('Delete VPN user {{ $vpnUser->username }}?');">
                                        @csrf
                                        @method('DELETE')
                                        <button class="text-red-600 hover:underline text-xs">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-6 py-8 text-center text-gray-400">No VPN users found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
                <div class="px-6 py-3 border-t border-gray-100">
                    {{ $vpnUsers->links() }}
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
