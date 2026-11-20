<?php

namespace App\Relacionamento\Filament\Widgets;

use App\Relacionamento\Models\RelContato;
use App\Relacionamento\Models\RelInteracao;
use App\Support\WebAnalytics;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/** Pessoas que se identificaram no ecossistema Oravel (Gazeta, materiais...). Só o período vale. */
class OrgRelacionamentoStats extends StatsOverviewWidget
{
    use InteractsWithPageFilters;

    protected static bool $isDiscovered = false;

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    protected function getStats(): array
    {
        $since = WebAnalytics::since($this->filters['period'] ?? '7');
        $aceitaram = RelContato::whereNotNull('consentimento_em')->whereNull('descadastrado_em')->count();

        return [
            Stat::make('Contatos identificados', RelContato::count())->description('Pessoas com e-mail ou telefone')->descriptionIcon('heroicon-m-user-group')->color('primary'),
            Stat::make('Aceitaram receber', $aceitaram)->description('Com consentimento registrado e sem descadastro')->descriptionIcon('heroicon-m-check-badge')->color('success'),
            Stat::make('Descadastrados', RelContato::whereNotNull('descadastrado_em')->count())->description('Não contatar')->descriptionIcon('heroicon-m-no-symbol')->color('gray'),
            Stat::make('Ações no período', RelInteracao::where('ocorrido_em', '>=', $since)->where('tipo', '!=', RelInteracao::TIPO_CONTATO_COMERCIAL)->count())->description('Ferramentas, materiais, Gazeta, e-mails...')->descriptionIcon('heroicon-m-bolt')->color('warning'),
        ];
    }
}
