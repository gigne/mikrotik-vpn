<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">IKEv2 User: {{ $ikev2User->username }}</h2>
            <div class="flex gap-2">
                <a href="{{ route('ikev2-users.index') }}" class="inline-flex items-center px-4 py-2 bg-gray-600 border border-transparent rounded-md font-semibold text-xs text-white tracking-widest hover:bg-gray-500">All Users</a>
                <a href="{{ route('ikev2-users.edit', $ikev2User) }}" class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white tracking-widest hover:bg-indigo-500">Edit</a>
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @if (session('success'))
                <div class="bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded">{{ session('success') }}</div>
            @endif

            <!-- Status -->
            <div class="bg-white shadow rounded-lg p-6">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Status</h3>
                <dl class="grid grid-cols-2 gap-4 text-sm">
                    <div><dt class="text-gray-500">Username</dt><dd class="font-medium">{{ $ikev2User->username }}</dd></div>
                    <div><dt class="text-gray-500">VPN IP</dt><dd class="font-mono">{{ $ikev2User->assigned_ip ?? 'Dynamic' }}</dd></div>
                    <div><dt class="text-gray-500">Status</dt><dd><span class="px-2 py-0.5 rounded text-xs font-medium {{ $ikev2User->isActive() ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600' }}">{{ $ikev2User->isActive() ? 'Active' : ($ikev2User->isExpired() ? 'Expired' : 'Disabled') }}</span></dd></div>
                    <div><dt class="text-gray-500">Expires</dt><dd>{{ $ikev2User->expired_at?->format('Y-m-d H:i') ?? 'Never' }}</dd></div>
                    <div><dt class="text-gray-500">Created</dt><dd>{{ $ikev2User->created_at?->format('Y-m-d H:i') }}</dd></div>
                    <div><dt class="text-gray-500">Last Used</dt><dd>{{ $ikev2User->last_used_at?->diffForHumans() ?? 'Never' }}</dd></div>
                </dl>
            </div>

            <!-- Certificate Info -->
            <div class="bg-white shadow rounded-lg p-6">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Certificate</h3>
                @if ($ikev2User->has_certificate)
                    <dl class="grid grid-cols-2 gap-4 text-sm">
                        <div><dt class="text-gray-500">Serial</dt><dd class="font-mono">{{ $ikev2User->certificate_serial }}</dd></div>
                    </dl>
                    <div class="mt-4 flex gap-2">
                        <a href="{{ route('ikev2-users.profile.download', $ikev2User) }}"
                           class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white hover:bg-blue-500">
                            Download iOS/macOS Profile (.mobileconfig)
                        </a>
                        <form method="POST" action="{{ route('ikev2-users.regenerate-certificate', $ikev2User) }}"
                              onsubmit="return confirm('Regenerate certificate? Old cert will be invalidated.');">
                            @csrf
                            <button class="inline-flex items-center px-4 py-2 bg-yellow-600 border border-transparent rounded-md font-semibold text-xs text-white hover:bg-yellow-500">
                                Regenerate Certificate
                            </button>
                        </form>
                    </div>
                @else
                    <p class="text-sm text-gray-400">No certificate generated yet.</p>
                @endif
            </div>

            <!-- Actions -->
            <div class="bg-white shadow rounded-lg p-6">
                <h3 class="text-lg font-medium text-red-600 mb-4">Danger Zone</h3>
                <form method="POST" action="{{ route('ikev2-users.destroy', $ikev2User) }}"
                      onsubmit="return confirm('Permanently delete {{ $ikev2User->username }}?');">
                    @csrf @method('DELETE')
                    <button class="inline-flex items-center px-4 py-2 bg-red-600 border border-transparent rounded-md font-semibold text-xs text-white hover:bg-red-500">
                        Delete User
                    </button>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>