@props([
    'menu' => [],
    'class' => '',
    'collapsible' => true,
])

@php
    $menuService = app(\App\Services\MenuService::class);
    $menuItems = empty($menu) ? $menuService->getMenuForUser(auth()->user()) : $menu;
@endphp

<nav {{ $attributes->merge(['class' => 'menu-sidebar ' . $class]) }}
     x-data="{ 
         collapsed: localStorage.getItem('menu-collapsed') === 'true',
         activeItems: {},
         toggleCollapse() {
             this.collapsed = !this.collapsed;
             localStorage.setItem('menu-collapsed', this.collapsed);
         },
         toggleSubmenu(id) {
             this.activeItems[id] = !this.activeItems[id];
         },
         isActive(id) {
             return this.activeItems[id] || false;
         }
     }"
     :class="{ 'collapsed': collapsed }"
>
    @if($collapsible)
        <button @click="toggleCollapse()" 
                class="menu-toggle-btn"
                aria-label="Toggle menu">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                      d="M4 6h16M4 12h16M4 18h16" />
            </svg>
        </button>
    @endif

    <div class="menu-content">
        @foreach($menuItems as $item)
            <x-menu.item :item="$item" :level="0" />
        @endforeach
    </div>

    {{-- Favorites Section --}}
    @php
        $favorites = $menuService->getFavorites(auth()->user());
    @endphp
    
    @if($favorites->isNotEmpty())
        <div class="menu-favorites">
            <h3 class="menu-section-title">Favorites</h3>
            @foreach($favorites as $item)
                <x-menu.item :item="$item" :level="0" :simple="true" />
            @endforeach
        </div>
    @endif

    {{-- Quick Access Section --}}
    @php
        $frequentItems = $menuService->getFrequentItems(auth()->user(), 5);
    @endphp
    
    @if($frequentItems->isNotEmpty())
        <div class="menu-quick-access">
            <h3 class="menu-section-title">Quick Access</h3>
            @foreach($frequentItems as $item)
                <x-menu.item :item="$item" :level="0" :simple="true" />
            @endforeach
        </div>
    @endif
</nav>

@push('styles')
<style>
    .menu-sidebar {
        @apply bg-white dark:bg-gray-800 shadow-lg h-full overflow-y-auto transition-all duration-300;
        width: 280px;
    }
    
    .menu-sidebar.collapsed {
        width: 60px;
    }
    
    .menu-toggle-btn {
        @apply p-2 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-md transition-colors duration-200;
        @apply flex items-center justify-center w-full;
    }
    
    .menu-content {
        @apply p-4 space-y-1;
    }
    
    .menu-section-title {
        @apply text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider px-3 py-2;
    }
    
    .collapsed .menu-section-title {
        @apply hidden;
    }
    
    .menu-favorites,
    .menu-quick-access {
        @apply mt-6 border-t border-gray-200 dark:border-gray-700 pt-4;
    }
</style>
@endpush

@push('scripts')
<script>
    // Track menu analytics
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('.menu-item-link').forEach(link => {
            link.addEventListener('click', function(e) {
                const menuItemId = this.dataset.menuItemId;
                if (menuItemId) {
                    // Send analytics in background
                    fetch('/api/menu/track-click', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                        },
                        body: JSON.stringify({ menu_item_id: menuItemId })
                    });
                }
            });
        });
    });
</script>
@endpush