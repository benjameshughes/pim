<x-layouts.app.sidebar>
    <x-page-template 
        title="Role Management"
        :breadcrumbs="[
            ['name' => 'Dashboard', 'url' => route('dashboard')],
            ['name' => 'Role Management']
        ]"
        :actions="[
            [
                'type' => 'link',
                'label' => 'User Management',
                'href' => route('admin.users.index'),
                'variant' => 'outline',
                'icon' => 'users'
            ]
        ]"
    >
        <x-slot:subtitle>
            Manage user roles, permissions, and access control
        </x-slot:subtitle>

        <x-slot:stats>
            <x-stats-grid>
                <x-stats-card 
                    title="System Roles"
                    value="3"
                    icon="shield-check"
                />
                <x-stats-card 
                    title="Custom Roles"
                    value="0"
                    icon="key"
                    color="purple"
                />
                <x-stats-card 
                    title="Permissions"
                    value="12"
                    icon="lock-closed"
                    color="blue"
                />
            </x-stats-grid>
        </x-slot:stats>

        <div class="text-center py-16">
            <div class="mx-auto h-16 w-16 text-zinc-400 mb-4">
                <flux:icon name="shield-check" class="h-16 w-16" />
            </div>
            <h3 class="text-xl font-semibold text-zinc-900 dark:text-zinc-100 mb-2">
                Role Management Coming Soon
            </h3>
            <p class="text-zinc-600 dark:text-zinc-400 mb-6 max-w-md mx-auto">
                Advanced role-based access control including custom roles, granular permissions, and team management features are in development.
            </p>
            <div class="bg-zinc-50 dark:bg-zinc-900 rounded-lg p-6 max-w-lg mx-auto">
                <h4 class="font-semibold text-zinc-900 dark:text-zinc-100 mb-3">Current System Roles:</h4>
                <div class="space-y-2 text-left">
                    <div class="flex items-center gap-3">
                        <flux:badge variant="primary">Administrator</flux:badge>
                        <span class="text-sm text-zinc-600 dark:text-zinc-400">Full system access</span>
                    </div>
                    <div class="flex items-center gap-3">
                        <flux:badge variant="outline">Manager</flux:badge>
                        <span class="text-sm text-zinc-600 dark:text-zinc-400">Product and data management</span>
                    </div>
                    <div class="flex items-center gap-3">
                        <flux:badge variant="neutral">User</flux:badge>
                        <span class="text-sm text-zinc-600 dark:text-zinc-400">Basic access</span>
                    </div>
                </div>
            </div>
        </div>
    </x-page-template>
</x-layouts.app.sidebar>