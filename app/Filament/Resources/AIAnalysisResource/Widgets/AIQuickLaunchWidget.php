<?php

namespace App\Filament\Resources\AIAnalysisResource\Widgets;

use App\Filament\Pages\AnaliseEstoque;
use App\Filament\Pages\CrmFunil;
use App\Filament\Pages\OtimizacaoRotas;
use App\Filament\Resources\EquipmentDamageResource;
use App\Support\Tenancy;
use Filament\Widgets\Widget;

/**
 * Atalhos por area na entrada da Central de IA -- pedido do usuario
 * 2026-07-26 depois de relatar que nao encontrava a IA do Comercial
 * (o botao existia, mas escondido dentro de um formulario secundario).
 */
class AIQuickLaunchWidget extends Widget
{
    protected static string $view = 'filament.widgets.ai-quick-launch';

    protected int|string|array $columnSpan = 'full';

    public function getAreas(): array
    {
        $hasFeature = (bool) Tenancy::current()?->hasFeature('ia_diagnostico_avarias');

        return [
            [
                'label' => 'Diagnóstico de Avarias',
                'description' => 'Analise uma avaria reportada e receba causa provável, ações corretivas, peças e custo estimado.',
                'icon' => 'heroicon-o-wrench-screwdriver',
                'color' => '#dc2626',
                'url' => EquipmentDamageResource::getUrl(),
                'visible' => $hasFeature,
            ],
            [
                'label' => 'Otimização de Rotas',
                'description' => 'Analise as mobilizações do dia por veículo e receba a sequência de paradas mais eficiente.',
                'icon' => 'heroicon-o-map',
                'color' => '#3b82f6',
                'url' => OtimizacaoRotas::getUrl(),
                'visible' => $hasFeature,
            ],
            [
                'label' => 'Risco de Perda (Comercial)',
                'description' => 'Analise um Lead do funil e receba probabilidade de perda, recomendações e um e-mail pronto.',
                'icon' => 'heroicon-o-presentation-chart-line',
                'color' => '#f59e0b',
                'url' => CrmFunil::getUrl(),
                'visible' => $hasFeature,
            ],
            [
                'label' => 'Análise de Estoque',
                'description' => 'Verifica materiais críticos e parados, e receba uma priorização de compra por IA.',
                'icon' => 'heroicon-o-archive-box',
                'color' => '#16a34a',
                'url' => AnaliseEstoque::getUrl(),
                'visible' => $hasFeature,
            ],
        ];
    }
}
