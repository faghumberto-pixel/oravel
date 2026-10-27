<?php

namespace App\Dashboards;

/**
 * Dashboards padrão por módulo (grupo do contrato): igual para toda empresa que contratou o módulo.
 * `blocks` é a ordem de exibição. Os demais módulos entram aqui nas próximas etapas.
 */
class ModuleDashboards
{
    /** @return array<string, array{label: string, module: string, lead: string, blocks: list<string>}> */
    public static function all(): array
    {
        return [
            'manutencao' => [
                'label' => 'Visão geral',
                'module' => 'Manutenção',
                'lead' => 'Atendimentos, prazo, retorno à máquina e peças paradas, em uma tela.',
                'blocks' => [
                    'manutencao.atendimentos_hoje',
                    'manutencao.equipamentos_parados',
                    'manutencao.no_prazo',
                    'manutencao.retorno_maquina',
                    'manutencao.km_rodados',
                    'manutencao.custo_medio',
                    'manutencao.agenda_hoje',
                    'manutencao.por_situacao',
                    'manutencao.tipo_atendimento',
                    'manutencao.tempo_primeiro_atendimento',
                    'manutencao.defeitos_frequentes',
                    'manutencao.aguardando_peca',
                ],
            ],
            'materiais' => [
                'label' => 'Visão geral',
                'module' => 'Materiais e Peças',
                'lead' => 'Estoque, compras e reposição de materiais e peças, em uma tela.',
                'blocks' => [
                    'materiais.valor_em_estoque',
                    'materiais.giro',
                    'materiais.abaixo_minimo_total',
                    'materiais.compras_a_receber',
                    'materiais.abaixo_minimo_lista',
                    'materiais.estoque_por_categoria',
                    'materiais.mais_consumidos',
                    'materiais.cobertura_dias',
                    'materiais.aplicadas_x_orcadas',
                    'materiais.requisicoes_abertas',
                ],
            ],
        ];
    }

    public static function find(string $key): ?array
    {
        return static::all()[$key] ?? null;
    }
}
