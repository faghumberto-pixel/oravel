@props(['card'])
{{-- Card clicavel de filtro (padrao da Central): fundo suave na cor do significado; ativo = borda forte + anel. --}}
<button
    type="button"
    wire:click="filtrarCard('{{ $card['chave'] }}')"
    @class([
        'rounded-xl border p-3 text-left transition focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500',
        isset($card['cor']) ? \App\Support\CrmPalette::fundoCartao($card['cor']) : $card['paleta']['soft'],
        $card['paleta']['border'].' ring-2 '.$card['paleta']['ring'] => $card['ativo'],
        'border-transparent' => ! $card['ativo'],
    ])
>
    <div class="text-xs {{ $card['paleta']['text'] }}">{{ $card['rotulo'] }}</div>
    <div class="text-2xl font-semibold text-gray-950 dark:text-white">{{ number_format($card['total'], 0, ',', '.') }}</div>
</button>
