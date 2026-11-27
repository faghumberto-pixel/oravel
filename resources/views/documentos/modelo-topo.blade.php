{{-- Logo e cabeçalho do modelo de documento da empresa. $doc vem de App\Support\ModeloDeDocumento::carregar(). --}}
@if (! empty($doc['logo']))
    <img src="{{ $doc['logo'] }}" alt="" style="max-height: 54px; max-width: 220px; display: block; margin-bottom: 6px;">
@endif
@if (! empty($doc['cabecalho']))
    <div style="font-size: 10px; color: #4b5563; line-height: 1.4; white-space: pre-line;">{{ $doc['cabecalho'] }}</div>
@endif
