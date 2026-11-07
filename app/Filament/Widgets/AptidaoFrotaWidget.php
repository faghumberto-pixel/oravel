<?php

namespace App\Filament\Widgets;

use App\Models\Asset;
use App\Services\Catalogo\Aptidao\AptidaoGate;
use App\Support\CatalogoAcesso;
use App\Support\Tenancy;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/** G1 -- frota apta x inapta + alerta de cadastro incompleto. So aparece com a chave do catalogo ligada e permissao de leitura. */
class AptidaoFrotaWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 5;

    protected ?string $heading = 'Aptidão da frota';

    public static function canView(): bool
    {
        return CatalogoAcesso::podeLer() && AptidaoGate::ligada(Tenancy::current()?->id ?? auth()->user()?->tenant_id);
    }

    protected function getStats(): array
    {
        $conta = fn (string $status) => Asset::query()->where('aptidao_status', $status)->count();
        $incompletos = $conta('cadastro_incompleto');

        return [
            Stat::make('Aptos', $conta('apto'))->color('success'),
            Stat::make('Apto com restrição', $conta('apto_restricao'))->color('warning'),
            Stat::make('Inaptos', $conta('inapto'))->color('danger'),
            Stat::make('Cadastro incompleto', $incompletos)->color($incompletos ? 'gray' : 'success')
                ->description($incompletos ? 'Ativos sem grupo de equipamento' : 'Todos os ativos têm grupo'),
        ];
    }
}
