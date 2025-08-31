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

            {{-- ✨ PHOENIX NAVIGATION - ORGANIZED & INTUITIVE ✨ --}}
            <flux:navlist variant="outline">
                {{-- Dashboard --}}
                @can('view-dashboard')
                    <flux:navlist.item 
                        icon="squares-2x2" 
                        href="{{ route('dashboard') }}"
                        wire:navigate
                    >
                        Dashboard
                    </flux:navlist.item>
                @endcan

                {{-- 📦 PRODUCT MANAGEMENT --}}
                @if(auth()->user()->can('view-products') || auth()->user()->can('view-barcodes') || auth()->user()->can('view-pricing') || auth()->user()->can('import-products'))
                    <flux:navlist.group expandable heading="Products">
                        @can('view-products')
                            <flux:navlist.item
                                :current="request()->is('products')"
                                icon="cube" 
                                href="{{ route('products.index') }}"
                            >
                                Products & Variants
                            </flux:navlist.item>
                        @endcan

                        @can('view-barcodes')
                            <flux:navlist.item 
                                icon="bars-2" 
                                href="{{ route('barcodes.index') }}"
                            >
                                Barcodes
                            </flux:navlist.item>
                        @endcan

                        @can('view-pricing')
                            <flux:navlist.item 
                                icon="currency-dollar" 
                                href="{{ route('pricing.dashboard') }}" 
                            >
                                Pricing
                            </flux:navlist.item>
                        @endcan

                        @can('import-products')
                            <flux:navlist.item 
                                icon="arrow-up-tray" 
                                href="{{ route('import.products') }}"
                            >
                                Import
                            </flux:navlist.item>
                        @endcan
                    </flux:navlist.group>
                @endif

                {{-- 🖼️ MEDIA MANAGEMENT --}}
                @can('view-images')
                    <flux:navlist.group expandable heading="Media">
                        <flux:navlist.item 
                            icon="image" 
                            href="{{ route('images.index') }}"
                        >
                            Images
                        </flux:navlist.item>
                    </flux:navlist.group>
                @endcan

                {{-- 🛍️ SALES CHANNELS --}}
                @if(auth()->user()->can('sync-to-marketplace') || auth()->user()->can('manage-marketplace-connections'))
                    <flux:navlist.group expandable heading="Sales Channels">
                        @can('sync-to-marketplace')
                            <flux:navlist.item 
                                icon="cloud-arrow-up" 
                                href="{{ route('shopify.sync') }}" 
                            >
                                Shopify
                            </flux:navlist.item>
                        @endcan

                        @can('manage-marketplace-connections')
                            <flux:navlist.item 
                                icon="tag" 
                                href="{{ route('marketplace.identifiers') }}" 
                            >
                                Marketplace
                            </flux:navlist.item>
                        @endcan
                    </flux:navlist.group>
                @endif

                {{-- ⚡ OPERATIONS --}}
                @if(auth()->user()->can('bulk-operations') || auth()->user()->can('view-system-logs'))
                    <flux:navlist.group expandable heading="Operations"  :expanded="false">
                        @can('bulk-operations')
                            <flux:navlist.item 
                                icon="bolt" 
                                href="{{ route('bulk.operations') }}" 
                            >
                                Bulk Operations
                            </flux:navlist.item>
                        @endcan

                        @can('view-system-logs')
                            <flux:navlist.item 
                                icon="chart-bar" 
                                href="{{ route('log-dashboard') }}" 
                            >
                                Logs
                            </flux:navlist.item>
                        @endcan
                    </flux:navlist.group>
                @endif

                {{-- 🏢 MANAGEMENT - Only for admins --}}
                @can('manage-system-settings')
                    <flux:navlist.group expandable heading="Management"  :expanded="false">
                        <flux:navlist.item 
                            icon="users" 
                            href="{{ route('management.users.index') }}"
                        >
                            Users
                        </flux:navlist.item>

                        <flux:navlist.item 
                            icon="key" 
                            href="{{ route('management.user-roles.index') }}"
                        >
                            User Roles
                        </flux:navlist.item>
                    </flux:navlist.group>
                @endcan
            </flux:navlist>

            <flux:spacer />

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

        <flux:main>
            {{ $slot }}
        </flux:main>

        {{-- ✨ PHOENIX TOAST - CUSTOMIZABLE PERFECTION ✨ --}}
        <flux:toast 
            position="bottom-right" 
            animation="glitter" 
            theme="modern" 
            :duration="5000" 
            :max-toasts="3" 
            glitter-intensity="high"
            x-on:notify.window="addToast($event.detail.message, $event.detail.type, $event.detail.duration)"
        />

        @fluxScripts
        @livewireScripts
        
        {{-- Global Images Image Selector - Temporarily disabled to debug Flux errors --}}
        {{-- <livewire:images.image-selector /> --}}

        {{-- Beautiful Confirmation Modal --}}
        <x-confirmation-modal />

        {{-- Component Scripts Stack --}}
        @stack('scripts')

        {{-- Global Confirm Modal Helper --}}
        <script>
            // Simple global function to show confirmation modal
            window.confirmAction = function(options) {
                const modal = document.querySelector('#confirmation-dialog')
                if (modal && modal._x_dataStack) {
                    const modalData = modal._x_dataStack[0]
                    modalData.open(options)
                }
            }
        </script>
    </body>
</html>