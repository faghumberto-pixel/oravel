<?php

namespace App\Domain\Intelligence\Support;

/**
 * Fonte única do que o Intelligence recebe e calcula: campos da planilha (entrada), indicadores (saída) e metas.
 * Planilha, importador, calculadora de indicadores e diagnóstico leem só daqui: campo/indicador novo = uma linha aqui.
 *
 * ATENÇÃO: as metas (ruim/bom) são valores iniciais de referência, ainda não validados com o mercado de locação.
 * O benchmark real (entre empresas) é o que deve calibrá-las depois.
 */
class Catalogo
{
    public const SHEET = 'Dados';

    /** @return list<array{key:string, group:string, title:string, type:string, required:bool, help:string, example:string, enum?:list<string>}> */
    public static function campos(): array
    {
        $c = fn (string $group, string $key, string $title, string $type, string $help, string $example, bool $required = false, ?array $enum = null) => array_filter([
            'group' => $group, 'key' => $key, 'title' => $title, 'type' => $type, 'help' => $help, 'example' => $example, 'required' => $required, 'enum' => $enum,
        ], fn ($v) => $v !== null);

        return [
            // Perfil: só serve para comparar a empresa com parecidas (benchmark) e para datar a fotografia.
            $c('Perfil', 'data_referencia', 'Data de Referência', 'date', 'Data a que os números se referem (dd/mm/aaaa). Não pode ser futura.', '30/09/2026', true),
            $c('Perfil', 'regiao', 'Estado (UF)', 'text', 'Sigla do estado da operação principal. Usado só para comparar com empresas parecidas.', 'SP'),
            $c('Perfil', 'porte', 'Porte', 'enum', 'Micro, Pequeno, Médio ou Grande.', 'Médio', false, ['Micro', 'Pequeno', 'Médio', 'Grande']),
            $c('Perfil', 'segmento', 'Segmento', 'text', 'Ex.: Locação de equipamentos, Logística, Serviços.', 'Locação de equipamentos'),

            // Frota → "minha disponibilidade aumentou?", "quanto cada equipamento rende?", "estou parando mais que os parecidos?"
            $c('Frota', 'frota_total', 'Total de Equipamentos', 'int', 'Quantidade de equipamentos da frota na data.', '120'),
            $c('Frota', 'frota_locada', 'Equipamentos Locados', 'int', 'Quantos estavam locados (gerando receita).', '84'),
            $c('Frota', 'frota_indisponivel', 'Equipamentos Indisponíveis', 'int', 'Quantos não podiam ser locados: na oficina, sem peça, sinistrados ou parados por qualquer motivo.', '12'),
            $c('Frota', 'valor_frota', 'Valor da Frota (R$)', 'decimal', 'Valor de mercado (ou contábil) de todos os equipamentos. Base do retorno sobre o patrimônio.', '9000000'),

            // Financeira → "minha rentabilidade cresceu?", "onde estou perdendo dinheiro?"
            $c('Financeira', 'receita_mensal', 'Receita do Mês (R$)', 'decimal', 'Faturamento do mês, sem R$.', '450000'),
            $c('Financeira', 'custo_manutencao', 'Custo de Manutenção do Mês (R$)', 'decimal', 'Peças, mão de obra e terceiros da manutenção.', '38000'),
            $c('Financeira', 'custo_operacional', 'Demais Custos do Mês (R$)', 'decimal', 'Todos os outros custos da operação: folha, frete, aluguel, impostos.', '210000'),
            $c('Financeira', 'inadimplencia_valor', 'Valor em Atraso (R$)', 'decimal', 'Total de faturas vencidas e não pagas na data.', '22000'),

            // Comercial → "minha carteira é saudável?"
            $c('Comercial', 'propostas_emitidas', 'Propostas Emitidas no Mês', 'int', 'Quantidade de propostas enviadas.', '40'),
            $c('Comercial', 'propostas_ganhas', 'Propostas Ganhas no Mês', 'int', 'Quantas viraram contrato.', '14'),
            $c('Comercial', 'maior_cliente_pct', 'Receita do Maior Cliente (%)', 'pct', 'Quanto da receita do mês veio do maior cliente (0 a 100). Mede dependência.', '18'),

            // Oficina → "minha oficina melhorou?", "tenho gente suficiente na oficina?"
            $c('Oficina', 'os_corretivas', 'OS Corretivas no Mês', 'int', 'Ordens de serviço abertas por quebra ou defeito.', '45'),
            $c('Oficina', 'os_preventivas', 'OS Preventivas no Mês', 'int', 'Ordens de serviço abertas por revisão programada.', '30'),
            $c('Oficina', 'tempo_medio_reparo_h', 'Tempo Médio de Reparo (horas)', 'decimal', 'Da abertura à conclusão da OS.', '18'),
            $c('Oficina', 'tecnicos', 'Técnicos de Manutenção', 'int', 'Quantidade de técnicos que atuam na manutenção.', '6'),

            // Operacional
            $c('Operacional', 'entregas_no_prazo_pct', 'Entregas no Prazo (%)', 'pct', 'Percentual de entregas/retiradas feitas no prazo (0 a 100).', '88'),
        ];
    }

    /** Colunas no formato do gerador de modelo .xlsx (AssetImportColumns::writeTemplate). */
    public static function colunasDoModelo(): array
    {
        return array_map(fn ($c) => [
            'group' => $c['group'], 'title' => $c['title'], 'required' => $c['required'], 'help' => $c['help'], 'example' => $c['example'],
        ], self::campos());
    }

    /**
     * Indicadores calculados. `area` do diagnóstico; `ruim`/`bom` = valores que valem 0 e 100 pontos (null = só informativo, não pontua).
     *
     * @return array<string, array{area:string, titulo:string, unidade:string, ruim:?float, bom:?float, calc:callable}>
     */
    public static function indicadores(): array
    {
        $div = fn ($a, $b, $f = 1) => ($a === null || $b === null || $b == 0) ? null : $a / $b * $f;

        return [
            'disponibilidade_frota' => ['area' => 'frota', 'titulo' => 'Disponibilidade da Frota', 'unidade' => '%', 'ruim' => 70, 'bom' => 95,
                'calc' => fn ($d) => isset($d['frota_indisponivel'], $d['frota_total']) ? $div($d['frota_total'] - $d['frota_indisponivel'], $d['frota_total'], 100) : null],
            'utilizacao_frota' => ['area' => 'frota', 'titulo' => 'Utilização da Frota', 'unidade' => '%', 'ruim' => 40, 'bom' => 85,
                'calc' => fn ($d) => $div($d['frota_locada'] ?? null, $d['frota_total'] ?? null, 100)],
            'receita_por_equipamento' => ['area' => 'frota', 'titulo' => 'Receita por Equipamento', 'unidade' => 'R$', 'ruim' => null, 'bom' => null,
                'calc' => fn ($d) => $div($d['receita_mensal'] ?? null, $d['frota_total'] ?? null)],
            'custo_manutencao_por_equipamento' => ['area' => 'frota', 'titulo' => 'Custo de Manutenção por Equipamento', 'unidade' => 'R$', 'ruim' => null, 'bom' => null,
                'calc' => fn ($d) => $div($d['custo_manutencao'] ?? null, $d['frota_total'] ?? null)],

            'margem_operacional' => ['area' => 'financeira', 'titulo' => 'Margem Operacional', 'unidade' => '%', 'ruim' => 0, 'bom' => 30,
                'calc' => fn ($d) => isset($d['receita_mensal']) && (isset($d['custo_manutencao']) || isset($d['custo_operacional']))
                    ? $div($d['receita_mensal'] - ($d['custo_manutencao'] ?? 0) - ($d['custo_operacional'] ?? 0), $d['receita_mensal'], 100) : null],
            'retorno_sobre_frota' => ['area' => 'financeira', 'titulo' => 'Retorno Anualizado sobre o Valor da Frota', 'unidade' => '%', 'ruim' => 8, 'bom' => 30,
                'calc' => fn ($d) => isset($d['receita_mensal'], $d['valor_frota']) && (isset($d['custo_manutencao']) || isset($d['custo_operacional']))
                    ? $div(($d['receita_mensal'] - ($d['custo_manutencao'] ?? 0) - ($d['custo_operacional'] ?? 0)) * 12, $d['valor_frota'], 100) : null],
            'custo_manutencao_sobre_receita' => ['area' => 'financeira', 'titulo' => 'Custo de Manutenção sobre a Receita', 'unidade' => '%', 'ruim' => 25, 'bom' => 5,
                'calc' => fn ($d) => $div($d['custo_manutencao'] ?? null, $d['receita_mensal'] ?? null, 100)],
            'inadimplencia_pct' => ['area' => 'financeira', 'titulo' => 'Inadimplência sobre a Receita', 'unidade' => '%', 'ruim' => 15, 'bom' => 2,
                'calc' => fn ($d) => $div($d['inadimplencia_valor'] ?? null, $d['receita_mensal'] ?? null, 100)],

            'taxa_conversao_propostas' => ['area' => 'comercial', 'titulo' => 'Conversão de Propostas', 'unidade' => '%', 'ruim' => 10, 'bom' => 40,
                'calc' => fn ($d) => $div($d['propostas_ganhas'] ?? null, $d['propostas_emitidas'] ?? null, 100)],
            'concentracao_maior_cliente' => ['area' => 'comercial', 'titulo' => 'Dependência do Maior Cliente', 'unidade' => '%', 'ruim' => 40, 'bom' => 10,
                'calc' => fn ($d) => $d['maior_cliente_pct'] ?? null],

            'preventiva_pct' => ['area' => 'oficina', 'titulo' => 'Manutenção Preventiva', 'unidade' => '%', 'ruim' => 10, 'bom' => 60,
                'calc' => fn ($d) => isset($d['os_corretivas']) || isset($d['os_preventivas'])
                    ? $div($d['os_preventivas'] ?? 0, ($d['os_corretivas'] ?? 0) + ($d['os_preventivas'] ?? 0), 100) : null],
            'corretivas_por_100_equipamentos' => ['area' => 'oficina', 'titulo' => 'Corretivas por 100 Equipamentos', 'unidade' => 'un', 'ruim' => 40, 'bom' => 10,
                'calc' => fn ($d) => $div($d['os_corretivas'] ?? null, $d['frota_total'] ?? null, 100)],
            'tempo_medio_reparo_h' => ['area' => 'oficina', 'titulo' => 'Tempo Médio de Reparo', 'unidade' => 'h', 'ruim' => 72, 'bom' => 8,
                'calc' => fn ($d) => $d['tempo_medio_reparo_h'] ?? null],
            'equipamentos_por_tecnico' => ['area' => 'oficina', 'titulo' => 'Equipamentos por Técnico', 'unidade' => 'un', 'ruim' => null, 'bom' => null,
                'calc' => fn ($d) => $div($d['frota_total'] ?? null, $d['tecnicos'] ?? null)],

            'entregas_no_prazo_pct' => ['area' => 'operacional', 'titulo' => 'Entregas no Prazo', 'unidade' => '%', 'ruim' => 60, 'bom' => 95,
                'calc' => fn ($d) => $d['entregas_no_prazo_pct'] ?? null],
        ];
    }

    /**
     * Orientação por indicador (alimenta Diagnóstico e Plano de evolução): por que importa e o que fazer. Texto de referência geral
     * para locadoras, a revisar com um especialista do setor antes de ser vendido como recomendação.
     *
     * @return array<string, array{impacto: string, acoes: list<string>}>
     */
    public static function orientacao(): array
    {
        return [
            'disponibilidade_frota' => ['impacto' => 'Equipamento parado não gera receita e compromete a entrega ao cliente.', 'acoes' => [
                'Listar os equipamentos indisponíveis há mais tempo e definir um prazo de retorno para cada um.',
                'Reservar as peças de maior giro para reduzir a espera na oficina.',
                'Priorizar na oficina os equipamentos que têm contrato aguardando.']],
            'utilizacao_frota' => ['impacto' => 'Equipamento disponível e sem contrato é capital parado.', 'acoes' => [
                'Oferecer ativamente os equipamentos ociosos à carteira de clientes.',
                'Rever preço e condições dos itens com menor giro.',
                'Avaliar a venda ou a redução de itens ociosos há muito tempo.']],
            'margem_operacional' => ['impacto' => 'É o que sobra da receita depois dos custos: mede se a operação se sustenta.', 'acoes' => [
                'Mapear os 3 maiores custos do mês e definir uma meta de redução para cada um.',
                'Rever os contratos de margem baixa no próximo reajuste.',
                'Acompanhar o resultado todo mês, não só no fechamento do ano.']],
            'retorno_sobre_frota' => ['impacto' => 'Mostra quanto a frota rende por ano sobre o valor investido nela.', 'acoes' => [
                'Identificar os equipamentos que menos contribuem para a receita.',
                'Priorizar investimento nas categorias com melhor retorno.',
                'Rever o preço de locação dos equipamentos de maior valor.']],
            'custo_manutencao_sobre_receita' => ['impacto' => 'Manutenção cara corrói a margem e costuma indicar equipamento velho ou manutenção só corretiva.', 'acoes' => [
                'Levantar os equipamentos com maior custo de manutenção acumulado.',
                'Aumentar a parcela de preventiva para reduzir as corretivas.',
                'Negociar peças e serviços com os fornecedores mais usados.']],
            'inadimplencia_pct' => ['impacto' => 'Receita faturada e não recebida pressiona o caixa.', 'acoes' => [
                'Criar uma rotina de cobrança logo após o vencimento.',
                'Rever limite e condições dos clientes com atrasos recorrentes.',
                'Exigir garantias nos contratos de maior valor.']],
            'taxa_conversao_propostas' => ['impacto' => 'Mostra quanto do esforço comercial vira contrato.', 'acoes' => [
                'Registrar o motivo de cada proposta perdida.',
                'Responder as propostas em até 24 horas.',
                'Rever proposta e preço nas categorias com menos fechamento.']],
            'concentracao_maior_cliente' => ['impacto' => 'Depender de um só cliente expõe a empresa se ele reduzir ou sair.', 'acoes' => [
                'Definir uma participação máxima por cliente.',
                'Abrir novos clientes nos segmentos que a empresa já atende.',
                'Renovar com antecedência o contrato do maior cliente.']],
            'preventiva_pct' => ['impacto' => 'Preventiva evita quebra em campo e reduz o custo de manutenção.', 'acoes' => [
                'Montar o plano de preventiva por tipo de equipamento (horas de uso ou meses).',
                'Programar a revisão antes de o equipamento voltar ao cliente.',
                'Acompanhar o cumprimento do plano toda semana.']],
            'corretivas_por_100_equipamentos' => ['impacto' => 'Muita corretiva por equipamento indica falha que se repete.', 'acoes' => [
                'Analisar as causas das corretivas que mais se repetem.',
                'Rever o checklist de saída e de retorno dos equipamentos.',
                'Tratar a causa-raiz dos equipamentos que quebram com frequência.']],
            'tempo_medio_reparo_h' => ['impacto' => 'Reparo demorado mantém o equipamento parado e atrasa o cliente.', 'acoes' => [
                'Medir onde o tempo se perde: espera de peça, fila ou mão de obra.',
                'Manter estoque mínimo das peças mais usadas.',
                'Redistribuir a carga entre os técnicos.']],
            'entregas_no_prazo_pct' => ['impacto' => 'Atraso na entrega prejudica a obra do cliente e a reputação da empresa.', 'acoes' => [
                'Planejar rota e agenda de entregas com antecedência.',
                'Confirmar a disponibilidade do equipamento antes de prometer o prazo.',
                'Registrar o motivo de cada atraso para corrigir a causa.']],
        ];
    }
}
