<!DOCTYPE html>
<html lang="pt-BR">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>Recuperar link de pagamento - Oravel</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />

        <script src="https://cdn.tailwindcss.com"></script>

        <style>
            .oravel-auth-field {
                display: flex;
                align-items: center;
                gap: 0.625rem;
                border-radius: 0.75rem;
                border: 1px solid rgba(255, 255, 255, 0.25);
                background-color: rgba(255, 255, 255, 0.12);
                padding: 0.625rem 1rem;
            }

            .oravel-auth-field:focus-within {
                --tw-ring-color: rgba(249, 115, 22, 0.6);
                box-shadow: 0 0 0 3px rgba(249, 115, 22, 0.35);
                border-color: rgba(249, 115, 22, 0.6);
            }

            .oravel-auth-field-icon { height: 1.1rem; width: 1.1rem; flex-shrink: 0; color: rgba(255, 255, 255, 0.7); }

            .oravel-auth-input-raw {
                flex: 1 1 auto;
                border: none;
                background: transparent;
                color: #fff;
                font-size: 0.875rem;
                outline: none;
            }

            .oravel-auth-input-raw::placeholder { color: rgba(255, 255, 255, 0.55); }

            .oravel-auth-label { font-size: 0.8rem; font-weight: 500; color: rgba(255, 255, 255, 0.75); margin-bottom: 0.375rem; display: block; }

            .oravel-auth-error { margin-top: 0.375rem; font-size: 0.75rem; color: #fca5a5; }
        </style>
    </head>
    <body class="font-sans antialiased">
        <div
            class="relative min-h-screen w-full overflow-hidden bg-slate-950"
            style="background-image: linear-gradient(to bottom right, rgba(0,0,0,0.8), rgba(0,0,0,0.55), rgba(67,20,7,0.3)), url('{{ asset('images/login-bg.jpg') }}'); background-size: cover; background-position: center;"
        >
            <div class="relative z-10 flex min-h-screen flex-col items-center justify-center px-6 py-12">
                <a href="https://oravel.com.br" class="mb-6 flex items-center gap-3">
                    <span class="flex h-14 w-14 items-center justify-center rounded-full border border-white/30 bg-white/10">
                        <x-heroicon-o-bolt class="h-7 w-7 text-white" />
                    </span>
                    <span class="text-2xl font-bold text-white">Oravel</span>
                </a>

                <div class="w-full max-w-md rounded-3xl border border-white/25 bg-white/15 p-8 shadow-2xl backdrop-blur-xl sm:p-10">
                    <h1 class="text-2xl font-bold text-white">Recuperar link de pagamento</h1>
                    <p class="mt-2 text-sm text-white/75">
                        Já se cadastrou e assinou o contrato, mas fechou a página antes de pagar?
                        Informe o e-mail que você usou no cadastro.
                    </p>

                    <div class="my-6 h-px w-full bg-white/15"></div>

                    @if ($errors->any())
                        <div class="mb-5 rounded-xl border border-red-400/40 bg-red-500/10 p-3 text-sm text-red-200">
                            {{ $errors->first() }}
                        </div>
                    @endif

                    <form method="POST" action="{{ route('checkout.recover.submit') }}" class="space-y-5">
                        @csrf

                        <div>
                            <label class="oravel-auth-label">E-mail do cadastro</label>
                            <div class="oravel-auth-field">
                                <x-heroicon-o-envelope class="oravel-auth-field-icon" />
                                <input type="email" name="email" value="{{ old('email') }}" required autofocus
                                    class="oravel-auth-input-raw">
                            </div>
                        </div>

                        <button type="submit"
                            class="w-full rounded-xl bg-orange-500 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-orange-600">
                            Buscar meu link
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </body>
</html>
