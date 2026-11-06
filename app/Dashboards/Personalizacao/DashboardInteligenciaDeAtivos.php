<?php

namespace App\Dashboards\Personalizacao;

use App\Models\DashboardTemplate;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;

/**
 * Modelo de dashboard "Inteligência de Ativos — custo e localização" (modelo da PLATAFORMA): aparece na Central, em
 * Dashboards personalizados, e a Central atribui a quem quiser (a empresa precisa ter as chaves do contrato; sem elas
 * os blocos não aparecem). Na Locadora Demo ele é atribuído como tela Início.
 */
class DashboardInteligenciaDeAtivos
{
    public const NOME = 'Inteligência de Ativos — custo e localização';

    /** Ordem de exibicao (grade de 12 colunas: 3+3+3+3 | 6+6 | 6+3+3 | 6+6). */
    public const BLOCOS = [
        'ativos_ia.custo_12m', 'ativos_ia.trocar_vender', 'ativos_ia.em_revisao', 'ativos_ia.localizados',
        'ativos_ia.mapa', 'ativos_ia.mapa_calor',
        'ativos_ia.ranking_valor', 'ativos_ia.situacao', 'ativos_ia.tipo_manutencao',
        'ativos_ia.menos_retorno', 'ativos_ia.alertas', 'ativos_ia.ocorrencias', 'ativos_ia.devolucoes',
    ];

    /** Cria (ou atualiza) o modelo da plataforma. Repetir nao duplica. */
    public static function garantirModelo(): DashboardTemplate
    {
        return DB::transaction(function () {
            $modelo = DashboardTemplate::withoutGlobalScopes()->firstOrNew(['tenant_id' => null, 'name' => self::NOME]);
            $modelo->fill([
                'profile' => 'direcao',
                'description' => 'Onde está cada equipamento e quanto ele custa de manutenção: custo em 12 meses, quem passou do limite, mapa, mapa de calor por mês, retorno por equipamento e alertas.',
                'is_active' => true,
            ])->save();

            $modelo->blocks()->delete();
            foreach (self::BLOCOS as $i => $chave) {
                $modelo->blocks()->create(['block_key' => $chave, 'position' => $i + 1, 'config' => []]);
            }

            return $modelo;
        });
    }

    /** Atribui o modelo a uma empresa (opcionalmente como tela Início). */
    public static function atribuirA(Tenant $empresa, bool $inicio = false): DashboardTemplate
    {
        $modelo = self::garantirModelo();
        DashboardTemplateService::atribuir($modelo, [$empresa->id], $inicio);

        return $modelo;
    }
}
