<?php

namespace Database\Seeders;

use App\Models\Asset;
use App\Models\InternalUnit;
use App\Models\Material;
use App\Models\StorageLocation;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

/**
 * Backfill de posicoes de planta baixa -- ate 2026-07-26 nenhum tenant tinha
 * StorageLocation, entao as telas de Planta Baixa (Almoxarifado e Patio de
 * Ativos) apareciam sempre vazias. Gera uma grade simples por unidade
 * (InternalUnit) em cada contexto e distribui os Materiais/Ativos sem
 * posicao ainda, round-robin, nas celulas criadas. Idempotente: pula
 * unidade+contexto que ja tem StorageLocation.
 */
class StorageLocationBackfillSeeder extends Seeder
{
    private const COLUNAS = 4;

    public function run(): void
    {
        foreach (InternalUnit::all() as $unit) {
            $this->seedContext(
                $unit,
                StorageLocation::CONTEXT_ALMOXARIFADO,
                Material::where('tenant_id', $unit->tenant_id)->whereNull('storage_location_id')->get(),
                'A',
            );

            $this->seedContext(
                $unit,
                StorageLocation::CONTEXT_PATIO_ATIVOS,
                Asset::where('tenant_id', $unit->tenant_id)->whereNull('storage_location_id')->get(),
                'Q',
            );
        }
    }

    /**
     * @param  Collection<int, Material|Asset>  $items
     */
    private function seedContext(InternalUnit $unit, string $context, Collection $items, string $prefix): void
    {
        $jaTem = StorageLocation::where('internal_unit_id', $unit->id)->where('context', $context)->exists();

        if ($jaTem) {
            $this->command?->info("Pulando {$unit->name} / {$context} -- já tem posições.");

            return;
        }

        // Sempre sobra pelo menos 3 celulas vazias, pra grade nao ficar
        // 100% ocupada (mais realista que todo quadrante ter algo).
        $totalPosicoes = max($items->count() + 3, self::COLUNAS * 2);
        $linhas = (int) ceil($totalPosicoes / self::COLUNAS);

        $posicoes = [];

        for ($linha = 1; $linha <= $linhas; $linha++) {
            for ($coluna = 1; $coluna <= self::COLUNAS; $coluna++) {
                $posicoes[] = StorageLocation::create([
                    'tenant_id' => $unit->tenant_id,
                    'internal_unit_id' => $unit->id,
                    'context' => $context,
                    'code' => sprintf('%s%d-%02d', $prefix, $linha, $coluna),
                    'row' => $linha,
                    'column' => $coluna,
                    'is_active' => true,
                ]);
            }
        }

        foreach ($items->values() as $index => $item) {
            $item->update(['storage_location_id' => $posicoes[$index]->id]);
        }

        $this->command?->info("Criadas {$totalPosicoes} posições ({$context}) para {$unit->name}, ".count($items)." item(ns) associado(s).");
    }
}
