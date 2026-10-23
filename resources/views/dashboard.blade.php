<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Dashboard') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @if (session('success'))
                <div class="bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded">
                    {{ session('success') }}
                </div>
            @endif
            @if (session('error'))
                <div class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded">
                    {{ session('error') }}
                </div>
            @endif

            <!-- Server status cards -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div class="bg-white shadow-sm rounded-lg p-6">
                    <div class="text-sm font-medium text-gray-500">VPN Server</div>
                    <div class="mt-1 text-2xl font-bold {{ $serverStatus['overall'] === 'healthy' ? 'text-green-600' : 'text-red-600' }}">
                        {{ ucfirst($serverStatus['overall']) }}
                    </div>
                </div>
                <div class="bg-white shadow-sm rounded-lg p-6">
                    <div class="text-sm font-medium text-gray-500">xl2tpd</div>
                    <div class="mt-1 text-2xl font-bold {{ ($serverStatus['services']['xl2tpd']['status'] ?? '') === 'active' ? 'text-green-600' : 'text-red-600' }}">
                        {{ $serverStatus['services']['xl2tpd']['status'] ?? 'unknown' }}
                    </div>
                </div>
                <div class="bg-white shadow-sm rounded-lg p-6">
                    <div class="text-sm font-medium text-gray-500">IPsec</div>
                    <div class="mt-1 text-2xl font-bold {{ ($serverStatus['services']['ipsec']['status'] ?? '') === 'active' ? 'text-green-600' : 'text-red-600' }}">
                        {{ $serverStatus['services']['ipsec']['status'] ?? 'unknown' }}
                    </div>
                </div>
                <div class="bg-white shadow-sm rounded-lg p-6">
                    <div class="text-sm font-medium text-gray-500">Driver</div>
                    <div class="mt-1 text-2xl font-bold text-gray-800">{{ config('vpnpanel.driver') }}</div>
                    <div class="text-xs text-gray-400">local / sandbox</div>
                </div>
            </div>

            <!-- Counts -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div class="bg-white shadow-sm rounded-lg p-6">
                    <div class="text-sm font-medium text-gray-500">Total MikroTik</div>
                    <div class="mt-1 text-3xl font-bold text-gray-900">{{ $stats['mikrotiks']['total'] }}</div>
                </div>
                <div class="bg-white shadow-sm rounded-lg p-6">
                    <div class="text-sm font-medium text-gray-500">MikroTik Online</div>
                    <div class="mt-1 text-3xl font-bold text-green-600">{{ $stats['mikrotiks']['online'] }}</div>
                </div>
                <div class="bg-white shadow-sm rounded-lg p-6">
                    <div class="text-sm font-medium text-gray-500">MikroTik Offline</div>
                    <div class="mt-1 text-3xl font-bold text-red-600">{{ $stats['mikrotiks']['offline'] }}</div>
                </div>
                <div class="bg-white shadow-sm rounded-lg p-6">
                    <div class="text-sm font-medium text-gray-500">Total VPN Users</div>
                    <div class="mt-1 text-3xl font-bold text-gray-900">{{ $stats['vpn_users']['total'] }}</div>
                    <div class="text-xs text-gray-400">{{ $stats['vpn_users']['active'] }} active</div>
                </div>
            </div>

            <!-- Charts (Chart.js via CDN) -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                <div class="bg-white shadow-sm rounded-lg p-6">
                    <div class="font-semibold text-gray-800 mb-4">MikroTik Online vs Offline</div>
                    <canvas id="mikrotikChart" height="220"></canvas>
                </div>
                <div class="bg-white shadow-sm rounded-lg p-6">
                    <div class="font-semibold text-gray-800 mb-4">IP Pool Usage</div>
                    <div class="w-full bg-gray-200 rounded-full h-4 mb-2">
                        <div class="bg-indigo-600 h-4 rounded-full" style="width: {{ $ipStats['usage_percent'] }}%"></div>
                    </div>
                    <div class="text-sm text-gray-600">
                        {{ $ipStats['used'] }} used / {{ $ipStats['total'] }} total ({{ $ipStats['usage_percent'] }}%)
                    </div>
                    <div class="mt-6 font-semibold text-gray-800 mb-2">Port Usage</div>
                    <div class="text-sm text-gray-600">
                        Winbox: {{ $portStats['winbox']['used'] }}/{{ $portStats['winbox']['total'] }} ·
                        WebFig: {{ $portStats['webfig']['used'] }}/{{ $portStats['webfig']['total'] }}
                    </div>
                </div>
            </div>

            <!-- Sites table -->
            <div class="bg-white shadow-sm rounded-lg overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-100 font-semibold text-gray-800">Sites</div>
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-left text-gray-500">
                        <tr>
                            <th class="px-6 py-3">Site</th>
                            <th class="px-6 py-3">VPN IP</th>
                            <th class="px-6 py-3">Status</th>
                            <th class="px-6 py-3">Last Seen</th>
                            <th class="px-6 py-3">Winbox</th>
                            <th class="px-6 py-3">WebFig</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($sites as $site)
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-3 font-medium text-gray-800">{{ $site->site_name }}</td>
                                <td class="px-6 py-3 font-mono text-gray-600">{{ $site->vpn_ip }}</td>
                                <td class="px-6 py-3">
                                    @if ($site->is_online)
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800">online</span>
                                    @else
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-800">offline</span>
                                    @endif
                                </td>
                                <td class="px-6 py-3 text-gray-500">{{ $site->last_seen_at?->diffForHumans() ?? '-' }}</td>
                                <td class="px-6 py-3 font-mono text-indigo-600">{{ $site->winbox_public_port ? config('vpnpanel.domain') . ':' . $site->winbox_public_port : '-' }}</td>
                                <td class="px-6 py-3 font-mono text-indigo-600">{{ $site->webfig_public_port ? config('vpnpanel.domain') . ':' . $site->webfig_public_port : '-' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-6 py-8 text-center text-gray-400">
                                    No sites yet. <a class="text-indigo-600 underline" href="{{ route('mikrotiks.create') }}">Add your first MikroTik</a>.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <!-- Recent activity -->
            <div class="bg-white shadow-sm rounded-lg overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-100 font-semibold text-gray-800">Recent Activity</div>
                <ul class="divide-y divide-gray-100">
                    @forelse ($recentActivity as $log)
                        <li class="px-6 py-3 text-sm">
                            <span class="font-mono text-xs bg-gray-100 rounded px-1.5 py-0.5">{{ $log->event }}</span>
                            <span class="ms-2 text-gray-600">{{ $log->user?->name ?? 'system' }}</span>
                            <span class="float-right text-gray-400 text-xs">{{ $log->created_at->diffForHumans() }}</span>
                        </li>
                    @empty
                        <li class="px-6 py-8 text-center text-gray-400 text-sm">No activity yet.</li>
                    @endforelse
                </ul>
            </div>
        </div>
    </div>

    @once
        @push('scripts')
            <script src="https://cdn.jsdelivr.net/npm/chart.js@4"></script>
            <script>
                window.addEventListener('DOMContentLoaded', () => {
                    const el = document.getElementById('mikrotikChart');
                    if (! el) return;
                    new Chart(el.getContext('2d'), {
                        type: 'bar',
                        data: {
                            labels: ['Online', 'Offline'],
                            datasets: [{
                                label: 'MikroTik',
                                data: [{{ $stats['mikrotiks']['online'] }}, {{ $stats['mikrotiks']['offline'] }}],
                                backgroundColor: ['#16a34a', '#dc2626'],
                            }],
                        },
                        options: {
                            responsive: true,
                            scales: { y: { beginAtZero: true, ticks: { precision: 0 } } },
                        },
                    });
                });
            </script>
        @endpush
    @endonce
</x-app-layout>
