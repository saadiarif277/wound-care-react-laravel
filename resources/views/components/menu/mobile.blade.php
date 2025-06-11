@props([
    'menu' => [],
])

@php
    $menuService = app(\App\Services\MenuService::class);
    $menuItems = empty($menu) ? $menuService->getMenuForUser(auth()->user()) : $menu;
@endphp

<div x-data="{ 
        open: false,
        activeItems: {},
        toggleMenu() {
            this.open = !this.open;
            if (this.open) {
                document.body.style.overflow = 'hidden';
            } else {
                document.body.style.overflow = '';
            }
        },
        toggleSubmenu(id) {
            this.activeItems[id] = !this.activeItems[id];
        },
        isActive(id) {
            return this.activeItems[id] || false;
        }
     }"
     @keydown.escape="open = false"
     class="lg:hidden">
    
    {{-- Menu Toggle Button --}}
    <button @click="toggleMenu()"
            class="mobile-menu-toggle inline-flex items-center justify-center p-2 rounded-md text-gray-400 hover:text-gray-500 hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-blue-500">
        <span class="sr-only">Open main menu</span>
        <svg class="h-6 w-6" x-show="!open" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
        </svg>
        <svg class="h-6 w-6" x-show="open" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
        </svg>
    </button>

    {{-- Mobile Menu Overlay --}}
    <div x-show="open"
         x-transition:enter="transition-opacity ease-linear duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition-opacity ease-linear duration-300"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 bg-gray-600 bg-opacity-75 z-40"
         @click="toggleMenu()">
    </div>

    {{-- Mobile Menu Panel --}}
    <div x-show="open"
         x-transition:enter="transition ease-in-out duration-300 transform"
         x-transition:enter-start="-translate-x-full"
         x-transition:enter-end="translate-x-0"
         x-transition:leave="transition ease-in-out duration-300 transform"
         x-transition:leave-start="translate-x-0"
         x-transition:leave-end="-translate-x-full"
         class="fixed inset-y-0 left-0 max-w-xs w-full bg-white dark:bg-gray-800 shadow-xl z-50 overflow-y-auto">
        
        <div class="flex items-center justify-between p-4 border-b border-gray-200 dark:border-gray-700">
            <h2 class="text-xl font-semibold text-gray-800 dark:text-white">Menu</h2>
            <button @click="toggleMenu()"
                    class="p-2 rounded-md text-gray-400 hover:text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-700">
                <span class="sr-only">Close menu</span>
                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        {{-- Search Box --}}
        <div class="p-4 border-b border-gray-200 dark:border-gray-700">
            <div class="relative">
                <input type="search"
                       placeholder="Search menu..."
                       class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-md leading-5 bg-white dark:bg-gray-700 dark:border-gray-600 placeholder-gray-500 focus:outline-none focus:placeholder-gray-400 focus:ring-1 focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                       @input="searchMenu($event.target.value)">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                    <svg class="h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                </div>
            </div>
        </div>

        {{-- Menu Items --}}
        <nav class="p-4 space-y-1">
            @foreach($menuItems as $item)
                <x-menu.mobile-item :item="$item" :level="0" />
            @endforeach
        </nav>

        {{-- User Info --}}
        <div class="absolute bottom-0 left-0 right-0 p-4 border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900">
            <div class="flex items-center">
                <img class="h-10 w-10 rounded-full" 
                     src="{{ auth()->user()->avatar_url ?? 'https://ui-avatars.com/api/?name=' . urlencode(auth()->user()->name) }}" 
                     alt="{{ auth()->user()->name }}">
                <div class="ml-3">
                    <p class="text-sm font-medium text-gray-700 dark:text-gray-200">{{ auth()->user()->name }}</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ auth()->user()->email }}</p>
                </div>
            </div>
        </div>
    </div>
</div>

@push('styles')
<style>
    @media (max-width: 1023px) {
        body.menu-open {
            overflow: hidden;
        }
    }
</style>
@endpush

@push('scripts')
<script>
    // Search functionality
    window.searchMenu = function(query) {
        if (query.length < 2) return;
        
        fetch('/api/menu/search', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
            body: JSON.stringify({ query })
        })
        .then(response => response.json())
        .then(data => {
            // Update menu with search results
            // This would be handled by Alpine.js or your preferred framework
        });
    };
</script>
@endpush