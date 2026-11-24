<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700|playfair-display:600,700&display=swap" rel="stylesheet" />

        <script src="https://cdn.tailwindcss.com"></script>
        <style>
            /* Mesmo tema do login do painel: foto + camada verde da marca + cartao translucido. */
            .oravel-guest-root {
                font-family: Inter, ui-sans-serif, system-ui, sans-serif;
                color: #FFFFFF;
                background-color: #0B2B2A;
                background-image: linear-gradient(100deg, rgba(11, 43, 42, 0.94) 0%, rgba(11, 43, 42, 0.82) 45%, rgba(11, 43, 42, 0.62) 100%), url('{{ asset('images/login-bg.jpg').'?v=3' }}');
                background-size: cover;
                background-position: center;
            }
            .oravel-guest-logo { background: #0B2B2A; border: 1px solid rgba(242, 140, 27, 0.5); }
            .oravel-guest-card {
                background: rgba(11, 43, 42, 0.78);
                border: 1px solid rgba(255, 255, 255, 0.14);
                border-radius: 16px;
                box-shadow: 0 20px 50px rgba(0, 0, 0, 0.35);
                backdrop-filter: blur(10px);
            }
            .oravel-guest-card .text-gray-600, .oravel-guest-card .text-gray-700, .oravel-guest-card label, .oravel-guest-card .text-sm { color: rgba(255, 255, 255, 0.8); }
            .oravel-guest-card .text-green-600 { color: #86EFAC; }
            .oravel-guest-card .text-red-600 { color: #FCA5A5; }
            .oravel-guest-card input:not([type=checkbox]) {
                background: rgba(255, 255, 255, 0.08); color: #FFFFFF;
                border: 1px solid rgba(255, 255, 255, 0.25); border-radius: 9999px;
                padding-left: 1rem; padding-right: 1rem; box-shadow: none; outline: none;
            }
            .oravel-guest-card input:not([type=checkbox])::placeholder { color: rgba(255, 255, 255, 0.55); }
            .oravel-guest-card input:not([type=checkbox]):focus { border-color: #F28C1B; box-shadow: 0 0 0 3px rgba(242, 140, 27, 0.25); --tw-ring-shadow: 0 0 #0000; }
            .oravel-guest-card button[type=submit] { background: #F28C1B; color: #0B2B2A; border-radius: 9999px; padding: .6rem 1.4rem; font-weight: 700; }
            .oravel-guest-card button[type=submit]:hover, .oravel-guest-card button[type=submit]:focus { background: #D97A0C; }
            .oravel-guest-card a { color: rgba(255, 255, 255, 0.8); }
            .oravel-guest-card a:hover { color: #FFFFFF; }
        </style>
    </head>
    <body class="antialiased">
        <div class="oravel-guest-root min-h-screen flex flex-col sm:justify-center items-center pt-6 sm:pt-0">
            <div>
                <a href="/" class="oravel-guest-logo w-20 h-20 rounded-full flex items-center justify-center">
                    <img src="{{ asset('images/oravel-logo-or.png') }}" alt="Oravel" class="w-10 h-10">
                </a>
            </div>

            <div class="oravel-guest-card w-full sm:max-w-md mt-6 px-6 py-6 overflow-hidden">
                {{ $slot }}
            </div>
        </div>
    </body>
</html>
