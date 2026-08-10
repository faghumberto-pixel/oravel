<?php

namespace App\Filament\Central\Resources\SalesLeadResource\Widgets;

use App\Filament\Central\Resources\SalesLeadResource;
use App\Models\SalesLead;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Contagem simples pra quem acabou de abrir a lista -- SalesCrmStatsWidget
 * (Dashboard CRM) ja cobre metrica de funil/conversao, esta aqui responde
 * "quantos leads eu tenho e quantos ainda faltam trabalhar", sem duplicar.
 * Cada card e' clicavel e leva pra listagem ja filtrada (pedido do usuario
 * 2026-08-10: card deve linkar pro dado real no banco, nao so decorativo).
 */
class SalesLeadListStats extends BaseWidget
{
    protected function getStats(): array
    {
        $total = SalesLead::count();
        $abertos = SalesLead::whereNotIn('pipeline_stage', [SalesLead::STAGE_GANHO, SalesLead::STAGE_PERDIDO])->count();
        $comContato = SalesLead::where(fn ($q) => $q->whereNotNull('phone')->orWhereNotNull('email'))->count();
        $semContato = $total - $comContato;

        return [
            Stat::make('Total de Leads', $total)
                ->description('Cadastrados no funil')
                ->descriptionIcon('heroicon-m-users')
                ->color('gray')
                ->url(SalesLeadResource::getUrl('index')),

            Stat::make('Em Aberto', $abertos)
                ->description('Ainda não fechados (ganho/perdido)')
                ->descriptionIcon('heroicon-m-arrow-path')
                ->color('primary')
                // SelectFilter so' aceita 1 valor -- "aberto" e' 4 estagios
                // diferentes (todos exceto ganho/perdido), sem filtro que
                // represente isso direito. Leva pra listagem geral (ja
                // ordenada por padrao) em vez de um filtro que mentiria.
                ->url(SalesLeadResource::getUrl('index')),

            Stat::make('Com Telefone ou E-mail', $comContato)
                ->description('Prontos para abordagem direta')
                ->descriptionIcon('heroicon-m-phone')
                ->color('success')
                ->url(SalesLeadResource::getUrl('index', ['tableFilters[has_contact][value]' => '1'])),

            Stat::make('Sem Contato Direto', $semContato)
                ->description('Falta telefone e e-mail')
                ->descriptionIcon($semContato > 0 ? 'heroicon-m-exclamation-triangle' : 'heroicon-m-check-circle')
                ->color($semContato > 0 ? 'warning' : 'success')
                ->url(SalesLeadResource::getUrl('index', ['tableFilters[has_contact][value]' => '0'])),
        ];
    }
}
