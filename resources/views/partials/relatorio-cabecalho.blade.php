{{-- Cabeçalho padrão de relatório. Opcionais: $tituloRelatorio (nome em destaque) e $filtrosRelatorio (array|texto). --}}
@php
    $logoRelatorio = \App\Support\Relatorio::logo();
    $filtrosRel = \App\Support\Relatorio::filtros($filtrosRelatorio ?? null);
@endphp
<div style="border-bottom: 2px solid #111827; padding-bottom: 8px; margin-bottom: 14px; font-family: 'Helvetica', 'Arial', sans-serif;">
    <table style="width: 100%; border-collapse: collapse;">
        <tr>
            <td style="vertical-align: middle;">
                @if($logoRelatorio)
                    <img src="{{ $logoRelatorio }}" alt="" style="max-height: 48px; max-width: 180px; float: left; margin-right: 12px;">
                @endif
                <div style="font-size: 18px; font-weight: bold; color: #111827;">{{ \App\Support\Relatorio::emissor() }}</div>
                @if(\App\Support\Relatorio::detalhe())
                    <div style="font-size: 10px; color: #6b7280;">{{ \App\Support\Relatorio::detalhe() }}</div>
                @endif
            </td>
            <td style="vertical-align: middle; text-align: right; font-size: 10px; color: #374151; white-space: nowrap;">
                <div>{{ now()->format('d/m/Y') }} às {{ now()->format('H:i') }}</div>
                @if(\App\Support\Relatorio::usuario())
                    <div>Emitido por {{ \App\Support\Relatorio::usuario() }}</div>
                @endif
            </td>
        </tr>
    </table>
    @isset($tituloRelatorio)
        <div style="font-size: 22px; font-weight: 800; text-transform: uppercase; color: #111827; margin-top: 10px;">{{ $tituloRelatorio }}</div>
    @endisset
    @if(isset($tituloRelatorio) || isset($filtrosRelatorio))
        <div style="font-size: 10px; color: #374151; margin-top: 4px;">
            <strong>Filtros:</strong> {{ count($filtrosRel) ? implode(' · ', $filtrosRel) : 'nenhum (todos os registros visíveis)' }}
        </div>
    @endif
</div>
