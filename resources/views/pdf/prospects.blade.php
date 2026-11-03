<!doctype html>
<html lang="pt-BR"><head><meta charset="utf-8">
<style>
 @page { margin: 22px; }
 body { font-family: DejaVu Sans, sans-serif; font-size: 8px; color: #1a1a1a; }
 h1 { font-size: 14px; margin: 0 0 2px; color: #1A3A52; }
 .sub { color: #666; margin-bottom: 8px; font-size: 8px; }
 table { width: 100%; border-collapse: collapse; }
 th { background: #1A3A52; color: #fff; text-align: left; padding: 3px; }
 td { padding: 3px; border-bottom: 1px solid #ddd; vertical-align: top; }
 .aviso { color: #a00; font-weight: bold; margin-bottom: 6px; }
</style></head><body>
<h1>Prospects — Oravel</h1>
<div class="sub">Gerado em {{ now()->format('d/m/Y H:i') }} · {{ $prospects->count() }} de {{ $total }} empresa(s)@if ($descricao) · Filtros: {{ $descricao }}@endif</div>
@if ($total > $prospects->count())
 <div class="aviso">Atenção: o relatório mostra só as primeiras {{ $prospects->count() }} de {{ $total }} empresas. Aplique mais filtros para a lista completa.</div>
@endif
<table>
<thead><tr><th>Empresa</th><th>Cidade / porte</th><th>Atividade</th><th>Aberta</th><th>Nota</th><th>Telefone</th><th>Sócio</th><th>Situação</th><th>Estágio / resultado</th><th>Próx. contato</th></tr></thead>
<tbody>
@foreach ($prospects as $p)
 <tr>
  <td><b>{{ $p->nome }}</b><br>{{ $p->cnpj }}</td>
  <td>{{ $p->cidade }}<br>{{ $p->porte }}</td>
  <td>{{ $p->atividade }}</td>
  <td>{{ $p->ano_abertura }}</td>
  <td>{{ $p->nota }}</td>
  <td>{{ $p->telefone }}<br>{{ $p->email }}</td>
  <td>{{ $p->socio }}</td>
  <td>{{ \App\Models\Prospect::STATUS[$p->status] ?? $p->status }}</td>
  <td>{{ \App\Models\Prospect::RESULTADOS[$p->resultado] ?? '' }}</td>
  <td>{{ $p->proximo_contato?->format('d/m/Y') }}</td>
 </tr>
@endforeach
</tbody>
</table>
</body></html>
