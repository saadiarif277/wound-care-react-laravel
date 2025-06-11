{{-- CSRF Protected Form Component --}}
<form {{ $attributes->merge(['method' => 'POST']) }}>
    @csrf
    {{ $slot }}
</form>
