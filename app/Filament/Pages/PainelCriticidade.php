<?php

namespace App\Filament\Pages;

use App\Models\Asset;
use App\Models\EquipmentDamage;
use App\Models\MaintenancePlan;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Ponto de entrada rapido do gestor de manutencao: "o que precisa de
 * atencao AGORA". Cruza as 3 coisas que ja existiam soltas no sistema:
 *
 * - Preventiva vencida (MaintenancePlan::dueStatusForAsset, ja existia)
 * - Avaria em aberto (EquipmentDamage.status != cancelado -- o fluxo real
 *   nunca chega em "resolvido"/"cancelado" via UI hoje, entao "em aberto"
 *   e definido como "ainda em qualquer etapa do fluxo, nao cancelado")
 * - Reincidencia de dano (mesmo ativo + mesmo tipo, 2+ vezes em 90 dias --
 *   mesma logica de app/Filament/Pages/AvariasReincidencia.php)
 *
 * A criticidade "de cadastro" do ativo (importância pro negocio) vem da
 * Matriz ABC (Asset::abcMatrix()), que foi escolhida como fonte unica de
 * criticidade -- ver decisao registrada no commit que criou este arquivo.
 * criticality_level/criticidade_peso (as outras 2 fontes que existiam)
 * nao entram aqui, estao sendo aposentadas.
 */
class PainelCriticidade extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-fire';

    protected static ?string $navigationGroup = 'Manutenção';

    protected static ?string $navigationLabel = 'Painel de Criticidade';

    protected static ?string $title = 'Painel de Criticidade';

    protected static string $view = 'filament.pages.painel-criticidade';

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can('viewAny', Asset::class);
    }

    public function getLinhasProperty(): Collection
    {
        $assets = Asset::query()
            ->with('abcMatrix')
            ->whereNotIn('status', ['aguardando_triagem'])
            ->get();

        if ($assets->isEmpty()) {
            return collect();
        }

        $planos = MaintenancePlan::query()->get();
        $planosPorAsset = $planos->whereNotNull('asset_id')->groupBy('asset_id');
        $planosPorGrupo = $planos->whereNotNull('checklist_group_id')->groupBy('checklist_group_id');

        $avariasAbertasPorAsset = EquipmentDamage::query()
            ->where('status', '!=', EquipmentDamage::STATUS_CANCELADO)
            ->selectRaw('asset_id, count(*) as total')
            ->groupBy('asset_id')
            ->pluck('total', 'asset_id');

        $reincidentes = EquipmentDamage::query()
            ->select('asset_id')
            ->selectRaw('count(*) as total')
            ->whereNotNull('damage_type')
            ->where('created_at', '>=', now()->subDays(90))
            ->groupBy('asset_id', 'damage_type')
            ->having(DB::raw('count(*)'), '>=', 2)
            ->pluck('asset_id')
            ->unique();

        return $assets->map(function (Asset $asset) use ($planosPorAsset, $planosPorGrupo, $avariasAbertasPorAsset, $reincidentes) {
            $planosDoAtivo = $planosPorAsset->get($asset->id, collect())
                ->merge($asset->checklist_group_id ? $planosPorGrupo->get($asset->checklist_group_id, collect()) : collect());

            $preventivaVencida = $planosDoAtivo->contains(fn (MaintenancePlan $plano) => $plano->dueStatusForAsset($asset)['is_overdue']);

            $avariasAbertas = (int) ($avariasAbertasPorAsset[$asset->id] ?? 0);
            $reincidente = $reincidentes->contains($asset->id);

            $pontos = ($preventivaVencida ? 1 : 0) + ($avariasAbertas > 0 ? 1 : 0) + ($reincidente ? 1 : 0);
            $nivelAbc = $asset->abcMatrix?->nivel;

            // Nivel A (mais critico pro negocio) some 1 ponto extra -- um
            // ativo classe A com qualquer alerta pesa mais que um classe C.
            if ($nivelAbc === 'A') {
                $pontos++;
            }

            $flag = match (true) {
                $pontos >= 3 => 'critico',
                $pontos >= 1 => 'atencao',
                default => 'normal',
            };

            return [
                'asset' => $asset,
                'nivel_abc' => $nivelAbc,
                'preventiva_vencida' => $preventivaVencida,
                'avarias_abertas' => $avariasAbertas,
                'reincidente' => $reincidente,
                'flag' => $flag,
            ];
        })
            ->filter(fn ($linha) => $linha['flag'] !== 'normal')
            ->sortBy(fn ($linha) => match ($linha['flag']) {
                'critico' => 0,
                'atencao' => 1,
                default => 2,
            })
            ->values();
    }
}
