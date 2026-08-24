<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Merlo Transportes') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=poppins:500,600,700,800|instrument-sans:400,500,600" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans text-[#2B1113] antialiased">
        <div class="min-h-screen flex flex-col sm:justify-center items-center pt-8 sm:pt-0 bg-[#FFFBF6] px-4">
            <a href="/" class="mt-4 sm:mt-0">
                <img src="{{ asset('Logo.png') }}" alt="Merlo Transportes" class="h-14 w-auto">
            </a>

            <div class="w-full sm:max-w-md mt-8 px-6 py-8 sm:px-8 bg-white shadow-xl shadow-black/5 ring-1 ring-black/5 overflow-hidden rounded-2xl">
                {{ $slot }}
            </div>
        </div>
    </body>
</html>
