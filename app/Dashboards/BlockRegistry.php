<?php

namespace App\Dashboards;

use App\Dashboards\Blocks\Ativos;
use App\Dashboards\Blocks\Comercial;
use App\Dashboards\Blocks\Financeiro;
use App\Dashboards\Blocks\Frota;
use App\Dashboards\Blocks\InteligenciaAtivos;
use App\Dashboards\Blocks\Logistica;
use App\Dashboards\Blocks\Manutencao;
use App\Dashboards\Blocks\MateriaisPecas;

/**
 * Catálogo de blocos. Lista explícita (e não descoberta por pasta) para que uma classe nova nunca apareça
 * no catálogo sem ser registrada de propósito.
 */
class BlockRegistry
{
    /** @return array<string, class-string<Block>> chave => classe */
    public static function all(): array
    {
        $classes = [
            Manutencao\AtendimentosHoje::class,
            Manutencao\CargaPorTecnico::class,
            Manutencao\CustoPorMes::class,
            Manutencao\OsPorDiaDaSemana::class,
            Manutencao\UltimasOcorrencias::class,
            Manutencao\EquipamentosParados::class,
            Manutencao\AtendimentosNoPrazo::class,
            Manutencao\RetornoMaquina::class,
            Manutencao\KmRodados::class,
            Manutencao\CustoMedio::class,
            Manutencao\AgendaDeHoje::class,
            Manutencao\AtendimentosPorSituacao::class,
            Manutencao\TipoDeAtendimento::class,
            Manutencao\TempoPrimeiroAtendimento::class,
            Manutencao\DefeitosFrequentes::class,
            Manutencao\AguardandoPeca::class,
            Manutencao\SlaResolucaoCumprida::class,
            Manutencao\SlaVencidosAgora::class,
            Manutencao\SlaTempoMedioResolucao::class,

            MateriaisPecas\ValorEmEstoque::class,
            MateriaisPecas\EntregasPrevistas::class,
            MateriaisPecas\UltimasMovimentacoes::class,
            MateriaisPecas\GiroDoEstoque::class,
            MateriaisPecas\ItensAbaixoDoMinimo::class,
            MateriaisPecas\ComprasAReceber::class,
            MateriaisPecas\AbaixoDoMinimoSugestao::class,
            MateriaisPecas\EstoquePorCategoria::class,
            MateriaisPecas\MateriaisMaisConsumidos::class,
            MateriaisPecas\CoberturaEmDias::class,
            MateriaisPecas\AplicadasEOrcadas::class,
            MateriaisPecas\RequisicoesEmAberto::class,

            Comercial\ContratosAtivos::class,
            Comercial\ContratosVencendo::class,
            Comercial\ConversaoDeOrcamentos::class,
            Comercial\OrcamentosSemRetorno::class,
            Comercial\FunilDeVendas::class,
            Comercial\VendasPorVendedor::class,
            Comercial\ContratosARenovar::class,
            Comercial\ClientesParaLigar::class,
            Comercial\PedidosDeLocacao::class,
            Comercial\ContratosPorMes::class,
            Comercial\PipelineEmAberto::class,
            Comercial\PropostasEmAberto::class,
            Comercial\OrcamentosPorSituacao::class,
            Comercial\OrigemDosLeads::class,
            Comercial\MapaDosLeads::class,
            Comercial\EsteiraDePropostas::class,
            Comercial\OrigemPorMes::class,
            Comercial\ProximosCompromissos::class,
            Comercial\UltimosFechamentos::class,

            Financeiro\AReceberNoMes::class,
            Financeiro\SaldoDoMes::class,
            Financeiro\AgendaDeVencimentos::class,
            Financeiro\PagarPorCategoria::class,
            Financeiro\RecebidoNoMes::class,
            Financeiro\APagarNoMes::class,
            Financeiro\PagoNoMes::class,
            Financeiro\RecebimentosAtrasados::class,
            Financeiro\PagamentosAtrasados::class,
            Financeiro\RecebidoXPago::class,
            Financeiro\ReceitaPorCategoria::class,
            Financeiro\ProximosPagamentos::class,

            InteligenciaAtivos\CustoDoPeriodo::class,
            InteligenciaAtivos\TrocarOuVender::class,
            InteligenciaAtivos\EmRevisao::class,
            InteligenciaAtivos\Localizados::class,
            InteligenciaAtivos\MapaDosEquipamentos::class,
            InteligenciaAtivos\MapaDeCalorDoCusto::class,
            InteligenciaAtivos\RankingDoCusto::class,
            InteligenciaAtivos\SituacaoDaFrota::class,
            InteligenciaAtivos\TipoDeManutencao::class,
            InteligenciaAtivos\QuemDaMenosRetorno::class,
            InteligenciaAtivos\AlertasDeAtivos::class,
            InteligenciaAtivos\OcorrenciasDaFrota::class,
            InteligenciaAtivos\DevolucoesPrevistas::class,
            Frota\GastoComCombustivel::class,
            Frota\ConsumoMedio::class,
            Frota\MultasEmAberto::class,
            Frota\SinistrosEmAberto::class,
            Frota\TrocasDeOleoAVencer::class,
            Frota\AgendaDaFrota::class,
            Frota\CombustivelPorTipo::class,
            Frota\TotalDeVeiculos::class,
            Frota\VeiculosNaRua::class,
            Frota\KmNoMes::class,
            Frota\CustoPorKm::class,
            Frota\OndeEstaoOsVeiculos::class,
            Frota\MapaDaFrota::class,
            Frota\NaRuaAgora::class,
            Frota\SaidasPorFinalidade::class,
            Frota\DestinosMaisVisitados::class,
            Frota\QuemMaisDirige::class,
            Frota\UsoPorVeiculo::class,
            Frota\SaidasPorDiaDaSemana::class,
            Frota\UltimasMovimentacoesDaFrota::class,
            Frota\SaidasPorMes::class,
            Frota\KmPorSemana::class,
            Frota\CustoDeManutencaoDaFrota::class,
            Frota\CombustivelPorPeriodo::class,
            Frota\CorretivasXPreventivas::class,
            Frota\IndicePreventiva::class,
            Frota\CombustivelPorVeiculo::class,
            Frota\CombustivelPorMes::class,

            Logistica\MovimentacoesDeHoje::class,
            Logistica\MovimentacoesEmCurso::class,
            Logistica\FreteNoMes::class,
            Logistica\FretePorMes::class,
            Logistica\RetiradasSolicitadas::class,
            Logistica\MobilizacaoXDesmobilizacao::class,
            Logistica\FretePorTipo::class,
            Logistica\ProgramacaoDaLogistica::class,
            Logistica\VeiculosForaDoPatio::class,

            Ativos\TotalDeAtivos::class,
            Ativos\ValorPorCategoria::class,
            Ativos\TaxaDeLocacao::class,
            Ativos\AtivosDisponiveis::class,
            Ativos\AtivosEmManutencao::class,
            Ativos\AtivosPorSituacao::class,
            Ativos\AtivosPorCategoria::class,
            Ativos\AtivosPorCliente::class,
            Ativos\VencimentosDeDocumentos::class,
        ];

        $porChave = [];
        foreach ($classes as $classe) {
            $porChave[$classe::key()] = $classe;
        }

        return $porChave;
    }

    /** @return class-string<Block>|null */
    public static function find(string $key): ?string
    {
        return static::all()[$key] ?? null;
    }
}
