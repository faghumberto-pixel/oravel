<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <meta name="theme-color" content="#0d1321">
    <title>{{ $asset->name }} - Status do Pátio - {{ config('app.name', 'Oravel') }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,600,800&display=swap" rel="stylesheet" />
    @vite(['resources/css/app.css'])
</head>
<body class="font-sans antialiased bg-zinc-950 text-zinc-100 overscroll-none">
    {{--
        Classes de cor precisam ser strings literais completas (nao
        interpoladas tipo bg-{{ $cor }}-500), senao o Tailwind JIT nao
        encontra no scan dos arquivos e a cor nunca aparece no CSS
        compilado -- mesmo bug ja visto antes com bg-slate-900 vs
        variavel de tema nesta sessao.
    --}}
    @php
        $statusMeta = [
            'disponivel' => ['Disponível', 'bg-emerald-500/15 text-emerald-400'],
            'locado' => ['Locado', 'bg-sky-500/15 text-sky-400'],
            'operando' => ['Em Operação', 'bg-sky-500/15 text-sky-400'],
            'manutencao' => ['Em Manutenção', 'bg-amber-500/15 text-amber-400'],
            'aguardando_triagem' => ['Aguardando Triagem', 'bg-amber-500/15 text-amber-400'],
            'quarentena' => ['Quarentena', 'bg-red-500/15 text-red-400'],
        ];
        [$statusLabel, $statusClasses] = $statusMeta[$asset->status] ?? [$asset->status, 'bg-zinc-500/15 text-zinc-400'];
        $lastOrder = $asset->maintenanceOrders()->latest('created_at')->first();
    @endphp
    <div class="mx-auto flex min-h-screen max-w-md flex-col items-center justify-center px-8 text-center">
        <p class="text-xs font-bold uppercase tracking-widest text-zinc-500">Patrimônio {{ $asset->patrimonio ?? '—' }}</p>
        <p class="mt-1 text-2xl font-extrabold text-white">{{ $asset->name }}</p>

        <span class="mt-6 inline-flex items-center rounded-full px-4 py-1.5 text-sm font-bold {{ $statusClasses }}">
            {{ $statusLabel }}
        </span>

        @if($asset->status === 'locado' && $asset->client)
            <p class="mt-4 text-sm text-zinc-400">Locado para <span class="font-semibold text-zinc-200">{{ $asset->client->name }}</span></p>
        @endif

        <div class="mt-8 w-full rounded-xl border border-zinc-800 bg-zinc-900 p-4 text-left">
            <p class="text-[11px] font-bold uppercase tracking-wide text-zinc-500">Última O.S.</p>
            @if($lastOrder)
                <p class="mt-1 text-sm text-zinc-200">Nº {{ $lastOrder->os_number }} — {{ $lastOrder->status }}</p>
                <p class="mt-0.5 text-xs text-zinc-500">{{ $lastOrder->created_at->format('d/m/Y H:i') }}</p>
            @else
                <p class="mt-1 text-sm text-zinc-500">Nenhuma O.S. registrada ainda.</p>
            @endif
        </div>

        <button onclick="window.location.reload()" class="mt-10 min-h-[2.75rem] rounded-xl border border-zinc-700 bg-zinc-900 px-6 text-xs font-bold tracking-wide text-zinc-300">
            ATUALIZAR
        </button>
    </div>
</body>
</html>
