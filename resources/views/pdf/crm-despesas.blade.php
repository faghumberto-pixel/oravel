<!doctype html>
<html lang="pt-BR"><head><meta charset="utf-8">
<style>
 body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #1a1a1a; }
 h1 { font-size: 15px; margin: 0; color: #1A3A52; } h2 { font-size: 12px; margin: 14px 0 4px; }
 .sub { color: #666; margin-bottom: 8px; }
 table { width: 100%; border-collapse: collapse; margin-bottom: 6px; }
 th { background: #1A3A52; color: #fff; text-align: left; padding: 3px 4px; }
 td { padding: 3px 4px; border-bottom: 1px solid #ddd; }
 .tot { text-align: right; font-weight: bold; }
 .aviso { color: #a00; font-weight: bold; }
</style></head><body>
<h1>Acerto de despesas de visita</h1>
<div class="sub">Período {{ \Illuminate\Support\Carbon::parse($de)->format('d/m/Y') }} a {{ \Illuminate\Support\Carbon::parse($ate)->format('d/m/Y') }} · gerado em {{ now()->format('d/m/Y H:i') }}</div>
@if ($total > $exibidas)
 <div class="aviso">Atenção: o PDF mostra só {{ $exibidas }} de {{ $total }} despesas (limite {{ $limite }}). Filtre por vendedor ou período, ou use a exportação para Excel.</div>
@endif
@forelse ($porVendedor as $vendedor => $itens)
 <h2>{{ $vendedor }}</h2>
 <table>
  <thead><tr><th>Data</th><th>Tipo</th><th>Descrição</th><th>Veículo</th><th>Status</th><th class="tot">Valor</th></tr></thead>
  <tbody>
  @foreach ($itens as $d)
   <tr><td>{{ $d->data->format('d/m/Y') }}</td><td>{{ \App\Models\CrmVisitaDespesa::tipoLabels()[$d->tipo] ?? $d->tipo }}</td><td>{{ $d->descricao }}</td><td>{{ $d->veiculo?->placa }}</td><td>{{ \App\Models\CrmVisitaDespesa::statusLabels()[$d->status] ?? $d->status }}</td><td class="tot">R$ {{ number_format($d->valor, 2, ',', '.') }}</td></tr>
  @endforeach
   <tr><td colspan="5" class="tot">Total de {{ $vendedor }}</td><td class="tot">R$ {{ number_format($itens->sum('valor'), 2, ',', '.') }}</td></tr>
  </tbody>
 </table>
@empty
 <p>Nenhuma despesa no período.</p>
@endforelse
@if ($porVendedor->isNotEmpty())
 <h2 class="tot">Total geral: R$ {{ number_format($porVendedor->flatten(1)->sum('valor'), 2, ',', '.') }}</h2>
@endif
</body></html>
