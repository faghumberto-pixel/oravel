<!DOCTYPE html>
<html lang="pt-BR">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>Acesso temporariamente bloqueado -- Oravel</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />

        <script src="https://cdn.tailwindcss.com"></script>
    </head>
    <body class="font-sans antialiased">
        @php
            $isCancelado = $tenant?->asaas_payment_status === \App\Models\Tenant::PAYMENT_STATUS_CANCELADO;
            $diasAtraso = $tenant?->asaas_overdue_since?->diffInDays(now());
        @endphp
        <div
            class="relative min-h-screen w-full overflow-hidden bg-slate-950"
            style="background-image: linear-gradient(to bottom right, rgba(0,0,0,0.8), rgba(0,0,0,0.55), rgba(67,20,7,0.3)), url('{{ asset('images/login-bg.jpg') }}'); background-size: cover; background-position: center;"
        >
            <div class="relative z-10 flex min-h-screen flex-col items-center justify-center px-6 py-12">
                <span class="mb-6 flex items-center gap-3">
                    <span class="flex h-14 w-14 items-center justify-center rounded-full border border-white/30 bg-white/10">
                        <x-heroicon-o-lock-closed class="h-7 w-7 text-white" />
                    </span>
                    <span class="text-2xl font-bold text-white">Oravel</span>
                </span>

                <div class="w-full max-w-md rounded-3xl border border-white/25 bg-white/15 p-8 text-center shadow-2xl backdrop-blur-xl sm:p-10">
                    <h1 class="text-xl font-bold text-white">Acesso temporariamente bloqueado</h1>

                    @if ($isCancelado)
                        <p class="mt-3 text-sm text-white/75">
                            A assinatura da <strong>{{ $tenant->name }}</strong> foi cancelada. Pra voltar a
                            usar o Oravel, é preciso reativar a assinatura.
                        </p>
                    @else
                        <p class="mt-3 text-sm text-white/75">
                            A fatura da assinatura da <strong>{{ $tenant->name }}</strong> está em aberto
                            @if ($diasAtraso)
                                há {{ $diasAtraso }} {{ $diasAtraso === 1 ? 'dia' : 'dias' }}
                            @endif
                            e o acesso foi pausado. Assim que o pagamento for confirmado, o acesso volta
                            automaticamente -- normalmente em poucos minutos.
                        </p>
                    @endif

                    @if ($tenant?->asaas_current_invoice_url)
                        <a href="{{ $tenant->asaas_current_invoice_url }}" target="_blank" rel="noopener"
                            class="mt-6 inline-block w-full rounded-full px-6 py-3 text-sm font-bold uppercase tracking-wide text-white shadow-lg transition hover:opacity-90"
                            style="background-color: #ea580c;">
                            Pagar fatura em aberto
                        </a>
                    @else
                        <p class="mt-6 text-xs text-white/60">
                            Entre em contato com o suporte da Oravel pra regularizar sua assinatura.
                        </p>
                    @endif

                    <form method="POST" action="{{ route('filament.admin.auth.logout') }}" class="mt-4">
                        @csrf
                        <button type="submit" class="text-xs font-semibold text-white/60 underline hover:text-white">
                            Sair
                        </button>
                    </form>
                </div>

                <p class="relative z-10 mt-8 text-center text-xs text-white/50">
                    © {{ now()->year }} Oravel. Todos os direitos reservados.
                </p>
            </div>
        </div>
    </body>
</html>
