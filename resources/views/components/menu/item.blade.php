@props([
    'item',
    'level' => 0,
    'simple' => false,
])

@php
    $hasChildren = !empty($item['children']) && count($item['children']) > 0;
    $isActive = $item['is_active'] ?? false;
    $url = $item['url'] ?? '#';
    $badges = $item['badges'] ?? [];
@endphp

@if($item['is_divider'] ?? false)
    <div class="menu-divider" style="margin-left: {{ $level * 1.5 }}rem"></div>
@else
    <div class="menu-item-wrapper" 
         :class="{ 'has-children': {{ $hasChildren ? 'true' : 'false' }} }"
         style="padding-left: {{ $level * 1.5 }}rem">
        
        <a href="{{ $url }}"
           @if($hasChildren) 
               @click.prevent="toggleSubmenu('{{ $item['id'] }}')"
           @endif
           class="menu-item-link {{ $isActive ? 'active' : '' }} {{ $item['css_class'] ?? '' }}"
           data-menu-item-id="{{ $item['id'] }}"
           target="{{ $item['target'] ?? '_self' }}"
           @if(!$simple)
               x-tooltip="{{ $item['title'] }}"
           @endif
        >
            {{-- Icon --}}
            @if($item['icon'])
                <span class="menu-item-icon">
                    @if($item['icon_type'] === 'heroicon')
                        <x-dynamic-component :component="'heroicon-o-' . $item['icon']" class="w-5 h-5" />
                    @elseif($item['icon_type'] === 'fontawesome')
                        <i class="{{ $item['icon'] }}"></i>
                    @else
                        <img src="{{ $item['icon'] }}" alt="" class="w-5 h-5">
                    @endif
                </span>
            @endif
            
            {{-- Title --}}
            <span class="menu-item-title" :class="{ 'sr-only': collapsed && {{ $level }} === 0 }">
                {{ $item['title'] }}
            </span>
            
            {{-- Badges --}}
            @foreach($badges as $badge)
                <span class="{{ $badge['css_classes'] }}">
                    @if($badge['type'] === 'dot')
                        <span class="menu-badge-dot"></span>
                    @else
                        {{ $badge['value'] }}
                    @endif
                </span>
            @endforeach
            
            {{-- Chevron for items with children --}}
            @if($hasChildren && !$simple)
                <svg class="menu-item-chevron ml-auto transform transition-transform duration-200"
                     :class="{ 'rotate-180': isActive('{{ $item['id'] }}') }"
                     fill="none" 
                     viewBox="0 0 24 24" 
                     stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                </svg>
            @endif
            
            {{-- Favorite button --}}
            @if(!$simple)
                <button @click.stop="toggleFavorite('{{ $item['id'] }}')"
                        class="menu-item-favorite ml-2 opacity-0 group-hover:opacity-100 transition-opacity"
                        :class="{ 'is-favorite': isFavorite('{{ $item['id'] }}') }">
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
                    </svg>
                </button>
            @endif
        </a>
        
        {{-- Children --}}
        @if($hasChildren && !$simple)
            <div class="menu-item-children"
                 x-show="isActive('{{ $item['id'] }}')"
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0 transform -translate-y-2"
                 x-transition:enter-end="opacity-100 transform translate-y-0"
                 x-transition:leave="transition ease-in duration-150"
                 x-transition:leave-start="opacity-100 transform translate-y-0"
                 x-transition:leave-end="opacity-0 transform -translate-y-2">
                @foreach($item['children'] as $child)
                    <x-menu.item :item="$child" :level="$level + 1" />
                @endforeach
            </div>
        @endif
    </div>
@endif

@once
@push('styles')
<style>
    .menu-divider {
        @apply border-t border-gray-200 dark:border-gray-700 my-2;
    }
    
    .menu-item-wrapper {
        @apply relative;
    }
    
    .menu-item-link {
        @apply flex items-center px-3 py-2 text-sm font-medium rounded-md;
        @apply text-gray-700 dark:text-gray-300;
        @apply hover:bg-gray-100 dark:hover:bg-gray-700 hover:text-gray-900 dark:hover:text-white;
        @apply transition-colors duration-200;
    }
    
    .menu-item-link.active {
        @apply bg-blue-50 dark:bg-blue-900 text-blue-700 dark:text-blue-200;
    }
    
    .menu-item-icon {
        @apply mr-3 flex-shrink-0;
    }
    
    .menu-item-title {
        @apply flex-grow;
    }
    
    .menu-item-chevron {
        @apply w-4 h-4;
    }
    
    .menu-item-favorite {
        @apply text-gray-400 hover:text-yellow-500 transition-colors;
    }
    
    .menu-item-favorite.is-favorite {
        @apply text-yellow-500 opacity-100;
    }
    
    .menu-item-children {
        @apply mt-1 space-y-1;
    }
    
    /* Badge styles */
    .menu-badge {
        @apply inline-flex items-center justify-center px-2 py-1 text-xs font-bold leading-none rounded-full;
    }
    
    .menu-badge-count {
        @apply min-w-[1.25rem];
    }
    
    .menu-badge-red {
        @apply bg-red-100 text-red-800 dark:bg-red-800 dark:text-red-100;
    }
    
    .menu-badge-blue {
        @apply bg-blue-100 text-blue-800 dark:bg-blue-800 dark:text-blue-100;
    }
    
    .menu-badge-green {
        @apply bg-green-100 text-green-800 dark:bg-green-800 dark:text-green-100;
    }
    
    .menu-badge-yellow {
        @apply bg-yellow-100 text-yellow-800 dark:bg-yellow-800 dark:text-yellow-100;
    }
    
    .menu-badge-gray {
        @apply bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-100;
    }
    
    .menu-badge-dot {
        @apply w-2 h-2 rounded-full bg-current;
    }
    
    .menu-badge-right {
        @apply ml-auto;
    }
    
    .menu-badge-left {
        @apply order-first mr-2;
    }
    
    /* Collapsed state */
    .collapsed .menu-item-title {
        @apply hidden;
    }
    
    .collapsed .menu-item-chevron {
        @apply hidden;
    }
    
    .collapsed .menu-item-favorite {
        @apply hidden;
    }
    
    .collapsed .menu-badge {
        @apply absolute top-0 right-0 transform translate-x-1/2 -translate-y-1/2;
    }
</style>
@endpush

@push('scripts')
<script>
    // Handle favorite toggle
    window.toggleFavorite = function(menuItemId) {
        fetch('/api/menu/toggle-favorite', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
            body: JSON.stringify({ menu_item_id: menuItemId })
        })
        .then(response => response.json())
        .then(data => {
            // Update UI or trigger Alpine.js event
            window.dispatchEvent(new CustomEvent('menu-favorite-toggled', { 
                detail: { menuItemId, isFavorite: data.is_favorite } 
            }));
        });
    };
    
    // Check if item is favorite
    window.isFavorite = function(menuItemId) {
        // This would be populated from the server
        return window.menuFavorites && window.menuFavorites.includes(menuItemId);
    };
</script>
@endpush
@endonce