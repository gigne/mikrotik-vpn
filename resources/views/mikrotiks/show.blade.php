<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ $mikrotik->site_name }}</h2>
            <div class="flex gap-2">
                <a href="{{ route('mikrotiks.script.download', $mikrotik) }}"
                   class="inline-flex items-center px-4 py-2 bg-gray-600 border border-transparent rounded-md font-semibold text-xs text-white tracking-widest hover:bg-gray-500">
                    Download .rsc
                </a>
                <a href="{{ route('mikrotiks.edit', $mikrotik) }}"
                   class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white tracking-widest hover:bg-indigo-500">
                    Edit
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @if (session('success'))
                <div class="bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded">{{ session('success') }}</div>
            @endif
            @if (session('error'))
                <div class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded">{{ session('error') }}</div>
            @endif

            <!-- Details -->
            <div class="bg-white shadow-sm rounded-lg p-6">
                <div class="grid grid-cols-2 md:grid-cols-4 gap-6 text-sm">
                    <div>
                        <div class="text-gray-500">VPN IP</div>
                        <div class="font-mono text-gray-900">{{ $mikrotik->vpn_ip }}</div>
                    </div>
                    <div>
                        <div class="text-gray-500">VPN Username</div>
                        <div class="font-mono text-gray-900">{{ $mikrotik->vpn_username }}</div>
                    </div>
                    <div>
                        <div class="text-gray-500">Status</div>
                        <div class="{{ $mikrotik->is_online ? 'text-green-600' : 'text-red-600' }} font-medium">
                            {{ $mikrotik->is_online ? 'online' : 'offline' }}
                        </div>
                    </div>
                    <div>
                        <div class="text-gray-500">Last Seen</div>
                        <div class="text-gray-900">{{ $mikrotik->last_seen_at?->diffForHumans() ?? '-' }}</div>
                    </div>
                    <div>
                        <div class="text-gray-500">Winbox</div>
                        <div class="font-mono text-indigo-600">{{ $mikrotik->winbox_url }}</div>
                    </div>
                    <div>
                        <div class="text-gray-500">WebFig</div>
                        <div class="font-mono text-indigo-600">{{ $mikrotik->webfig_url }}</div>
                    </div>
                    <div>
                        <div class="text-gray-500">RouterOS Version</div>
                        <div class="text-gray-900">{{ $mikrotik->routeros_version ?? '-' }}</div>
                    </div>
                    <div>
                        <div class="text-gray-500">Description</div>
                        <div class="text-gray-900">{{ $mikrotik->description ?? '-' }}</div>
                    </div>
                </div>
            </div>

            <!-- Credentials + script -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <div class="bg-white shadow-sm rounded-lg p-6">
                    <div class="font-semibold text-gray-800 mb-4">Connection Summary</div>
                    <dl class="space-y-2 text-sm">
                        <div><dt class="text-gray-500 inline">Server: </dt><dd class="font-mono inline">{{ $summary['vpn_server'] }}</dd></div>
                        <div><dt class="text-gray-500 inline">Username: </dt><dd class="font-mono inline">{{ $summary['username'] }}</dd></div>
                        <div><dt class="text-gray-500 inline">Password: </dt><dd class="font-mono inline">{{ $summary['password'] }}</dd></div>
                        <div><dt class="text-gray-500 inline">VPN IP: </dt><dd class="font-mono inline">{{ $summary['vpn_ip'] }}</dd></div>
                        <div><dt class="text-gray-500 inline">PSK: </dt><dd class="font-mono inline">{{ $summary['psk'] }}</dd></div>
                    </dl>
                </div>

                <div class="lg:col-span-2 bg-white shadow-sm rounded-lg p-6">
                    <div class="flex justify-between items-center mb-4">
                        <div class="font-semibold text-gray-800">RouterOS Script</div>
                        <button type="button" onclick="navigator.clipboard.writeText(document.getElementById('rsc').textContent).then(() => this.textContent = 'Copied!')"
                                class="px-3 py-1.5 bg-gray-800 text-white rounded text-xs">Copy</button>
                    </div>
                    <pre id="rsc" class="bg-gray-900 text-green-300 text-xs p-4 rounded overflow-x-auto"><code>{{ $script }}</code></pre>
                </div>
            </div>

            <!-- Backups -->
            <div class="bg-white shadow-sm rounded-lg overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-100 font-semibold text-gray-800">Backups</div>
                @if (count($backups) > 0)
                    <ul class="divide-y divide-gray-100 text-sm">
                        @foreach ($backups as $file)
                            <li class="px-6 py-2 font-mono text-gray-600">{{ $file }}</li>
                        @endforeach
                    </ul>
                @else
                    <div class="px-6 py-8 text-center text-gray-400 text-sm">No backups yet.</div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
