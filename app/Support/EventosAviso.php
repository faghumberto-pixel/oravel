<?php

namespace App\Support;

/**
 * Avisos internos que o sistema envia a pessoas da empresa. Cada empresa
 * escolhe quem recebe cada um (Configurações > Responsáveis pelos Avisos).
 * Enquanto ninguém for escolhido, vale o comportamento antigo (quem tem o
 * papel indicado em `papeis`) e, se também não houver ninguém, os
 * administradores da empresa -- assim nenhum aviso se perde.
 */
class EventosAviso
{
    /**
     * @return array<string, array{label: string, ajuda: string, papeis: array<int, string>}>
     */
    public static function todos(): array
    {
        return [
            'proposta_para_revisao' => ['label' => 'Proposta comercial para revisar', 'ajuda' => 'Quando um vendedor envia uma proposta para revisão.', 'papeis' => ['Comercial']],
            'avaria_supervisor' => ['label' => 'Avaria aguardando o supervisor', 'ajuda' => 'Avaria nova que precisa de revisão técnica.', 'papeis' => ['Supervisor de Manutenção']],
            'avaria_comercial' => ['label' => 'Avaria para tratativa comercial', 'ajuda' => 'Avaria confirmada, que o comercial precisa tratar com o cliente.', 'papeis' => ['Comercial']],
            'troca_equipamento_solicitada' => ['label' => 'Troca de equipamento solicitada', 'ajuda' => 'Quando alguém pede a troca de um equipamento.', 'papeis' => ['Comercial']],
            'troca_equipamento_logistica' => ['label' => 'Troca de equipamento pronta para executar', 'ajuda' => 'Movimentações da troca prontas para a logística.', 'papeis' => ['Gerente de Logística']],
            'pendencia_os' => ['label' => 'Nova pendência em ordem de serviço', 'ajuda' => 'Pendência registrada numa OS.', 'papeis' => ['Supervisor de Manutenção', 'Gerente de Manutenção', 'Analista de Manutenção']],
            'requisicao_material' => ['label' => 'Requisição de material aberta', 'ajuda' => 'Pedido de compra ou reposição aguardando aprovação.', 'papeis' => ['admin', 'Gestor de Suprimentos']],
            'estoque_minimo' => ['label' => 'Estoque abaixo do mínimo', 'ajuda' => 'Material que atingiu o estoque mínimo.', 'papeis' => ['Gestor de Suprimentos']],
            'consumo_material' => ['label' => 'Alertas de consumo de material', 'ajuda' => 'Consumo acima do esperado ou outros desvios de material.', 'papeis' => ['admin']],
            'saldo_negativo_volante' => ['label' => 'Saldo negativo no almoxarifado volante', 'ajuda' => 'Veículo ficou com saldo negativo de peça.', 'papeis' => ['admin']],
            'ativo_critico_manutencao' => ['label' => 'Ativo crítico entrou em manutenção', 'ajuda' => 'Equipamento de alta prioridade parou para manutenção.', 'papeis' => ['oficina']],
            'contrato_local_mudou' => ['label' => 'Local de instalação do contrato mudou', 'ajuda' => 'O cliente informou outro local para o equipamento.', 'papeis' => ['Gerente de Logística']],
            'contrato_fechado_logistica' => ['label' => 'Contrato fechado, despacho a organizar', 'ajuda' => 'Nova locação fechada que a logística precisa despachar.', 'papeis' => ['Gerente de Logística']],
            'reserva_revogada' => ['label' => 'Reserva de equipamento revogada', 'ajuda' => 'Uma reserva para manutenção foi desfeita automaticamente.', 'papeis' => ['Gerente de Manutenção']],
            'manutencao_vencendo' => ['label' => 'Manutenção preventiva vencendo', 'ajuda' => 'Plano de manutenção de um ativo perto de vencer.', 'papeis' => ['admin']],
        ];
    }

    public static function existe(string $evento): bool
    {
        return array_key_exists($evento, static::todos());
    }

    /** @return array<string, string> evento => rótulo */
    public static function opcoes(): array
    {
        return array_map(fn ($e) => $e['label'], static::todos());
    }
}
