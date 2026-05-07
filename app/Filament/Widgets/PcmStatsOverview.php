<?php

namespace App\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class PcmStatsOverview extends BaseWidget
{
    // Garante que ocupe a linha inteira, alinhado com o design do PDF
    protected int | string | array $columnSpan = 'full';

    protected function getStats(): array
    {
        // Aqui estamos usando os dados do seu PDF. 
        // Futuramente, você pode substituir por consultas reais ao banco de dados.
        return [
            Stat::make('MTBF (Tempo Médio Entre Falhas)', '1.200 horas')
                ->description('Alta confiabilidade do maquinário atual')
                ->descriptionIcon('heroicon-m-clock')
                ->color('success'),
                
            Stat::make('MTTR (Tempo Médio para Reparo)', '4.5 horas')
                ->description('Equipe técnica operando dentro da meta')
                ->descriptionIcon('heroicon-m-wrench-screwdriver')
                ->color('warning'),
                
            Stat::make('Preventiva vs Corretiva', '80% / 20%')
                ->description('Foco em preventiva reduz quebras')
                ->descriptionIcon('heroicon-m-shield-check')
                ->color('primary'),
        ];
    }
}
