<!DOCTYPE html>
<html lang="pt-BR">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>Bem-vindo à Oravel</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />

        <script src="https://cdn.tailwindcss.com"></script>
    </head>
    <body class="font-sans antialiased">
        <div
            class="relative min-h-screen w-full overflow-hidden bg-slate-950"
            style="background-image: linear-gradient(to bottom right, rgba(0,0,0,0.8), rgba(0,0,0,0.55), rgba(67,20,7,0.3)), url('{{ asset('images/login-bg.jpg').'?v=3' }}'); background-size: cover; background-position: center;"
        >
            <div class="relative z-10 flex min-h-screen flex-col items-center justify-center px-4 py-12 sm:px-6">
                <a href="https://oravel.com.br" class="mb-6 flex items-center gap-3">
                    <span class="flex h-14 w-14 items-center justify-center rounded-full border border-white/30 bg-white/10">
                        <x-heroicon-o-bolt class="h-7 w-7 text-white" />
                    </span>
                    <span class="text-2xl font-bold text-white">Oravel</span>
                </a>

                <div class="w-full max-w-xl rounded-3xl border border-white/25 bg-white/15 p-6 shadow-2xl backdrop-blur-xl sm:p-10">
                    <div class="text-center">
                        <span class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-full border border-white/30 bg-white/10">
                            <x-heroicon-o-check-circle class="h-7 w-7 text-white" />
                        </span>
                        <h1 class="text-2xl font-bold text-white sm:text-3xl">Bem-vindo à Oravel!</h1>
                        <p class="mt-3 text-sm leading-relaxed text-white/80 sm:text-base">
                            Você acaba de dar um passo importante: menos papel, menos correria e mais controle
                            sobre a sua operação. Daqui para frente, cada ordem de serviço, cada contrato e
                            cada cliente fica num só lugar, e você enxerga tudo em tempo real.
                        </p>
                    </div>

                    <h2 class="mt-8 text-xs font-semibold uppercase tracking-widest text-white/60">Seus próximos passos</h2>
                    <ol class="mt-3 space-y-3">
                        <li class="flex gap-3 rounded-2xl border border-white/15 bg-white/10 p-4">
                            <span class="flex h-7 w-7 flex-none items-center justify-center rounded-full text-sm font-bold text-white" style="background-color:#ea580c">1</span>
                            <div class="min-w-0">
                                <p class="font-semibold text-white">Aguarde a confirmação do pagamento</p>
                                <p class="mt-1 text-sm text-white/70">Normalmente leva poucos minutos. Você recebe um e-mail assim que o seu acesso for liberado, sem precisar fazer nada por aqui.</p>
                            </div>
                        </li>
                        <li class="flex gap-3 rounded-2xl border border-white/15 bg-white/10 p-4">
                            <span class="flex h-7 w-7 flex-none items-center justify-center rounded-full text-sm font-bold text-white" style="background-color:#ea580c">2</span>
                            <div class="min-w-0">
                                <p class="font-semibold text-white">Entre no painel</p>
                                <p class="mt-1 text-sm text-white/70">Use o e-mail e a senha que você cadastrou ao assinar. Se ainda não conseguir entrar, a confirmação não chegou: tente de novo em alguns minutos.</p>
                            </div>
                        </li>
                        <li class="flex gap-3 rounded-2xl border border-white/15 bg-white/10 p-4">
                            <span class="flex h-7 w-7 flex-none items-center justify-center rounded-full text-sm font-bold text-white" style="background-color:#ea580c">3</span>
                            <div class="min-w-0">
                                <p class="font-semibold text-white">Siga os Primeiros passos</p>
                                <p class="mt-1 text-sm text-white/70">Ao entrar, o sistema mostra um guia curto, passo a passo, para você chegar ao primeiro resultado ainda hoje. Leva poucos minutos por etapa.</p>
                            </div>
                        </li>
                        <li class="flex gap-3 rounded-2xl border border-white/15 bg-white/10 p-4">
                            <span class="flex h-7 w-7 flex-none items-center justify-center rounded-full text-sm font-bold text-white" style="background-color:#ea580c">4</span>
                            <div class="min-w-0">
                                <p class="font-semibold text-white">Aprenda no seu ritmo</p>
                                <p class="mt-1 text-sm text-white/70">A Academia Oravel tem aulas curtas e provas para você e sua equipe dominarem cada módulo.</p>
                            </div>
                        </li>
                    </ol>

                    <div class="mt-8 flex flex-col gap-3 sm:flex-row">
                        <a href="{{ url('/admin/login') }}"
                            class="inline-block w-full rounded-full px-6 py-3 text-center text-sm font-bold uppercase tracking-wide text-white shadow-lg transition hover:opacity-90"
                            style="background-color: #ea580c;">
                            Entrar no painel
                        </a>
                        <a href="https://wa.me/5519999332615?text={{ rawurlencode('Olá! Acabei de assinar a Oravel e preciso de ajuda para começar.') }}"
                            class="inline-block w-full rounded-full border border-white/40 px-6 py-3 text-center text-sm font-bold uppercase tracking-wide text-white transition hover:bg-white/10">
                            Falar com a Oravel
                        </a>
                    </div>
                </div>

                <p class="relative z-10 mt-8 text-center text-xs text-white/50">
                    © {{ now()->year }} Oravel. Todos os direitos reservados.
                </p>
            </div>
        </div>
    </body>
</html>
