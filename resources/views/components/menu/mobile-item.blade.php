@props([
    'item',
    'level' => 0,
])

@php
    $hasChildren = !empty($item['children']) && count($item['children']) > 0;
    $isActive = $item['is_active'] ?? false;
    $url = $item['url'] ?? '#';
    $badges = $item['badges'] ?? [];
@endphp

@if($item['is_divider'] ?? false)
    <div class="my-2 border-t border-gray-200 dark:border-gray-700"></div>
@else
    <div class="mobile-menu-item">
        <a href="{{ $url }}"
           @if($hasChildren) 
               @click.prevent="toggleSubmenu('{{ $item['id'] }}')"
           @else
               @click="toggleMenu()"
           @endif
           class="flex items-center px-3 py-2 text-base font-medium rounded-md {{ $isActive ? 'bg-blue-50 dark:bg-blue-900 text-blue-700 dark:text-blue-200' : 'text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700' }}"
           style="padding-left: {{ ($level * 1.5) + 0.75 }}rem">
            
            {{-- Icon --}}
            @if($item['icon'])
                <span class="mr-3 flex-shrink-0">
                    @if($item['icon_type'] === 'heroicon')
                        <x-dynamic-component :component="'heroicon-o-' . $item['icon']" class="w-6 h-6" />
                    @elseif($item['icon_type'] === 'fontawesome')
                        <i class="{{ $item['icon'] }} text-lg"></i>
                    @else
                        <img src="{{ $item['icon'] }}" alt="" class="w-6 h-6">
                    @endif
                </span>
            @endif
            
            {{-- Title --}}
            <span class="flex-grow">{{ $item['title'] }}</span>
            
            {{-- Badges --}}
            @foreach($badges as $badge)
                <span class="{{ $badge['css_classes'] }} ml-2">
                    @if($badge['type'] === 'dot')
                        <span class="w-2 h-2 rounded-full bg-current"></span>
                    @else
                        {{ $badge['value'] }}
                    @endif
                </span>
            @endforeach
            
            {{-- Chevron for items with children --}}
            @if($hasChildren)
                <svg class="ml-auto h-5 w-5 transform transition-transform duration-200"
                     :class="{ 'rotate-180': isActive('{{ $item['id'] }}') }"
                     fill="none" 
                     viewBox="0 0 24 24" 
                     stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                </svg>
            @endif
        </a>
        
        {{-- Children --}}
        @if($hasChildren)
            <div x-show="isActive('{{ $item['id'] }}')"
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0"
                 x-transition:enter-end="opacity-100"
                 x-transition:leave="transition ease-in duration-150"
                 x-transition:leave-start="opacity-100"
                 x-transition:leave-end="opacity-0"
                 class="mt-1 space-y-1">
                @foreach($item['children'] as $child)
                    <x-menu.mobile-item :item="$child" :level="$level + 1" />
                @endforeach
            </div>
        @endif
    </div>
@endif