<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $quote ? 'Orçamento' : 'Link inválido' }} — Oravel</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>[x-cloak] { display: none !important; }</style>
</head>
<body class="min-h-screen bg-gray-100 dark:bg-gray-950 font-sans antialiased">
    <div class="mx-auto max-w-2xl px-4 py-10">
        <div class="mb-6 flex items-center gap-2">
            <span class="text-2xl font-black text-gray-900 dark:text-white">O<span style="color:#E8541A">r</span>avel</span>
        </div>

        @if(! $quote)
            <div class="rounded-xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 p-8 text-center">
                <p class="text-lg font-bold text-gray-900 dark:text-white">Link inválido</p>
                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Este link de orçamento não existe ou já expirou.</p>
            </div>
        @else
            <div class="rounded-xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 overflow-hidden">
                <div class="px-6 py-5 border-b border-gray-100 dark:border-gray-800 flex items-center justify-between">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-wide text-gray-400 dark:text-gray-500">Orçamento pra</p>
                        <p class="text-lg font-black text-gray-900 dark:text-white">{{ $quote->client->name }}</p>
                    </div>
                    <span class="text-[10px] font-black uppercase tracking-wide px-3 py-1 rounded-full
                        @class([
                            'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300' => $quote->status === \App\Models\Quote::STATUS_ENVIADO,
                            'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300' => in_array($quote->status, [\App\Models\Quote::STATUS_APROVADO, \App\Models\Quote::STATUS_CONCLUIDO]),
                            'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300' => $quote->status === \App\Models\Quote::STATUS_REPROVADO,
                            'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400' => $quote->status === \App\Models\Quote::STATUS_RASCUNHO,
                        ])">
                        {{ \App\Models\Quote::statusLabels()[$quote->status] ?? $quote->status }}
                    </span>
                </div>

                <div class="px-6 py-5">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-left text-[10px] uppercase tracking-wide text-gray-400 dark:text-gray-500 border-b border-gray-100 dark:border-gray-800">
                                <th class="pb-2 font-bold">Item</th>
                                <th class="pb-2 font-bold text-right">Qtd.</th>
                                <th class="pb-2 font-bold text-right">Valor</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($quote->items as $item)
                                <tr class="border-b border-gray-50 dark:border-gray-800/60">
                                    <td class="py-2.5 text-gray-800 dark:text-gray-200">{{ $item->description }}</td>
                                    <td class="py-2.5 text-right text-gray-500 dark:text-gray-400">{{ number_format($item->quantity, 2, ',', '.') }}</td>
                                    <td class="py-2.5 text-right text-gray-800 dark:text-gray-200">R$ {{ number_format($item->subtotal, 2, ',', '.') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>

                    <div class="mt-4 flex justify-end">
                        <p class="text-lg font-black text-gray-900 dark:text-white">
                            Total: <span style="color:#E8541A">R$ {{ number_format($quote->total_value, 2, ',', '.') }}</span>
                        </p>
                    </div>

                    <div class="mt-4">
                        <a href="{{ route('quotes.pdf', $quote) }}" target="_blank" class="text-xs font-bold text-gray-500 dark:text-gray-400 underline">
                            Baixar PDF completo
                        </a>
                    </div>
                </div>

                <div class="px-6 py-5 border-t border-gray-100 dark:border-gray-800 bg-gray-50 dark:bg-gray-800/40">
                    @if($quote->status === \App\Models\Quote::STATUS_ENVIADO)
                        <div x-data="{ showReject: false }">
                            <div class="flex gap-3" x-show="!showReject">
                                <form method="POST" action="{{ route('quotes.public-approve', $quote->approval_token) }}" class="flex-1">
                                    @csrf
                                    <button type="submit" class="w-full rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white text-sm font-bold py-2.5">
                                        Aprovar Orçamento
                                    </button>
                                </form>
                                <button type="button" x-on:click="showReject = true" class="flex-1 rounded-lg border border-red-300 dark:border-red-800 text-red-600 dark:text-red-400 text-sm font-bold py-2.5">
                                    Reprovar
                                </button>
                            </div>
                            <form method="POST" action="{{ route('quotes.public-reject', $quote->approval_token) }}" x-show="showReject" x-cloak>
                                @csrf
                                <label class="block text-xs font-bold uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-1.5">Motivo da reprovação</label>
                                <textarea name="reason" required rows="3" class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-white text-sm mb-3"></textarea>
                                <div class="flex gap-3">
                                    <button type="submit" class="flex-1 rounded-lg bg-red-600 hover:bg-red-500 text-white text-sm font-bold py-2.5">
                                        Confirmar Reprovação
                                    </button>
                                    <button type="button" x-on:click="showReject = false" class="flex-1 rounded-lg border border-gray-300 dark:border-gray-700 text-gray-600 dark:text-gray-300 text-sm font-bold py-2.5">
                                        Voltar
                                    </button>
                                </div>
                            </form>
                        </div>
                    @elseif($quote->status === \App\Models\Quote::STATUS_APROVADO || $quote->status === \App\Models\Quote::STATUS_CONCLUIDO)
                        <p class="text-sm font-bold text-emerald-600 dark:text-emerald-400">
                            ✓ Orçamento aprovado em {{ $quote->client_responded_at?->format('d/m/Y \à\s H:i') }}.
                        </p>
                    @elseif($quote->status === \App\Models\Quote::STATUS_REPROVADO)
                        <p class="text-sm font-bold text-red-600 dark:text-red-400">Orçamento reprovado.</p>
                        @if($quote->rejection_reason)
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Motivo: {{ $quote->rejection_reason }}</p>
                        @endif
                    @else
                        <p class="text-sm text-gray-500 dark:text-gray-400">Este orçamento ainda está sendo preparado.</p>
                    @endif
                </div>
            </div>
        @endif
    </div>
</body>
</html>
