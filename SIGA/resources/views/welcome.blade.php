<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Comprehensive Academic Management System') }} - SIGA</title>

    <link rel="icon" href="/favicon.ico" sizes="any">
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Source+Sans+3:wght@400;500;600;700&family=Merriweather:wght@700;900&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="flex flex-col min-h-screen bg-[#fbfcfd] bg-[radial-gradient(circle,#dde5ee_1px,transparent_1px)] [background-size:22px_22px]">

    <x-siga.header />

    <main class="flex-1 grid grid-cols-1 lg:grid-cols-[1.2fr_1fr] items-center gap-10 px-5 md:px-14 max-w-7xl mx-auto w-full relative">
        <!-- Marca de agua de fondo -->
        <span class="absolute left-[-20px] lg:left-[-40px] top-1/2 -translate-y-1/2 font-merriweather font-black text-[200px] lg:text-[340px] leading-none text-siga-brand opacity-5 pointer-events-none select-none z-0">S</span>

        <x-siga.hero />
        <x-siga.auth-card />
    </main>

    <x-siga.footer />

    @persist('toast')
    <flux:toast.group>
        <flux:toast />
    </flux:toast.group>
    @endpersist
    @fluxScripts
</body>

</html>