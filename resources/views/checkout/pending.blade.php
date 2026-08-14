<!DOCTYPE html>
<html lang="pt-BR">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>Cadastro recebido -- Oravel</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />

        <script src="https://cdn.tailwindcss.com"></script>
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

                <div class="w-full max-w-md rounded-3xl border border-white/25 bg-white/15 p-8 text-center shadow-2xl backdrop-blur-xl sm:p-10">
                    <span class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-full border border-white/30 bg-white/10">
                        <x-heroicon-o-clock class="h-7 w-7 text-white" />
                    </span>

                    <h1 class="text-xl font-bold text-white">Cadastro recebido</h1>

                    <p class="mt-3 text-sm text-white/75">
                        Sua empresa foi cadastrada, mas não conseguimos gerar o link de pagamento agora.
                        Entraremos em contato para concluir a assinatura, ou você pode tentar novamente
                        mais tarde.
                    </p>

                    <a href="{{ route('checkout.create') }}"
                        class="mt-6 inline-block w-full rounded-full px-6 py-3 text-sm font-bold uppercase tracking-wide text-white shadow-lg transition hover:opacity-90"
                        style="background-color: #ea580c;">
                        Voltar
                    </a>
                </div>

                <p class="relative z-10 mt-8 text-center text-xs text-white/50">
                    © {{ now()->year }} Oravel. Todos os direitos reservados.
                </p>
            </div>
        </div>
    </body>
</html>
