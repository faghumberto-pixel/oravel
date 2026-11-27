{{-- Seções ou cláusulas e termos do modelo de documento da empresa. $doc vem de App\Support\ModeloDeDocumento::carregar(). --}}
@foreach ((array) ($doc['campos'] ?? []) as $campo)
    <div class="section" style="page-break-inside: avoid;">
        <div class="section-title">{{ $campo['titulo'] }}</div>
        @if ($campo['texto'] !== '')
            <div style="padding: 6px 4px; font-size: 11px; line-height: 1.5; white-space: pre-line;">{{ $campo['texto'] }}</div>
        @endif
    </div>
@endforeach
@if (! empty($doc['termos']))
    <div class="section">
        <div class="section-title">Termos gerais</div>
        <div style="padding: 6px 4px; font-size: 10px; line-height: 1.5; white-space: pre-line;">{{ $doc['termos'] }}</div>
    </div>
@endif
