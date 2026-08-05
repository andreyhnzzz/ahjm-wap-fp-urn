<section class="w-full max-w-[380px] mx-auto relative z-10 lg:mb-0 mb-10">
    <div class="bg-white rounded-[18px] shadow-[0_30px_60px_-24px_rgba(11,61,120,0.3)] border border-[#eef1f5] overflow-hidden">

        <div class="bg-siga-brand pt-7 px-8 pb-11 relative text-center">
            <div class="font-merriweather font-black text-lg text-white">{{ __('National Technical University') }}</div>
        </div>

        <div class="w-16 h-16 rounded-full bg-white border-4 border-white -mt-8 mx-auto flex items-center justify-center relative shadow-[0_4px_10px_rgba(11,61,120,0.15)] overflow-hidden">
            <img src="{{ asset('images/logo-utn.AVIF') }}" alt="UTN" class="w-[70%] h-[70%] object-contain">
        </div>

        <div class="p-5 md:px-8 md:pb-8 md:pt-5">
            @auth
            <div class="text-center">
                <h2 class="text-xl font-bold text-[#12233c] mb-1">{{ __('Welcome back!') }}</h2>
                <p class="text-[13.5px] text-[#6b7684] mb-6">{{ auth()->user()->name }}</p>

                <a href="{{ route('dashboard') }}" wire:navigate class="block w-full bg-siga-brand hover:bg-siga-brand-hover text-white text-[15px] font-bold py-3.5 rounded-lg transition-colors">
                    {{ __('Go to Dashboard') }}
                </a>

                <form method="POST" action="{{ route('logout') }}" class="mt-3">
                    @csrf
                    <button type="submit" class="w-full text-[13px] font-semibold text-[#8993a1] hover:text-siga-brand transition-colors">
                        {{ __('Log out') }}
                    </button>
                </form>
            </div>
            @else
            <h2 class="text-xl font-bold text-[#12233c] mb-1 text-center">{{ __('Log in') }}</h2>
            <p class="text-[13.5px] text-[#6b7684] mb-6 text-center">{{ __('UTN Institutional Account') }}</p>

            <!-- Session Status / Errors -->
            <x-auth-session-status class="mb-4 text-center" :status="session('status')" />
            @if ($errors->any())
            <div class="mb-4 text-sm text-red-600 text-center">{{ __('These credentials do not match our records.') }}</div>
            @endif

            <form method="POST" action="{{ route('login.store') }}" class="flex flex-col gap-4">
                @csrf
                <label class="flex flex-col gap-1.5">
                    <span class="text-[13px] font-semibold text-[#33404f]">{{ __('Institutional Email') }}</span>
                    <input type="email" name="email" value="{{ old('email') }}" required autofocus placeholder="nombre.apellido@utn.ac.cr" class="px-3.5 py-3 border border-[#d6dce4] rounded-lg text-[14.5px] outline-none focus:border-siga-brand focus:ring-3 focus:ring-siga-brand/10 transition-all">
                </label>

                <label class="flex flex-col gap-1.5" x-data="{ show: false }">
                    <span class="text-[13px] font-semibold text-[#33404f]">{{ __('Password') }}</span>
                    <div class="relative flex items-center">
                        <input :type="show ? 'text' : 'password'" name="password" required placeholder="••••••••" class="w-full px-3.5 py-3 pr-10 border border-[#d6dce4] rounded-lg text-[14.5px] outline-none focus:border-siga-brand focus:ring-3 focus:ring-siga-brand/10 transition-all">

                        <button type="button" @click="show = !show" class="absolute right-2.5 p-1 text-[#8993a1] hover:text-siga-brand transition-colors" aria-label="{{ __('Show or hide password') }}">
                            <svg x-show="!show" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7Z"></path>
                                <circle cx="12" cy="12" r="3"></circle>
                            </svg>
                            <svg x-show="show" x-cloak width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                <path d="M3 3l18 18"></path>
                                <path d="M10.6 10.6a3 3 0 0 0 4.24 4.24"></path>
                                <path d="M9.9 5.1A10.4 10.4 0 0 1 12 5c6.5 0 10 7 10 7a13.9 13.9 0 0 1-2.9 3.9M6.4 6.4C4.3 7.8 2.7 9.9 2 12c0 0 3.5 7 10 7 1 0 1.9-.1 2.8-.4"></path>
                            </svg>
                        </button>
                    </div>
                </label>

                @if (Route::has('password.request'))
                <div class="flex justify-end -mt-1">
                    <a href="{{ route('password.request') }}" wire:navigate class="text-[13px] font-semibold text-siga-brand hover:text-siga-brand-hover transition-colors">
                        {{ __('Forgot your password?') }}
                    </a>
                </div>
                @endif

                <button type="submit" class="mt-1 bg-siga-brand hover:bg-siga-brand-hover text-white py-3 rounded-lg text-[15px] font-bold transition-colors">
                    {{ __('Sign in') }}
                </button>
            </form>
            @endauth
        </div>
    </div>
</section>