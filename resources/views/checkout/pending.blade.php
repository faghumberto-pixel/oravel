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
            style="background-image: linear-gradient(to bottom right, rgba(0,0,0,0.8), rgba(0,0,0,0.55), rgba(67,20,7,0.3)), url('{{ asset('images/login-bg.jpg').'?v=3' }}'); background-size: cover; background-position: center;"
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

                    <h1 class="text-xl font-bold text-white">{{ $token ? 'Falta só o pagamento' : 'Cadastro recebido' }}</h1>

                    @if ($token && $motivo === 'email')
                        <p class="mt-3 text-sm text-white/75">
                            Seu cadastro e o contrato estão prontos, mas o e-mail <strong class="text-white">{{ $email }}</strong>
                            foi recusado pelo sistema de pagamento. Confira se está escrito certo e tente de novo.
                        </p>

                        <form method="POST" action="{{ route('checkout.fix-email', $token) }}" class="mt-5 text-left">
                            @csrf
                            <label for="email" class="block text-xs font-semibold uppercase tracking-wide text-white/70">E-mail correto</label>
                            <input id="email" name="email" type="email" required autocomplete="email" value="{{ old('email', $email) }}"
                                class="mt-1 w-full rounded-xl border border-white/30 bg-white/10 px-4 py-3 text-sm text-white placeholder-white/40 focus:border-orange-400 focus:outline-none">
                            @error('email')<p class="mt-2 text-sm text-orange-200">{{ $message }}</p>@enderror
                            <button type="submit" class="mt-4 inline-block w-full rounded-full px-6 py-3 text-sm font-bold uppercase tracking-wide text-white shadow-lg transition hover:opacity-90" style="background-color: #ea580c;">
                                Corrigir e ir para o pagamento
                            </button>
                        </form>
                    @elseif ($token)
                        <p class="mt-3 text-sm text-white/75">
                            Seu cadastro e o contrato de {{ $empresa }} estão prontos, mas não conseguimos gerar o link de pagamento agora.
                            Já avisamos a nossa equipe. Você pode tentar de novo agora mesmo.
                        </p>

                        <a href="{{ route('checkout.continue', $token) }}" class="mt-5 inline-block w-full rounded-full px-6 py-3 text-sm font-bold uppercase tracking-wide text-white shadow-lg transition hover:opacity-90" style="background-color: #ea580c;">
                            Tentar de novo
                        </a>
                    @else
                        <p class="mt-3 text-sm text-white/75">
                            Sua empresa foi cadastrada, mas não conseguimos gerar o link de pagamento agora.
                            Você pode tentar novamente mais tarde ou falar com a nossa equipe.
                        </p>

                        <a href="{{ route('checkout.recover') }}" class="mt-5 inline-block w-full rounded-full px-6 py-3 text-sm font-bold uppercase tracking-wide text-white shadow-lg transition hover:opacity-90" style="background-color: #ea580c;">
                            Recuperar meu link de pagamento
                        </a>
                    @endif

                    <a href="https://wa.me/5519999332615?text={{ rawurlencode('Olá! Fiz o cadastro no Oravel'.($empresa ? ' ('.$empresa.')' : '').' e preciso de ajuda para concluir o pagamento.') }}" target="_blank" rel="noopener"
                        class="mt-3 inline-block w-full rounded-full border border-white/40 px-6 py-3 text-sm font-semibold text-white transition hover:bg-white/10">
                        Falar com a equipe pelo WhatsApp
                    </a>
                </div>

                <p class="relative z-10 mt-8 text-center text-xs text-white/50">
                    © {{ now()->year }} Oravel. Todos os direitos reservados.
                </p>
            </div>
        </div>
    </body>
</html>
