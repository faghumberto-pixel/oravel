@php
    $labels = [
        'mao_de_obra' => 'Mão de obra especializada',
        'seguranca_documentacao' => 'Segurança e documentação',
        'acessorio' => 'Acessórios e componentes',
        'insumo' => 'Insumos e consumíveis',
    ];
    $brl = fn (float $v) => 'R$ '.number_format($v, 2, ',', '.');
@endphp
<div class="space-y-4 text-sm">
    <table class="w-full">
        <tbody>
            @foreach ($summary['lines'] as $key => $valor)
                <tr class="border-b border-gray-200 dark:border-white/10">
                    <td class="py-2">{{ $labels[$key] }}</td>
                    <td class="py-2 text-right tabular-nums">{{ $brl($valor) }}</td>
                </tr>
            @endforeach
            <tr class="font-semibold">
                <td class="py-2">Custo total</td>
                <td class="py-2 text-right tabular-nums">{{ $brl($summary['cost']) }}</td>
            </tr>
            <tr>
                <td class="py-2">Faturado (Contas a Receber)</td>
                <td class="py-2 text-right tabular-nums">{{ $brl($summary['billed']) }}</td>
            </tr>
            <tr class="font-semibold">
                <td class="py-2">Margem</td>
                <td class="py-2 text-right tabular-nums {{ $summary['margin'] < 0 ? 'text-danger-600' : 'text-success-600' }}">
                    {{ $brl($summary['margin']) }}@if ($summary['margin_pct'] !== null) ({{ number_format($summary['margin_pct'], 1, ',', '.') }}%)@endif
                </td>
            </tr>
        </tbody>
    </table>
    <p class="text-xs text-gray-500">Segurança e documentação ainda não tem custo cadastrado. Acessórios e insumos contam pela quantidade vezes o custo unitário da saída; itens devolvidos em bom estado não contam.</p>
</div>
