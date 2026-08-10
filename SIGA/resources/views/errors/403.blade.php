<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Access denied') }} &middot; {{ config('app.name') }}</title>
    {{--
        Deliberately plain inline styles, no @vite/app.css dependency.
        This page has to render correctly even in a worst-case scenario
        where something else in the asset pipeline is broken — an error
        page that itself fails to render defeats the point.
    --}}
    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #eef2f9;
            font-family: -apple-system, 'Segoe UI', Arial, sans-serif;
            color: #0b1b33;
            padding: 24px;
        }

        .card {
            background: #fff;
            border-radius: 14px;
            box-shadow: 0 8px 30px rgba(15, 37, 71, .12);
            max-width: 440px;
            width: 100%;
            padding: 40px 36px;
            text-align: center;
        }

        .icon {
            width: 64px;
            height: 64px;
            border-radius: 50%;
            background: #fdeceb;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
        }

        h1 {
            font-size: 21px;
            font-weight: 700;
            color: #0b3c8c;
            margin: 0 0 10px;
        }

        p {
            font-size: 14.5px;
            color: #5a6478;
            line-height: 1.55;
            margin: 0 0 26px;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: #0b3c8c;
            color: #fff;
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
            padding: 11px 22px;
            border-radius: 8px;
        }

        .btn:hover {
            background: #0a3372;
        }
    </style>
</head>

<body>
    <div class="card">
        <div class="icon">
            <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="#DC2626" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" />
                <line x1="9.5" y1="9.5" x2="14.5" y2="14.5" />
                <line x1="14.5" y1="9.5" x2="9.5" y2="14.5" />
            </svg>
        </div>
        <h1>{{ __('You do not have permission to view this page') }}</h1>
        <p>{{ $exception->getMessage() ?: __('Your account does not have the required permission for this action. If you believe this is a mistake, contact your system administrator.') }}</p>
        @auth
        <a href="{{ route('dashboard') }}" class="btn">{{ __('Back to dashboard') }}</a>
        @else
        <a href="{{ route('home') }}" class="btn">{{ __('Back to home') }}</a>
        @endauth
    </div>
</body>

</html>