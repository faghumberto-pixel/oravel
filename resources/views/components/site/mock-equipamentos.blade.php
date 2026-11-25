@props(['ex', 'rotulo', 'linhas' => 3])
@php use App\Support\IntelligenceSite\Exemplo as E; @endphp
<x-site.mock :rotulo="$rotulo" :aria="'Tela de exemplo de Equipamentos: '.$ex->acima60().' equipamentos já custam mais de 60% do valor em manutenção'">
    <h4>Equipamentos · {{ $ex->frota() }} na frota</h4>
    <table class="t">
        <thead><tr><th>Equipamento</th><th>Manutenção ÷ valor</th><th>Situação</th></tr></thead>
        <tbody>
        @foreach (array_slice($ex->equipamentos(), 0, $linhas) as $e)
            <tr>
                <td>{{ $e['nome'] }}<br><small>{{ $e['patrimonio'] }}</small></td>
                <td>{{ $e['pct'] }}%</td>
                <td><span class="tag {{ $e['pct'] >= 60 ? 'bad' : ($e['pct'] >= 40 ? 'warn' : 'ok') }}">{{ $e['situacao'] }}</span></td>
            </tr>
        @endforeach
        </tbody>
    </table>
</x-site.mock>
