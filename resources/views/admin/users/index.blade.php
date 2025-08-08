<x-layouts.app.sidebar>
    <x-page-template 
        title="User Management"
        :breadcrumbs="[
            ['name' => 'Dashboard', 'url' => route('dashboard')],
            ['name' => 'User Management']
        ]"
        :actions="[
            [
                'type' => 'link',
                'label' => 'Role Management',
                'href' => route('admin.roles.index'),
                'variant' => 'outline',
                'icon' => 'shield-check'
            ]
        ]"
    >
        <x-slot:subtitle>
            Manage user accounts, permissions, and access levels
        </x-slot:subtitle>

        <x-slot:stats>
            <x-stats-grid>
                <x-stats-card 
                    title="Total Users"
                    :value="App\Models\User::count()"
                    icon="users"
                />
                <x-stats-card 
                    title="Active Users"
                    :value="App\Models\User::whereNotNull('email_verified_at')->count()"
                    icon="check-circle"
                    color="green"
                />
                <x-stats-card 
                    title="Pending Verification"
                    :value="App\Models\User::whereNull('email_verified_at')->count()"
                    icon="exclamation-circle"
                    color="yellow"
                />
            </x-stats-grid>
        </x-slot:stats>

        <div class="text-center py-16">
            <div class="mx-auto h-16 w-16 text-zinc-400 mb-4">
                <flux:icon name="users" class="h-16 w-16" />
            </div>
            <h3 class="text-xl font-semibold text-zinc-900 dark:text-zinc-100 mb-2">
                User Management Coming Soon
            </h3>
            <p class="text-zinc-600 dark:text-zinc-400 mb-6 max-w-md mx-auto">
                Advanced user management features including role-based permissions, user invitations, and activity monitoring are in development.
            </p>
            <div class="flex items-center justify-center gap-4">
                <flux:button 
                    href="{{ route('settings.profile') }}"
                    variant="primary"
                    icon="user-circle"
                    wire:navigate
                >
                    Manage Your Profile
                </flux:button>
                <flux:button 
                    href="{{ route('admin.roles.index') }}"
                    variant="outline"
                    icon="shield-check"
                    wire:navigate
                >
                    Role Management
                </flux:button>
            </div>
        </div>
    </x-page-template>
</x-layouts.app.sidebar>