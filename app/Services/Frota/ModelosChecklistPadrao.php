<?php

namespace App\Services\Frota;

use App\Models\FrotaItemModeloChecklist as Item;
use App\Models\FrotaModeloChecklist;

/**
 * Modelos de checklist padrão da frota (rápido e completo). Criados na primeira vez que o cliente usa o
 * módulo (ou pelo comando frota:criar-modelos-padrao), sem seeder: só se o cliente ainda não tem nenhum modelo.
 */
class ModelosChecklistPadrao
{
    public static function garantir(string $tenantId): void
    {
        if (FrotaModeloChecklist::withoutGlobalScopes()->where('tenant_id', $tenantId)->exists()) {
            return;
        }

        $rapido = self::itensRapidos();
        $completo = array_merge($rapido, self::itensExtrasDoCompleto());

        foreach ([
            ['Checklist rápido (saída e retorno)', FrotaModeloChecklist::TIPO_RAPIDO, $rapido],
            ['Checklist completo', FrotaModeloChecklist::TIPO_COMPLETO, $completo],
        ] as [$nome, $tipo, $itens]) {
            $modelo = FrotaModeloChecklist::withoutGlobalScopes()->create(['tenant_id' => $tenantId, 'nome' => $nome, 'tipo' => $tipo, 'ativo' => true]);

            foreach ($itens as $ordem => $item) {
                Item::create(array_merge(['modelo_id' => $modelo->id, 'ordem' => $ordem + 1], $item));
            }
        }
    }

    /** @return array<int, array<string, mixed>> */
    private static function itensRapidos(): array
    {
        return [
            ['descricao' => 'Pneus: aparência e calibragem', 'categoria' => 'pneus', 'gravidade' => Item::GRAVIDADE_CRITICA, 'exige_foto_se_problema' => true],
            ['descricao' => 'Nível do óleo do motor', 'categoria' => 'fluidos', 'gravidade' => Item::GRAVIDADE_ATENCAO],
            ['descricao' => 'Água / arrefecimento', 'categoria' => 'fluidos', 'gravidade' => Item::GRAVIDADE_ATENCAO],
            ['descricao' => 'Freios (pedal e freio de mão)', 'categoria' => 'freios', 'gravidade' => Item::GRAVIDADE_CRITICA, 'exige_foto_se_problema' => true],
            ['descricao' => 'Luzes e setas', 'categoria' => 'luzes', 'gravidade' => Item::GRAVIDADE_ATENCAO],
            ['descricao' => 'Painel sem luz de alerta acesa', 'categoria' => 'outros', 'gravidade' => Item::GRAVIDADE_ATENCAO, 'exige_foto_se_problema' => true],
            ['descricao' => 'Combustível suficiente', 'categoria' => 'fluidos', 'gravidade' => Item::GRAVIDADE_INFORMATIVA],
            ['descricao' => 'Documentos do veículo e CNH', 'categoria' => 'documentos', 'gravidade' => Item::GRAVIDADE_CRITICA],
            ['descricao' => 'Extintor, triângulo e macaco', 'categoria' => 'seguranca', 'gravidade' => Item::GRAVIDADE_ATENCAO],
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private static function itensExtrasDoCompleto(): array
    {
        return [
            ['descricao' => 'Sulco dos pneus', 'categoria' => 'pneus', 'gravidade' => Item::GRAVIDADE_ATENCAO, 'tipo_resposta' => Item::RESPOSTA_NUMERO, 'unidade' => 'mm', 'exige_foto_se_problema' => true],
            ['descricao' => 'Tensão da bateria', 'categoria' => 'fluidos', 'gravidade' => Item::GRAVIDADE_ATENCAO, 'tipo_resposta' => Item::RESPOSTA_NUMERO, 'unidade' => 'V'],
            ['descricao' => 'Limpadores de para-brisa', 'categoria' => 'carroceria', 'gravidade' => Item::GRAVIDADE_ATENCAO],
            ['descricao' => 'Correias', 'categoria' => 'outros', 'gravidade' => Item::GRAVIDADE_ATENCAO],
            ['descricao' => 'Vazamentos (óleo, água, combustível)', 'categoria' => 'fluidos', 'gravidade' => Item::GRAVIDADE_CRITICA, 'exige_foto_se_problema' => true],
            ['descricao' => 'Limpeza interna e externa', 'categoria' => 'carroceria', 'gravidade' => Item::GRAVIDADE_INFORMATIVA],
        ];
    }
}
