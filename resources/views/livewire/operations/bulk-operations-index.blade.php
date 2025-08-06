<div>
    <x-breadcrumb :items="[
        ['name' => 'Operations'],
        ['name' => 'Bulk Operations']
    ]" />

    <!-- Header -->
    <div class="mb-8">
        <flux:heading size="xl">Bulk Operations</flux:heading>
        <flux:subheading>Efficiently manage multiple products and variants</flux:subheading>
    </div>

    <!-- This will redirect to overview tab -->
    <div class="text-center py-8">
        <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-purple-600 mx-auto"></div>
        <p class="mt-2 text-zinc-600 dark:text-zinc-400">Loading...</p>
    </div>
</div>