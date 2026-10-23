<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <meta name="theme-color" content="{{ $liberado ? '#059669' : '#dc2626' }}">
    <title>Verificação de Portaria - {{ config('app.name', 'Oravel') }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,600,800&display=swap" rel="stylesheet" />
    @vite(['resources/css/app.css'])
</head>
{{-- Mobile-first (max-w-md) -- em telas largas o body vira moldura neutra
     e o conteudo fica centralizado com formato de celular, em vez de
     esticar. --}}
<body class="font-sans antialiased bg-zinc-50 text-zinc-900 overscroll-none md:bg-white md:flex md:min-h-screen md:items-center md:justify-center md:py-6">
    <div class="mx-auto flex min-h-screen max-w-md flex-col items-center justify-center px-8 text-center md:h-[844px] md:min-h-0 md:w-[390px] md:overflow-y-auto md:rounded-[2rem] md:border md:border-zinc-200 md:shadow-2xl">
        @if(! $movement)
            <p class="text-2xl font-extrabold text-zinc-600">QR CODE INVÁLIDO</p>
            <p class="mt-2 text-sm text-zinc-600">Nenhuma movimentação encontrada para este código.</p>
        @elseif($liberado)
            <div class="flex h-24 w-24 items-center justify-center rounded-full bg-emerald-100 text-emerald-700">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" class="h-12 w-12">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                </svg>
            </div>
            <p class="mt-6 text-2xl font-extrabold text-emerald-700">LIBERADO PARA SAÍDA</p>
            <p class="mt-2 text-sm text-zinc-600">{{ strtoupper($movement->asset?->name ?? 'Equipamento') }}</p>
            <p class="mt-1 text-xs text-zinc-600">
                OK técnico dado por {{ $movement->approvedBy?->name ?? '—' }} em {{ $movement->approved_at?->format('d/m/Y H:i') }}
            </p>
        @else
            <div class="flex h-24 w-24 items-center justify-center rounded-full bg-red-100 text-red-600">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" class="h-12 w-12">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </div>
            <p class="mt-6 text-2xl font-extrabold text-red-600">AGUARDANDO LIBERAÇÃO TÉCNICA</p>
            <p class="mt-2 text-sm text-zinc-600">{{ strtoupper($movement->asset?->name ?? 'Equipamento') }}</p>
            <p class="mt-1 text-xs text-zinc-600">Este equipamento ainda não recebeu o OK técnico do pátio. Não libere a saída.</p>
        @endif

        <button onclick="window.location.reload()" class="mt-10 min-h-[2.75rem] rounded-xl border border-zinc-300 bg-white px-6 text-xs font-bold tracking-wide text-zinc-700">
            ATUALIZAR
        </button>
    </div>
</body>
</html>
