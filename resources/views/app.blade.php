<!DOCTYPE html>
<html lang="id">
<head>
    {{-- header memuat charset, <title>, ikon, dan stylesheet dasar. --}}
    @include('components.header')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @vite('resources/js/app.js')
    @inertiaHead
</head>
<body>
    @inertia
</body>
</html>
