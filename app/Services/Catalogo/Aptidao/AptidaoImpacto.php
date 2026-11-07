<?php

namespace App\Services\Catalogo\Aptidao;

use App\Models\Asset;
use App\Models\AssetCategory;

/**
 * Previa de impacto ANTES de salvar uma mudanca de regra: quantos ativos do grupo mudariam de status de aptidao
 * (calcula o perfil atual x o perfil proposto pelo formulario, sem gravar nada).
 */
class AptidaoImpacto
{
    public function __construct(private AssetFitnessService $motor) {}

    /**
     * @param  array<string, mixed>  $estadoDoForm  estado do formulario do grupo (repeaters "record-{id}")
     * @return array{total: int, mudaram: int, transicoes: array<string, int>, exemplos: array<int, array{ativo: string, de: string, para: string}>}
     */
    public function previa(AssetCategory $grupo, array $estadoDoForm): array
    {
        $atual = PerfilDoGrupo::doGrupo($grupo);
        $proposto = $atual->comEstadoDoForm($estadoDoForm);

        $out = ['total' => 0, 'mudaram' => 0, 'transicoes' => [], 'exemplos' => []];

        Asset::withoutGlobalScopes()->where('tenant_id', $grupo->tenant_id)->where('asset_category_id', $grupo->id)->whereNull('deleted_at')
            ->chunkById(100, function ($lote) use ($atual, $proposto, &$out) {
                foreach ($lote as $ativo) {
                    $out['total']++;
                    $de = $this->motor->avaliar($ativo, $atual)->status;
                    $para = $this->motor->avaliar($ativo, $proposto)->status;

                    if ($de !== $para) {
                        $out['mudaram']++;
                        $out['transicoes']["{$de}>{$para}"] = ($out['transicoes']["{$de}>{$para}"] ?? 0) + 1;

                        if (count($out['exemplos']) < 5) {
                            $out['exemplos'][] = ['ativo' => (string) ($ativo->name ?? $ativo->tag), 'de' => $de, 'para' => $para];
                        }
                    }
                }
            });

        return $out;
    }

    public static function rotulo(string $status): string
    {
        return ['apto' => 'Apto', 'apto_restricao' => 'Apto com restrição', 'inapto' => 'Inapto', 'cadastro_incompleto' => 'Cadastro incompleto'][$status] ?? $status;
    }
}
