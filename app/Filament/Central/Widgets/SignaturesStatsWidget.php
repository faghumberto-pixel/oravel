<?php

namespace App\Filament\Central\Widgets;

use App\Models\Signature;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;

class SignaturesStatsWidget extends BaseWidget
{
    protected function getStats(): array
    {
        $thisMonth = Signature::whereMonth('signed_at', Carbon::now()->month)
            ->whereYear('signed_at', Carbon::now()->year)
            ->count();

        $allTime = Signature::count();

        $emailsSent = Signature::where('email_sent', true)->count();

        $emailsPercentage = $allTime > 0 ? round(($emailsSent / $allTime) * 100) : 0;

        $thisMonthTrend = $thisMonth > 0 ? '+' . $thisMonth : '0';

        return [
            Stat::make('Assinaturas Este Mês', $thisMonth)
                ->description('Novas assinaturas SLA + LGPD')
                ->icon('heroicon-o-document-check')
                ->color('success')
                ->descriptionIcon('heroicon-o-calendar')
                ->chart([1, 2, 3, 4, 5, $thisMonth]),

            Stat::make('Total de Assinaturas', $allTime)
                ->description('Todas as assinaturas de todos os tempos')
                ->icon('heroicon-o-inbox-stack')
                ->color('primary')
                ->descriptionIcon('heroicon-o-arrow-trending-up'),

            Stat::make('Emails Enviados', "{$emailsPercentage}%")
                ->description("{$emailsSent} de {$allTime} confirmadas")
                ->icon('heroicon-o-envelope')
                ->color($emailsPercentage === 100 ? 'success' : 'warning')
                ->descriptionIcon('heroicon-o-check-circle'),
        ];
    }
}
