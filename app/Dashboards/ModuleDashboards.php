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
        ];
    }

    public static function find(string $key): ?array
    {
        return static::all()[$key] ?? null;
    }
}
