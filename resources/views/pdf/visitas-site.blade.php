<!doctype html>
<html lang="pt-BR"><head><meta charset="utf-8">
<style>
 @page { margin: 18px; }
 body { font-family: DejaVu Sans, sans-serif; font-size: 6.5px; color: #1a1a1a; }
 h1 { font-size: 13px; margin: 0 0 2px; color: #1A3A52; }
 .sub { color: #666; margin-bottom: 8px; font-size: 8px; }
 table { width: 100%; border-collapse: collapse; }
 th { background: #1A3A52; color: #fff; text-align: left; padding: 3px; font-size: 6.5px; }
 td { padding: 2px 3px; border-bottom: 1px solid #ddd; vertical-align: top; word-wrap: break-word; }
 tr.det td { background: #f4f6f8; color: #444; border-bottom: 1px solid #bbb; }
 .aviso { color: #a00; font-weight: bold; margin-bottom: 6px; }
</style></head><body>
<h1>Visitas do Site — oravel.com.br</h1>
<div class="sub">Gerado em {{ now()->format('d/m/Y H:i') }} · {{ $visitas->count() }} de {{ $total }} visita(s)</div>
@if ($total > $visitas->count())
 <div class="aviso">Atenção: o PDF mostra só as {{ $visitas->count() }} visitas mais recentes de {{ $total }}. Use o Excel para a lista completa ou filtre o período.</div>
@endif
<table>
<thead><tr>
 <th>Início</th><th>Última ativ.</th><th>Origem</th><th>Campanha</th><th>Entrada</th><th>Págs</th><th>Tempo</th><th>Voltou</th>
 <th>Dispositivo</th><th>Navegador</th><th>Sistema</th><th>IP</th><th>Cidade/UF</th>
</tr></thead>
<tbody>
@foreach ($visitas as $v)
 <tr>
  <td>{{ $v->started_at?->format('d/m/Y H:i') }}</td>
  <td>{{ $v->last_activity_at?->format('d/m/Y H:i') }}</td>
  <td>{{ $v->sourceLabel() }}</td><td>{{ $v->utm_campaign }}</td><td>{{ $v->landing_path }}</td>
  <td>{{ $v->page_views }}</td><td>{{ \App\Support\WebAnalytics::duration($v->duration_seconds) }}</td>
  <td>{{ $v->is_returning ? 'Sim' : 'Não' }}</td>
  <td>{{ match ($v->device_type) { 'mobile' => 'Celular', 'tablet' => 'Tablet', default => 'Desktop' } }}</td>
  <td>{{ $v->browser }}</td><td>{{ $v->os }}</td><td>{{ $v->ip_address }}</td>
  <td>{{ $v->city ? $v->city.'/'.$v->state : '—' }}</td>
 </tr>
 <tr class="det"><td colspan="13">
  <b>Veio de:</b> {{ $v->referrer_url ?: 'Acesso direto' }}
  · <b>UTM:</b> {{ collect([$v->utm_source, $v->utm_medium, $v->utm_term, $v->utm_content])->filter()->implode(' / ') ?: '—' }}
  · <b>Visitante:</b> {{ $v->visitor_token }} · <b>Sessão:</b> {{ $v->session_token }}<br>
  <b>User agent:</b> {{ $v->user_agent }}<br>
  <b>Jornada:</b> @forelse ($v->pageviews as $p){{ $p->entered_at?->format('H:i:s') }} {{ $p->path }} ({{ \App\Support\WebAnalytics::duration($p->active_seconds) }}, {{ $p->max_scroll }}%){{ ! $loop->last ? ' → ' : '' }}@empty — @endforelse
  @if ($v->events->isNotEmpty())<br><b>Cliques:</b> {{ $v->events->map(fn ($e) => $e->occurred_at?->format('H:i:s').' '.$e->label)->implode(' | ') }}@endif
 </td></tr>
@endforeach
</tbody></table>
</body></html>
