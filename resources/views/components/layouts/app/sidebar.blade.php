<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-flux-appearance>
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white dark:bg-zinc-800">
        <flux:sidebar sticky stashable class="border-e border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:sidebar.toggle class="lg:hidden" icon="x" />

            <a href="{{ route('dashboard') }}" class="me-5 flex items-center space-x-2 rtl:space-x-reverse" wire:navigate>
                <x-app-logo />
            </a>

            {{-- Clean FluxUI Navigation - Builder + Actions Pattern Architecture --}}
            <flux:navlist variant="outline">
                {{-- Dashboard --}}
                <flux:navlist.item 
                    icon="squares-2x2" 
                    href="{{ route('dashboard') }}" 
                    current="{{ request()->routeIs('dashboard') }}" 
                    wire:navigate
                >
                    Dashboard
                </flux:navlist.item>

                {{-- Product Management Group --}}
                <flux:navlist.group heading="Product Management" class="grid">
                    <flux:navlist.item 
                        icon="cube" 
                        href="{{ route('products.index') }}" 
                        current="{{ request()->routeIs('products.*') }}" 
                        wire:navigate
                    >
                        Products
                    </flux:navlist.item>
                    
                    <flux:navlist.item 
                        icon="tag" 
                        href="{{ route('products.variants.index') }}" 
                        current="{{ request()->routeIs('products.variants.*') }}" 
                        wire:navigate
                    >
                        Variants
                    </flux:navlist.item>
                    
                    <flux:navlist.item 
                        icon="sparkles" 
                        href="{{ route('products.wizard') }}" 
                        current="{{ request()->routeIs('products.wizard') }}" 
                        wire:navigate
                    >
                        Product Wizard
                    </flux:navlist.item>
                </flux:navlist.group>

                {{-- Data Management Group --}}
                <flux:navlist.group heading="Data Management" class="grid">
                    <flux:navlist.item 
                        icon="qr-code" 
                        href="{{ route('barcodes.index') }}" 
                        current="{{ request()->routeIs('barcodes.*') }}" 
                        wire:navigate
                    >
                        Barcodes
                    </flux:navlist.item>
                    
                    <flux:navlist.item 
                        icon="currency-pound" 
                        href="{{ route('pricing.index') }}" 
                        current="{{ request()->routeIs('pricing.*') }}" 
                        wire:navigate
                    >
                        Pricing
                    </flux:navlist.item>
                    
                    <flux:navlist.item 
                        icon="photo" 
                        href="{{ route('images.index') }}" 
                        current="{{ request()->routeIs('images.*') }}" 
                        wire:navigate
                    >
                        Images
                    </flux:navlist.item>
                </flux:navlist.group>

                {{-- Data Exchange Group --}}
                <flux:navlist.group heading="Data Exchange" class="grid">
                    <flux:navlist.item 
                        icon="arrow-down-tray" 
                        href="{{ route('import.index') }}" 
                        current="{{ request()->routeIs('import.*') }}" 
                        wire:navigate
                    >
                        Import Data
                    </flux:navlist.item>
                    
                    <flux:navlist.item 
                        icon="arrow-up-tray" 
                        href="{{ route('export') }}" 
                        current="{{ request()->routeIs('export*') }}" 
                        wire:navigate
                    >
                        Export Data
                    </flux:navlist.item>
                </flux:navlist.group>

                {{-- Marketplace Sync Group --}}
                <flux:navlist.group heading="Marketplace Sync" class="grid">
                    <flux:navlist.item 
                        icon="shopping-bag" 
                        href="{{ route('sync.shopify') }}" 
                        current="{{ request()->routeIs('sync.shopify') }}" 
                        wire:navigate
                    >
                        Shopify
                    </flux:navlist.item>
                    
                    <flux:navlist.item 
                        icon="building-storefront" 
                        href="{{ route('sync.ebay') }}" 
                        current="{{ request()->routeIs('sync.ebay') }}" 
                        wire:navigate
                    >
                        eBay
                    </flux:navlist.item>
                    
                    <flux:navlist.item 
                        icon="globe-alt" 
                        href="{{ route('sync.mirakl') }}" 
                        current="{{ request()->routeIs('sync.mirakl') }}" 
                        wire:navigate
                    >
                        Mirakl
                    </flux:navlist.item>
                </flux:navlist.group>

                {{-- Operations Group --}}
                <flux:navlist.group heading="Operations" class="grid">
                    <flux:navlist.item 
                        icon="wrench-screwdriver" 
                        href="{{ route('operations.bulk') }}" 
                        current="{{ request()->routeIs('operations.*') }}" 
                        wire:navigate
                    >
                        Bulk Operations
                    </flux:navlist.item>
                    
                    <flux:navlist.item 
                        icon="archive-box" 
                        href="{{ route('archive') }}" 
                        current="{{ request()->routeIs('archive') }}" 
                        wire:navigate
                    >
                        Archive
                    </flux:navlist.item>
                </flux:navlist.group>

                {{-- Configuration Group --}}
                <flux:navlist.group heading="Configuration" class="grid">
                    <flux:navlist.item 
                        icon="adjustments-horizontal" 
                        href="{{ route('attributes.definitions') }}" 
                        current="{{ request()->routeIs('attributes.*') }}" 
                        wire:navigate
                    >
                        Attributes
                    </flux:navlist.item>
                    
                    <flux:navlist.item 
                        icon="users" 
                        href="{{ route('admin.users.index') }}" 
                        current="{{ request()->routeIs('admin.users.*') }}" 
                        wire:navigate
                    >
                        User Management
                    </flux:navlist.item>
                </flux:navlist.group>

                {{-- Examples Group (Development) --}}
                @if(app()->environment('local'))
                <flux:navlist.group heading="Examples" class="grid">
                    <flux:navlist.item 
                        icon="beaker" 
                        href="{{ route('examples.toast.demo') }}" 
                        current="{{ request()->routeIs('examples.toast.demo') }}" 
                        wire:navigate
                    >
                        Toast Demo
                    </flux:navlist.item>
                    
                    <flux:navlist.item 
                        icon="adjustments-horizontal" 
                        href="{{ route('examples.attributes.demo') }}" 
                        current="{{ request()->routeIs('examples.attributes.demo') }}" 
                        wire:navigate
                    >
                        Attributes Demo
                    </flux:navlist.item>
                </flux:navlist.group>
                @endif
            </flux:navlist>

            <flux:spacer />

            <flux:navlist variant="outline">
                <flux:navlist.item icon="folder-git-2" href="https://github.com/laravel/livewire-starter-kit" target="_blank">
                {{ __('Repository') }}
                </flux:navlist.item>

                <flux:navlist.item icon="book-open-text" href="https://laravel.com/docs/starter-kits#livewire" target="_blank">
                {{ __('Documentation') }}
                </flux:navlist.item>
            </flux:navlist>

            <!-- Desktop User Menu -->
            <flux:dropdown class="hidden lg:block" position="bottom" align="start">
                <flux:profile
                    name="{{ auth()->user()->name }}"
                    initials="{{ auth()->user()->initials() }}"
                    icon-trailing="chevrons-up-down"
                />

                <flux:menu class="w-[220px]">
                    <flux:menu.radio.group>
                        <div class="p-0 text-sm font-normal">
                            <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
                                <span class="relative flex h-8 w-8 shrink-0 overflow-hidden rounded-lg">
                                    <span
                                        class="flex h-full w-full items-center justify-center rounded-lg bg-neutral-200 text-black dark:bg-neutral-700 dark:text-white"
                                    >
                                        {{ auth()->user()->initials() }}
                                    </span>
                                </span>

                                <div class="grid flex-1 text-start text-sm leading-tight">
                                    <span class="truncate font-semibold">{{ auth()->user()->name }}</span>
                                    <span class="truncate text-xs">{{ auth()->user()->email }}</span>
                                </div>
                            </div>
                        </div>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <flux:menu.radio.group>
                        <flux:menu.item href="{{ route('settings.profile') }}" icon="cog" wire:navigate>{{ __('Settings') }}</flux:menu.item>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <form method="POST" action="{{ route('logout') }}" class="w-full">
                        @csrf
                        <flux:menu.item as="button" type="submit" icon="arrow-right-start-on-rectangle" class="w-full">
                            {{ __('Log Out') }}
                        </flux:menu.item>
                    </form>
                </flux:menu>
            </flux:dropdown>
        </flux:sidebar>

        <!-- Mobile User Menu -->
        <flux:header class="lg:hidden">
            <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />

            <flux:spacer />

            <flux:dropdown position="top" align="end">
                <flux:profile
                    initials="{{ auth()->user()->initials() }}"
                    icon-trailing="chevron-down"
                />

                <flux:menu>
                    <flux:menu.radio.group>
                        <div class="p-0 text-sm font-normal">
                            <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
                                <span class="relative flex h-8 w-8 shrink-0 overflow-hidden rounded-lg">
                                    <span
                                        class="flex h-full w-full items-center justify-center rounded-lg bg-neutral-200 text-black dark:bg-neutral-700 dark:text-white"
                                    >
                                        {{ auth()->user()->initials() }}
                                    </span>
                                </span>

                                <div class="grid flex-1 text-start text-sm leading-tight">
                                    <span class="truncate font-semibold">{{ auth()->user()->name }}</span>
                                    <span class="truncate text-xs">{{ auth()->user()->email }}</span>
                                </div>
                            </div>
                        </div>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <flux:menu.radio.group>
                        <flux:menu.item href="{{ route('settings.profile') }}" icon="cog" wire:navigate>{{ __('Settings') }}</flux:menu.item>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <form method="POST" action="{{ route('logout') }}" class="w-full">
                        @csrf
                        <flux:menu.item as="button" type="submit" icon="arrow-right-start-on-rectangle" class="w-full">
                            {{ __('Log Out') }}
                        </flux:menu.item>
                    </form>
                </flux:menu>
            </flux:dropdown>
        </flux:header>

        {{ $slot }}

        @fluxScripts
        @livewireScripts
        
        {{-- Component Scripts Stack --}}
        @stack('scripts')
        
        {{-- Alpine Toast Integration (ResourceManager Pattern) - Temporarily disabled for debugging --}}
        {{-- @include('partials.alpine-toast-integration') --}}
    </body>
</html>