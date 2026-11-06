<?php

namespace App\Filament\Resources\StockCountResource\Widgets;

use App\Services\Estoque\EstoqueUnificado;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Model;

/** Resumo da conferência: quanto já foi contado, quantas divergências e quanto isso vale em dinheiro (sobra e falta). */
class ConferenciaDaContagem extends StatsOverviewWidget
{
    public ?Model $record = null;

    protected function getStats(): array
    {
        $itens = $this->record->items()->get();
        $custos = app(EstoqueUnificado::class)->todos()->mapWithKeys(fn ($l) => [$l['tipo'].'|'.$l['id'] => (float) $l['custo']]);

        $contados = $itens->whereNotNull('counted_quantity');
        $divergentes = $contados->filter(fn ($i) => abs((float) $i->diferenca()) >= 0.001);
        $valor = fn ($sinal) => $divergentes->filter(fn ($i) => $sinal * (float) $i->diferenca() > 0)->sum(fn ($i) => abs((float) $i->diferenca()) * ($custos[$i->item_type.'|'.$i->item_id] ?? 0));
        $pct = $itens->count() > 0 ? (int) round($contados->count() / $itens->count() * 100) : 0;
        $brl = fn ($v) => 'R$ '.number_format($v, 2, ',', '.');

        return [
            Stat::make('Itens contados', $contados->count().' de '.$itens->count())->description($pct.'% da folha')->color($pct === 100 ? 'success' : 'warning'),
            Stat::make('Divergências', $divergentes->count())->description($contados->count() > 0 ? round($divergentes->count() / max(1, $contados->count()) * 100).'% dos contados' : 'nada contado ainda')->color($divergentes->count() > 0 ? 'danger' : 'success'),
            Stat::make('Falta no estoque', $brl($valor(-1)))->description('contou menos que o sistema')->color('danger'),
            Stat::make('Sobra no estoque', $brl($valor(1)))->description('contou mais que o sistema')->color('warning'),
        ];
    }
}
