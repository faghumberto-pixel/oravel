<?php

namespace App\Dashboards\Blocks\Planejamento;

use App\Dashboards\Block;
use App\Dashboards\Concerns\SeriesDeTempo;
use App\Dashboards\Payload;
use App\Models\Asset;
use App\Models\MaintenancePlan;

class PreventivasVencidas extends Block
{
    use SeriesDeTempo;

    public static function key(): string
    {
        return 'pmp.preventivas_vencidas';
    }

    public static function title(): string
    {
        return 'Preventivas vencidas';
    }

    public static function subtitle(): string
    {
        return 'Itens do plano que já passaram do horímetro, dos dias ou dos ciclos';
    }

    public static function size(): int
    {
        return 3;
    }

    public static function module(): string
    {
        return 'PMP';
    }

    public static function requiredFeature(): string
    {
        return 'tabela_maintenance_plans';
    }

    public static function sourceModel(): string
    {
        return MaintenancePlan::class;
    }

    public function data(array $config): array
    {
        [$vencidas, $aVencer] = $this->situacao();

        return Payload::kpi(count($vencidas), count($vencidas) === 1 ? 'item' : 'itens', count($aVencer).' vencem nos próximos 7 dias', count($vencidas) > 0 ? 'dn' : 'up');
    }

    /**
     * Avalia os planos aplicaveis a cada ativo (limite para nao pesar a tela).
     *
     * @return array{0: list<array{ativo: string, plano: string, horas: float}>, 1: list<array{ativo: string, plano: string, horas: float}>}
     */
    public function publicSituacao(): array
    {
        return $this->situacao();
    }

    protected function situacao(): array
    {
        $vencidas = $aVencer = [];

        foreach (Asset::query()->limit(150)->get() as $ativo) {
            foreach (MaintenancePlan::applicableFor($ativo)->where('is_active', true) as $plano) {
                $s = $plano->dueStatusForAsset($ativo);
                $linha = ['ativo' => $ativo->name, 'plano' => $plano->name, 'horas' => (float) $s['overdue_hours']];

                if ($s['is_overdue']) {
                    $vencidas[] = $linha;
                } elseif ($plano->interval_hours && ($s['due_at_hours'] - (float) $ativo->horimetro_atual) <= 50) {
                    $linha['horas'] = (float) ($s['due_at_hours'] - (float) $ativo->horimetro_atual);
                    $aVencer[] = $linha;
                }
            }
        }

        return [$vencidas, $aVencer];
    }
}
