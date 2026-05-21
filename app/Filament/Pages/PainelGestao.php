<?php

namespace App\Filament\Pages;

use Filament\Pages\Dashboard as BaseDashboard; // 🔒 Extensão correta para o Core do Filament reconhecer o componente
use App\Filament\Widgets\StatsOverview;
use App\Filament\Widgets\PcmStatsOverview;
use App\Filament\Widgets\AssetStatusChart;
use App\Filament\Widgets\MaintenanceTrendsChart;
use App\Filament\Widgets\AssetDistributionChart;
use App\Filament\Widgets\AssetMapWidget;

class PainelGestao extends BaseDashboard
{
    protected static ?string $navigationIcon = 'heroicon-o-home';
    
    // 🔗 VÍNCULO FÍSICO CORRETO: Aponta exatamente para o seu arquivo blade na pasta filament/pages/
    protected static string $view = 'filament.pages.painel-gestao'; 
    
    protected static ?string $navigationLabel = 'Painel de Controle';
    protected static ?string $title = 'Painel de Controle';
    protected static ?string $slug = 'dashboard';

    /**
     * Trava de Segurança Suprema: Controla quem enxerga esta página na barra lateral esquerda.
     * Some por completo para o Bruno (Técnico) inclusive se ele estiver na tela de Chat.
     */
    public static function shouldRegisterNavigation(): bool
    {
        $user = auth()->user();
        
        if (!$user) {
            return false;
        }

        // Se for você (Admin mestre), o Dashboard aparece perfeito
        if (method_exists($user, 'isAdmin') && $user->isAdmin()) {
            return true;
        }

        // Se for Gestor da empresa contratante, também vê
        if ($user->hasRole('gestor')) {
            return true;
        }

        // Para funções puramente operacionais como a do Bruno, DESAPARECE tudo
        return false;
    }

    public function getHeaderWidgets(): array
    {
        return [
            StatsOverview::class,           // Resumo da Operação (Ocupa linha toda)
            PcmStatsOverview::class,        // Saúde da Frota (PCM) (Ocupa linha toda)
            AssetStatusChart::class,        // Status Donut (Vai ocupar 1 coluna)
            MaintenanceTrendsChart::class,  // Tendências de Custos (Vai ocupar 2 colunas)
            AssetDistributionChart::class,  // Faturamento vs Custos (Ocupa linha toda)
            AssetMapWidget::class,          // Mapa de Ativos (Ocupa linha toda)
        ];
    }

    // Define a grade da página para 3 colunas
    public function getColumns(): int | string | array
    {
        return 3;
    }

    // Garante que os widgets do cabeçalho também respeitem as 3 colunas
    public function getHeaderWidgetsColumns(): int | array
    {
        return 3;
    }
}