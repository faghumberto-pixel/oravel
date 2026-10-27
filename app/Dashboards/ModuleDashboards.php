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
            'comercial' => [
                'label' => 'Visão geral',
                'module' => 'Comercial',
                'lead' => 'Contratos, orçamentos, funil de vendas e clientes para contatar, em uma tela.',
                'blocks' => [
                    'comercial.contratos_ativos',
                    'comercial.contratos_vencendo',
                    'comercial.conversao_orcamentos',
                    'comercial.orcamentos_sem_retorno',
                    'comercial.funil',
                    'comercial.vendas_por_vendedor',
                    'comercial.contratos_a_renovar',
                    'comercial.clientes_para_ligar',
                    'comercial.pedidos_locacao',
                    'comercial.contratos_por_mes',
                ],
            ],
            'financeiro' => [
                'label' => 'Visão geral',
                'module' => 'Financeiro',
                'lead' => 'O que entra, o que sai e o que está em atraso, em uma tela.',
                'blocks' => [
                    'financeiro.a_receber_mes',
                    'financeiro.recebido_mes',
                    'financeiro.a_pagar_mes',
                    'financeiro.pago_mes',
                    'financeiro.recebimentos_atrasados',
                    'financeiro.pagamentos_atrasados',
                    'financeiro.receita_por_categoria',
                    'financeiro.proximos_pagamentos',
                    'financeiro.recebido_x_pago',
                ],
            ],
            'frota' => [
                'label' => 'Visão geral da frota',
                'module' => 'Gestão de Frota',
                'lead' => 'Combustível, óleo, multas e sinistros dos veículos, em uma tela.',
                'blocks' => [
                    'frota.combustivel_mes',
                    'frota.consumo_medio',
                    'frota.multas_em_aberto',
                    'frota.sinistros_em_aberto',
                    'frota.trocas_oleo_a_vencer',
                    'frota.combustivel_por_veiculo',
                    'frota.combustivel_por_mes',
                ],
            ],
        ];
    }

    public static function find(string $key): ?array
    {
        return static::all()[$key] ?? null;
    }
}
