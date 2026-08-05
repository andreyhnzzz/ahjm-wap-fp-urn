<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Forgot your password?') }} - SIGA</title>

    <link rel="icon" href="/favicon.ico" sizes="any">
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">

    <!-- Fuentes específicas del diseño -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Figtree:wght@400;500;600;700&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="m-0 font-['Figtree',system-ui,sans-serif] antialiased bg-[#f4f6f9]">
    <div class="min-h-screen w-full flex items-center justify-center p-4 md:p-10">

        <!-- Tarjeta Principal -->
        <div class="w-full max-w-[420px] bg-white border border-[#e2e6ec] rounded-[14px] px-5 py-6 md:px-9 md:py-10 shadow-[0_1px_3px_rgba(16,49,97,0.06)]">

            <!-- Encabezado -->
            <div class="flex flex-col items-center gap-1.5 mb-7">
                <img src="{{ asset('images/logo-utn.AVIF') }}" alt="UTN" class="w-16 md:w-[84px] h-auto mb-1.5">
                <h1 class="text-lg md:text-[22px] font-bold text-[#0b2a5b] text-center">
                    {{ __('Forgot your password?') }}
                </h1>
                <p class="text-[13px] md:text-sm text-[#5b6b83] text-center leading-relaxed">
                    {{ __('Enter your institutional email and we will send you a link to reset it') }}
                </p>
            </div>

            <!-- Lógica reactiva y Formulario (Alpine.js) -->
            <div x-data="{
                email: '{{ old('email') }}',
                error: false,
                validateForm(e) {
                    const valid = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(this.email.trim());
                    if (!valid) {
                        e.preventDefault();
                        this.error = true;
                    } else {
                        this.error = false;
                        // Permite el envío del formulario al backend de Laravel
                    }
                }
            }">

                <!-- Mensaje de Éxito (Renderizado por la sesión de Laravel tras un envío exitoso) -->
                @if (session('status'))
                <div class="bg-[#eaf1fb] border border-[#c7dbf2] rounded-[10px] py-3.5 px-4 mb-5 text-sm text-[#0b2a5b] leading-relaxed">
                    {{ __('We sent a reset link to') }} <strong>{{ session('email', old('email')) }}</strong>. {{ __('Check your inbox.') }}
                </div>
                @endif

                <form method="POST" action="{{ route('password.email') }}" @submit="validateForm">
                    @csrf

                    <label for="email" class="block text-[13px] font-semibold text-[#24344f] mb-1.5">
                        {{ __('Email address') }}
                    </label>
                    <input
                        type="email"
                        id="email"
                        name="email"
                        x-model="email"
                        placeholder="nombre.apellido@estudiantec.utn.ac.cr"
                        required
                        autofocus
                        class="w-full px-3.5 py-3 border border-[#d7dde6] rounded-lg text-sm text-[#1c2b45] outline-none focus:border-[#0b2a5b] transition-colors mb-1" />

                    <!-- Mensajes de Error (Cliente: Alpine / Servidor: Laravel) -->
                    <div x-show="error" x-cloak class="text-[#c0392b] text-[13px] mt-1.5">
                        {{ __('Please enter a valid email.') }}
                    </div>
                    @error('email')
                    <div class="text-[#c0392b] text-[13px] mt-1.5">{{ $message }}</div>
                    @enderror

                    <button type="submit" class="w-full mt-4.5 py-3 bg-[#0b2a5b] hover:bg-[#0e3673] text-white rounded-lg text-sm font-bold cursor-pointer transition-colors">
                        {{ __('Send reset link') }}
                    </button>
                </form>
            </div>

            <!-- Conexión SPA de regreso a la vista base -->
            <div class="text-center mt-5 text-[13px] text-[#5b6b83]">
                {{ __('Remembered it?') }}
                <a href="{{ route('home') }}" wire:navigate class="text-[#0b2a5b] font-semibold underline hover:text-[#0e3673] transition-colors ml-1">
                    {{ __('Log in') }}
                </a>
            </div>

        </div>
    </div>
</body>

</html>