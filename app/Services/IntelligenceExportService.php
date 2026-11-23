<?php

namespace App\Services;

use App\Domain\Intelligence\Support\Catalogo;
use App\Domain\Intelligence\Support\ModeloExcel;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Exporta do ERP a aba "Equipamentos" do Intelligence, já preenchida, para um mês: quem tem os dois produtos não digita nada.
 * Usa as MESMAS regras do ERP (custo da OS pelo mês da conclusão, ou da abertura se ainda aberta; retrabalho = is_rework; dias locado
 * pelos contratos). Só lê; nunca grava nada. O Intelligence continua independente: este serviço é do lado do ERP e só conhece o formato
 * da planilha (Catalogo::equipamentos()).
 *
 * Regras de receita por equipamento no mês, na ordem: medição aprovada/faturada do contrato no mês; senão, contrato mensal fixo
 * proporcional aos dias locados; senão, diária × dias locados; contrato por hora ou franquia sem medição não é estimado (célula em branco).
 */
class IntelligenceExportService
{
    private const MAX_LINHAS = 5000;

    /**
     * @return array{linhas: list<array<string, mixed>>, data_referencia: string, ignorados: int, avisos: list<string>}
     */
    public function linhas(string $tenantId, Carbon $mes): array
    {
        $ini = $mes->copy()->startOfMonth()->startOfDay();
        $fim = $mes->copy()->endOfMonth()->endOfDay();
        $diasMes = $ini->daysInMonth;
        $anoAtual = (int) Carbon::now()->year;
        $avisos = [];

        $ativos = DB::table('assets')->where('tenant_id', $tenantId)
            ->when(Schema::hasColumn('assets', 'deleted_at'), fn ($q) => $q->whereNull('deleted_at'))
            ->orderBy('patrimonio')->get(['id', 'patrimonio', 'name', 'asset_category', 'manufacturing_year', 'acquisition_value']);

        $custoMes = $custoAcum = $corretivas = $preventivas = $reincidencias = $diasOs = [];
        $ordens = DB::table('maintenance_orders')->where('tenant_id', $tenantId)->whereNull('deleted_at')->whereNotNull('asset_id')
            ->get(['asset_id', 'maintenance_type', 'is_rework', 'total_order_cost', 'created_at', 'finished_at']);
        foreach ($ordens as $o) {
            $a = $o->asset_id;
            $data = Carbon::parse($o->finished_at ?? $o->created_at);
            $prev = $o->maintenance_type === 'Preventiva';
            if ($data->lte($fim)) {
                $custoAcum[$a] = ($custoAcum[$a] ?? 0) + (float) $o->total_order_cost;
            }
            if ($data->between($ini, $fim)) {
                $custoMes[$a] = ($custoMes[$a] ?? 0) + (float) $o->total_order_cost;
                $prev ? $preventivas[$a] = ($preventivas[$a] ?? 0) + 1 : $corretivas[$a] = ($corretivas[$a] ?? 0) + 1;
                if (! $prev && $o->is_rework) {
                    $reincidencias[$a] = ($reincidencias[$a] ?? 0) + 1;
                }
            }
            // dias em que a OS manteve o equipamento na oficina (aberta até hoje ou até o fim do mês)
            $de = Carbon::parse($o->created_at);
            $ate = $o->finished_at ? Carbon::parse($o->finished_at) : $fim->copy();
            if ($de->lte($fim) && $ate->gte($ini)) {
                foreach ($this->dias(max($de, $ini), min($ate, $fim)) as $d) {
                    $diasOs[$a][$d] = true;
                }
            }
        }

        $diasLocado = [];
        $receita = [];
        $semEstimativa = [];
        $contratos = DB::table('contracts')->where('tenant_id', $tenantId)->whereNull('deleted_at')->whereNotNull('asset_id')->whereIn('status', ['Ativo', 'Encerrado'])
            ->whereDate('start_date', '<=', $fim->toDateString())->where(fn ($q) => $q->whereNull('end_date')->orWhereDate('end_date', '>=', $ini->toDateString()))
            ->get(['id', 'asset_id', 'start_date', 'end_date', 'price', 'billing_type']);
        $medicoes = DB::table('contract_measurements')->where('tenant_id', $tenantId)->whereIn('status', ['approved', 'invoiced'])
            ->whereBetween('reference_period_start', [$ini->toDateString(), $fim->toDateString()])->selectRaw('contract_id, sum(total_amount) as total')->groupBy('contract_id')->pluck('total', 'contract_id');
        foreach ($contratos as $c) {
            $de = max(Carbon::parse($c->start_date)->startOfDay(), $ini);
            $ate = min($c->end_date ? Carbon::parse($c->end_date)->endOfDay() : $fim->copy(), $fim);
            $dias = $this->dias($de, $ate);
            foreach ($dias as $d) {
                $diasLocado[$c->asset_id][$d] = true;
            }
            if (isset($medicoes[$c->id])) {
                $valor = (float) $medicoes[$c->id];
            } elseif ($c->billing_type === 'mensal_fixo' || $c->billing_type === null) {
                $valor = (float) $c->price * count($dias) / $diasMes;
            } elseif ($c->billing_type === 'diaria') {
                $valor = (float) $c->price * count($dias);
            } else {
                $semEstimativa[$c->asset_id] = true;

                continue;
            }
            $receita[$c->asset_id] = ($receita[$c->asset_id] ?? 0) + $valor;
        }

        $linhas = [];
        $vistos = [];
        $ignorados = 0;
        foreach ($ativos as $a) {
            $pat = mb_strtoupper(preg_replace('/\s+/', ' ', trim((string) $a->patrimonio)));
            if ($pat === '') {
                $ignorados++;

                continue;
            }
            if (isset($vistos[$pat])) {
                $ignorados++;
                $avisos[] = "Nº Patrimônio repetido no cadastro de ativos ({$pat}): só o primeiro foi exportado.";

                continue;
            }
            $vistos[$pat] = true;
            if (count($linhas) >= self::MAX_LINHAS) {
                $avisos[] = 'A planilha comporta '.self::MAX_LINHAS.' equipamentos por envio: os demais ficaram de fora.';
                break;
            }

            $locado = array_keys($diasLocado[$a->id] ?? []);
            $indisp = count(array_diff(array_keys($diasOs[$a->id] ?? []), $locado)); // dia locado não conta como parado
            $ano = (int) $a->manufacturing_year;
            $temContrato = isset($diasLocado[$a->id]);

            $linhas[] = [
                'Nº Patrimônio' => $pat, 'Nome / Modelo' => (string) $a->name, 'Categoria' => $a->asset_category,
                'Ano de Fabricação' => ($ano >= 1950 && $ano <= $anoAtual) ? $ano : null,
                'Valor do Equipamento (R$)' => $a->acquisition_value !== null ? round((float) $a->acquisition_value, 2) : null,
                'Receita no Mês (R$)' => ! $temContrato ? 0.0 : (isset($semEstimativa[$a->id]) ? null : round($receita[$a->id] ?? 0, 2)),
                'Custo de Manutenção no Mês (R$)' => round($custoMes[$a->id] ?? 0, 2), 'Custo de Manutenção Acumulado (R$)' => round($custoAcum[$a->id] ?? 0, 2),
                'OS Corretivas no Mês' => $corretivas[$a->id] ?? 0, 'OS Preventivas no Mês' => $preventivas[$a->id] ?? 0, 'Corretivas Reincidentes no Mês' => $reincidencias[$a->id] ?? 0,
                'Dias Locado no Mês' => count($locado), 'Dias Indisponível no Mês' => $indisp,
            ];
        }

        if ($ignorados > 0) {
            $avisos[] = "{$ignorados} ativo(s) ficaram de fora por não terem Nº Patrimônio (ou por repetirem um já exportado).";
        }
        if ($semEstimativa !== []) {
            $avisos[] = count($semEstimativa).' equipamento(s) têm contrato por hora ou por franquia sem medição aprovada no mês: a receita deles ficou em branco.';
        }
        if (collect($linhas)->every(fn ($l) => $l['Valor do Equipamento (R$)'] === null)) {
            $avisos[] = 'Nenhum ativo tem valor de aquisição cadastrado: sem ele não há retorno nem a regra de trocar ou vender.';
        }

        return ['linhas' => $linhas, 'data_referencia' => $mes->copy()->endOfMonth()->format('d/m/Y'), 'ignorados' => $ignorados, 'avisos' => $avisos];
    }

    /** @return list<string> as datas (Y-m-d) de `$de` a `$ate`, as duas incluídas. */
    private function dias(Carbon $de, Carbon $ate): array
    {
        $out = [];
        for ($d = $de->copy()->startOfDay(); $d->lte($ate); $d->addDay()) {
            $out[] = $d->toDateString();
        }

        return $out;
    }

    /** O arquivo .xlsx pronto para importar: aba "Dados" só com a data, "Equipamentos" preenchida e "Instruções". */
    public function escrever(array $resultado, string $destino): void
    {
        ModeloExcel::escrever($destino, [
            'data_referencia' => $resultado['data_referencia'], 'linhas' => $resultado['linhas'],
            'aviso' => implode(' ', $resultado['avisos']),
        ]);
    }

    /** Colunas que a exportação preenche (para teste de contrato com o catálogo). */
    public static function colunas(): array
    {
        return array_column(Catalogo::equipamentos(), 'title');
    }
}
