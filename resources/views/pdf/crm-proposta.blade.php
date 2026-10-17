<!doctype html>
<html lang="pt-BR"><head><meta charset="utf-8">
<style>
 body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1a1a1a; }
 h1 { font-size: 18px; margin: 0 0 2px; color: #1A3A52; } .sub { color: #666; margin-bottom: 14px; }
 table { width: 100%; border-collapse: collapse; margin: 10px 0; }
 th { background: #1A3A52; color: #fff; text-align: left; padding: 5px; font-size: 10px; }
 td { padding: 5px; border-bottom: 1px solid #ddd; } .r { text-align: right; }
 .total { text-align: right; font-size: 15px; font-weight: bold; margin-top: 8px; }
 h3 { font-size: 12px; margin: 16px 0 3px; color: #1A3A52; } .txt { white-space: pre-line; }
</style></head><body>
<h1>{{ $proposta->tenant?->name }}</h1>
<div class="sub">{{ $proposta->titulo() }} · emitida em {{ now()->format('d/m/Y') }}@if($proposta->validade_ate) · válida até {{ $proposta->validade_ate->format('d/m/Y') }}@endif</div>
<p><b>Para:</b> {{ $proposta->destinatarioNome() }}@if($proposta->destinatarioEmail()) · {{ $proposta->destinatarioEmail() }}@endif</p>
<table>
 <thead><tr><th>Item</th><th class="r">Qtd.</th><th class="r">Unitário</th><th class="r">Subtotal</th></tr></thead>
 <tbody>
 @foreach ($proposta->itens as $item)
  <tr><td>{{ $item->descricao }}</td><td class="r">{{ number_format($item->quantidade, 2, ',', '.') }}</td><td class="r">R$ {{ number_format($item->preco_unitario, 2, ',', '.') }}</td><td class="r">R$ {{ number_format($item->subtotal, 2, ',', '.') }}</td></tr>
 @endforeach
 </tbody>
</table>
@if ((float) $proposta->desconto > 0)<div class="r">Desconto: − R$ {{ number_format($proposta->desconto, 2, ',', '.') }}</div>@endif
<div class="total">Total: R$ {{ number_format($proposta->total, 2, ',', '.') }}</div>
@if ($proposta->condicoes)<h3>Condições</h3><div class="txt">{{ $proposta->condicoes }}</div>@endif
@if ($proposta->observacoes)<h3>Observações</h3><div class="txt">{{ $proposta->observacoes }}</div>@endif
@if ($proposta->vendedor)<p style="margin-top:22px">Responsável: {{ $proposta->vendedor->name }} · {{ $proposta->vendedor->email }}</p>@endif
</body></html>
