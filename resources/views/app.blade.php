<!DOCTYPE html>
<html class="h-full bg-gray-200">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @routes
    @inertiaHead
    @viteReactRefresh
    @vite(['resources/css/app.css', 'resources/js/app.tsx'])
    
    <!-- Google Maps Script -->
    @if(config('services.google.maps_api_key'))
    <script>
        window.ENV = window.ENV || {};
        window.ENV.GOOGLE_MAPS_API_KEY = "{{ config('services.google.maps_api_key') }}";
    </script>
    <script async defer
        src="https://maps.googleapis.com/maps/api/js?key={{ config('services.google.maps_api_key') }}&libraries=places">
    </script>
    @endif
</head>
<body class="font-sans antialiased leading-none text-gray-800">
@inertia
</body>
</html>
