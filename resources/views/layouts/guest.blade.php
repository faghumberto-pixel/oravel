<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet" />

        <script src="https://cdn.tailwindcss.com"></script>
            <style>
            .oravel-guest-card input:not([type=checkbox]) { border-color: #C3CFCC; border-radius: 9999px; padding-left: 1rem; padding-right: 1rem; box-shadow: none; }
            .oravel-guest-card input:not([type=checkbox]):focus { border-color: #0F3B38; box-shadow: 0 0 0 3px rgba(15, 59, 56, .15); --tw-ring-shadow: 0 0 #0000; outline: none; }
            .oravel-guest-card button[type=submit] { background: #F28C1B; border-radius: 9999px; padding: .6rem 1.4rem; }
            .oravel-guest-card button[type=submit]:hover, .oravel-guest-card button[type=submit]:focus { background: #D97A0C; }
            .oravel-guest-card a { color: #0B2B2A; }
            .oravel-guest-card .text-gray-600 { color: #5B6B69; }
        </style>
    </head>
    <body class="antialiased" style="font-family: Inter, ui-sans-serif, system-ui, sans-serif; color: #12201F;">
        <div class="min-h-screen flex flex-col sm:justify-center items-center pt-6 sm:pt-0" style="background: #FAF6EE;">
            <div>
                <a href="/" class="w-20 h-20 rounded-full flex items-center justify-center" style="background: #0B2B2A;">
                    <img src="{{ asset('images/oravel-logo-or.png') }}" alt="Oravel" class="w-10 h-10">
                </a>
            </div>

            <div class="oravel-guest-card w-full sm:max-w-md mt-6 px-6 py-6 bg-white overflow-hidden rounded-2xl" style="box-shadow: 0 4px 20px rgba(0,0,0,.05);">
                {{ $slot }}
            </div>
        </div>
    </body>
</html>