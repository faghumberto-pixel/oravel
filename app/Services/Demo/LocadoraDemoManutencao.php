<?php

namespace App\Services\Demo;

use App\Models\Tenant;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Enche a MANUTENCAO da Locadora Demo (so' ela) com atividade recente: agenda de hoje, OS concluidas nos ultimos 60 dias (com SLA,
 * deslocamento, retrabalho e defeitos), OS esperando peca, tecnicos com carga e equipamentos parados. O historico de 18 meses que
 * LocadoraDemoService cria nao e' tocado. Idempotente: apaga so' as OS com prefixo DEMO-MAN- (e o que pende delas) e recria, de forma
 * deterministica. Insere direto nas tabelas (sem observers: nao manda e-mail nem notificacao).
 */
class LocadoraDemoManutencao
{
    private const SEMENTE = 20261112;

    private const PREFIXO = 'DEMO-MAN-';

    private const DEFEITOS = [
        'Vazamento no sistema hidráulico', 'Bateria não segura carga', 'Falha no painel de comando', 'Pneu ou rodízio danificado', 'Superaquecimento do motor',
        'Ruído anormal na transmissão', 'Mangueira rompida', 'Sensor de inclinação com erro', 'Falha na partida', 'Desgaste de freio',
    ];

    private const DESCRICOES_PREVENTIVA = ['Revisão preventiva 250h', 'Inspeção e lubrificação geral', 'Troca de óleo e filtros', 'Teste de carga e calibração', 'Checklist de segurança NR-13'];

    private string $tenantId;

    private Carbon $agora;

    /** @var list<string> */
    private array $tecnicos = [];

    /** @var list<array{id: string, client: ?string}> */
    private array $ativos = [];

    /** @var list<string> */
    private array $defeitos = [];

    /** @var array<string, float> id do ativo => quanto custo ainda pode receber sem passar de 38% do valor (nao vira "troque ou venda") */
    private array $folga = [];

    private int $seq = 0;

    public function popular(Tenant $tenant): array
    {
        if (! $tenant->is_demo) {
            throw new \RuntimeException('A manutenção só é populada na empresa de demonstração.');
        }

        $this->tenantId = (string) $tenant->id;
        $this->agora = Carbon::now();
        $this->seq = 0;
        mt_srand(self::SEMENTE);

        try {
            return DB::transaction(function () {
                $this->limpar();
                $this->tecnicos = DB::table('users')->where('tenant_id', $this->tenantId)->where('role', 'tecnico')->orderBy('created_at')->pluck('id')->map(fn ($v) => (string) $v)->all();
                $this->ativos = DB::table('assets')->where('tenant_id', $this->tenantId)->where('is_vehicle', false)->get(['id', 'client_id'])->map(fn ($a) => ['id' => (string) $a->id, 'client' => $a->client_id ? (string) $a->client_id : null])->all();
                $custos = DB::table('maintenance_orders')->where('tenant_id', $this->tenantId)->selectRaw('asset_id, sum(total_order_cost) as total')->groupBy('asset_id')->pluck('total', 'asset_id');
                foreach (DB::table('assets')->where('tenant_id', $this->tenantId)->get(['id', 'acquisition_value']) as $a) {
                    $this->folga[(string) $a->id] = max(0.0, (float) $a->acquisition_value * 0.38 - (float) ($custos[$a->id] ?? 0));
                }
                if (! $this->tecnicos || ! $this->ativos) {
                    throw new \RuntimeException('Crie a demo antes (demo:alec-criar): faltam técnicos ou equipamentos.');
                }

                foreach (self::DEFEITOS as $d) {
                    $this->defeitos[] = $this->inserir('reported_problems', ['description' => $d, 'is_active' => true]);
                }

                $hoje = $this->agenda();
                $concluidas = $this->concluidas();
                $abertas = $this->abertas();
                $parados = $this->parados();
                $retrabalho = $this->retrabalho();

                return ['agenda_de_hoje' => $hoje, 'concluidas_60_dias' => $concluidas, 'abertas_e_aguardando_peca' => $abertas, 'equipamentos_parados' => $parados, 'retrabalhos' => $retrabalho, 'defeitos_cadastrados' => count($this->defeitos)];
            });
        } finally {
            mt_srand();
        }
    }

    private function limpar(): void
    {
        $os = DB::table('maintenance_orders')->where('tenant_id', $this->tenantId)->where('os_number', 'like', self::PREFIXO.'%')->pluck('id');
        DB::table('parts_requests')->whereIn('maintenance_order_id', $os)->delete();
        DB::table('asset_downtime_events')->where('tenant_id', $this->tenantId)->delete();
        DB::table('maintenance_orders')->whereIn('id', $os)->delete();
        DB::table('reported_problems')->where('tenant_id', $this->tenantId)->delete();
    }

    private function inserir(string $tabela, array $dados, ?Carbon $criadoEm = null): string
    {
        $id = (string) Str::uuid();
        $quando = $criadoEm ?? $this->agora;
        DB::table($tabela)->insert(array_merge(['id' => $id, 'tenant_id' => $this->tenantId], $dados, ['created_at' => $quando, 'updated_at' => $quando]));

        return $id;
    }

    private function tecnico(int $i): string
    {
        return $this->tecnicos[$i % count($this->tecnicos)];
    }

    /** Sorteia um equipamento com folga de custo (o sorteio e' deterministico); sem folga nenhuma, devolve o de maior folga. */
    private function ativo(float $custo = 600): array
    {
        for ($t = 0; $t < 12; $t++) {
            $a = $this->ativos[mt_rand(0, count($this->ativos) - 1)];
            if (($this->folga[$a['id']] ?? 0) >= $custo) {
                return $a;
            }
        }
        usort($this->ativos, fn ($x, $y) => ($this->folga[$y['id']] ?? 0) <=> ($this->folga[$x['id']] ?? 0));

        return $this->ativos[0];
    }

    /** Desconta o custo de uma OS da folga do equipamento. */
    private function gastar(string $assetId, float $custo): void
    {
        $this->folga[$assetId] = max(0.0, ($this->folga[$assetId] ?? 0) - $custo);
    }

    /** Insere uma OS e devolve o id. $extra sobrescreve qualquer campo. */
    private function os(string $tipo, string $status, string $interno, Carbon $criado, array $extra = []): string
    {
        $this->seq++;
        $ativo = $this->ativo((float) ($extra['total_order_cost'] ?? 300));
        $this->gastar($ativo['id'], (float) ($extra['total_order_cost'] ?? 0));
        $externo = ($extra['km_traveled'] ?? 0) > 0;

        return $this->inserir('maintenance_orders', array_merge([
            'os_number' => self::PREFIXO.str_pad((string) $this->seq, 5, '0', STR_PAD_LEFT),
            'asset_id' => $ativo['id'], 'client_id' => $ativo['client'], 'technician_id' => $this->tecnico($this->seq),
            'maintenance_type' => $tipo, 'status' => $status, 'internal_status' => $interno, 'priority' => $tipo === 'Emergência' ? 'alta' : (['baixa', 'media', 'media'][mt_rand(0, 2)]),
            'service_type' => $externo ? 'Externo' : 'Interno', 'workflow_status' => 'scheduled', 'total_time_seconds' => 0, 'is_rework' => false,
            'description' => $tipo === 'Preventiva' ? self::DESCRICOES_PREVENTIVA[mt_rand(0, 4)] : self::DEFEITOS[mt_rand(0, 9)],
            'technical_notes' => 'Ordem de serviço fictícia da Locadora Demo.',
        ], $extra), $criado);
    }

    /** Visitas de hoje: algumas concluidas, outras em atendimento, outras ainda pendentes. */
    private function agenda(): int
    {
        $plano = [[8, 'concluida', 'Preventiva'], [9, 'concluida', 'Corretiva'], [11, 'concluida', 'Preventiva'], [13, 'andamento', 'Corretiva'], [14, 'andamento', 'Emergência'], [16, 'pendente', 'Preventiva'], [17, 'pendente', 'Corretiva']];
        foreach ($plano as $i => [$hora, $situacao, $tipo]) {
            $quando = $this->agora->copy()->setTime($hora, $i % 2 === 0 ? 0 : 30);
            $criado = $this->agora->copy()->subDays(mt_rand(1, 3));
            $km = $i % 2 === 0 ? mt_rand(18, 90) : 0;
            $labor = mt_rand(120, 380);
            $material = mt_rand(40, 220);
            $logistica = round($km * 2.2, 2);
            $extra = ['scheduled_at' => $quando, 'km_traveled' => $km, 'logistics_cost' => $logistica, 'reported_problem_id' => $tipo === 'Preventiva' ? null : $this->defeitos[mt_rand(0, 9)]];
            if ($situacao === 'concluida') {
                $extra += ['started_at' => $quando, 'finished_at' => $quando->copy()->addHours(2), 'hours_spent' => 2, 'total_time_seconds' => 7200, 'labor_cost' => $labor, 'material_cost' => $material, 'total_order_cost' => round($labor + $material + $logistica, 2), 'sla_target_minutes' => 1440];
                $this->os($tipo, 'Concluída', 'concluido', $criado, $extra);
            } elseif ($situacao === 'andamento') {
                $extra += ['started_at' => $quando, 'labor_cost' => $labor, 'total_order_cost' => round($labor + $logistica, 2)];
                $this->os($tipo, 'Em Andamento', 'em_manutencao', $criado, $extra);
            } else {
                $this->os($tipo, 'Pendente', 'agendado', $criado, array_merge($extra, ['km_traveled' => 0, 'logistics_cost' => 0]));
            }
        }

        return count($plano);
    }

    /** OS concluidas dos ultimos 60 dias, com SLA, deslocamento, retrabalho e defeitos (alimenta quase todos os cards). */
    private function concluidas(): int
    {
        $n = 0;
        // [quantidade, dia minimo atras, dia maximo atras]: muito no mes corrente e nos ultimos 30 dias, e um mes anterior para comparar.
        foreach ([[16, 0, 5], [16, 6, 30], [24, 31, 60]] as [$qtd, $de, $ate]) {
            for ($k = 0; $k < $qtd; $k++) {
                $fim = $this->agora->copy()->subDays(mt_rand($de, $ate))->setTime(mt_rand(9, 18), mt_rand(0, 59));
                if ($fim->gt($this->agora)) {
                    $fim = $this->agora->copy()->subHours(2);
                }
                $roll = mt_rand(1, 100);
                $tipo = $roll <= 50 ? 'Preventiva' : ($roll <= 88 ? 'Corretiva' : 'Emergência');
                $sla = [480, 1440, 2880][mt_rand(0, 2)];
                $dentro = mt_rand(1, 100) <= 82;
                $minutos = (int) round($sla * ($dentro ? mt_rand(35, 95) / 100 : mt_rand(110, 170) / 100));
                $criado = $fim->copy()->subMinutes($minutos);
                // Atraso no primeiro atendimento: piora um pouco nas semanas antigas, melhora nas recentes
                $lagHoras = max(0.5, mt_rand(8, 60) / 10 * ($de > 30 ? 2.2 : ($de > 5 ? 1.4 : 0.8)));
                $inicio = $criado->copy()->addMinutes((int) ($lagHoras * 60))->min($fim->copy()->subMinutes(30));
                $externo = mt_rand(1, 100) <= 55;
                $km = $externo ? mt_rand(20, 180) : 0;
                $mes = $de > 30 ? 1.25 : 1.0; // mes anterior mais caro: o custo medio do mes corrente mostra melhora
                $labor = round(mt_rand(120, 380) * $mes, 2);
                $material = $tipo === 'Preventiva' ? mt_rand(40, 220) : mt_rand(120, 760) * $mes;
                $logistica = round($km * 2.2, 2);
                $rework = $de <= 30 && $tipo !== 'Preventiva' && mt_rand(1, 100) <= 14;

                $this->os($tipo, 'Concluída', 'concluido', $criado, [
                    'scheduled_at' => $criado->copy()->addHours(2), 'started_at' => $inicio, 'finished_at' => $fim, 'hours_spent' => round($minutos / 60, 1), 'total_time_seconds' => $minutos * 60,
                    'sla_target_minutes' => $sla, 'km_traveled' => $km, 'logistics_cost' => $logistica, 'labor_cost' => $labor, 'material_cost' => $material,
                    'total_order_cost' => round($labor + $material + $logistica, 2), 'is_rework' => $rework,
                    'reported_problem_id' => $tipo === 'Preventiva' ? null : $this->defeitos[$this->defeitoPonderado()],
                ]);
                $n++;
            }
        }

        return $n;
    }

    /** Alguns defeitos aparecem muito mais que outros (ranking de defeitos frequentes). */
    private function defeitoPonderado(): int
    {
        $r = mt_rand(1, 100);

        return match (true) {
            $r <= 28 => 0, $r <= 50 => 1, $r <= 66 => 2, $r <= 78 => 3, $r <= 87 => 4, default => mt_rand(5, 9),
        };
    }

    /** OS abertas: esperando peca (com pedido de peca) e outras em atendimento, para a carga por tecnico e "Aguardando peca". */
    private function abertas(): int
    {
        $materiais = DB::table('materials')->where('tenant_id', $this->tenantId)->orderBy('created_at')->get(['id', 'unit_cost']);
        $n = 0;

        foreach ([[2, 'pedida'], [4, 'pendente'], [6, 'pedida'], [9, 'pendente']] as $i => [$dias, $pedido]) {
            $quando = $this->agora->copy()->subDays($dias)->setTime(9, 15);
            $labor = mt_rand(150, 400);
            $os = $this->os($i === 1 ? 'Emergência' : 'Corretiva', 'Em Andamento', 'aguardando_peca', $quando, [
                'started_at' => $quando->copy()->addHours(2), 'labor_cost' => $labor, 'total_order_cost' => $labor,
                'reported_problem_id' => $this->defeitos[[0, 1, 3, 8][$i]],
            ]);
            if ($materiais->isNotEmpty()) {
                $m = $materiais[($i * 5 + 3) % $materiais->count()];
                DB::table('parts_requests')->insert(['maintenance_order_id' => $os, 'material_id' => $m->id, 'quantity' => mt_rand(1, 4), 'status' => $pedido, 'cost_at_time' => $m->unit_cost, 'tenant_id' => $this->tenantId, 'created_at' => $quando, 'updated_at' => $quando]);
            }
            $n++;
        }

        // Outras em atendimento (distribuidas entre os tecnicos) e agendadas para os proximos dias
        foreach ([1, 1, 2, 3] as $i => $dias) {
            $quando = $this->agora->copy()->subDays($dias)->setTime(10, 40);
            $labor = mt_rand(120, 380);
            $this->os($i % 2 === 0 ? 'Corretiva' : 'Preventiva', 'Em Andamento', 'em_manutencao', $quando, [
                'started_at' => $quando->copy()->addHour(), 'labor_cost' => $labor, 'total_order_cost' => $labor,
                'reported_problem_id' => $i % 2 === 0 ? $this->defeitos[mt_rand(0, 4)] : null,
            ]);
            $n++;
        }
        foreach ([1, 1, 2, 3] as $dias) {
            $this->os('Preventiva', 'Pendente', 'agendado', $this->agora->copy()->subDay(), ['scheduled_at' => $this->agora->copy()->addDays($dias)->setTime(mt_rand(8, 15), 0)]);
            $n++;
        }

        return $n;
    }

    /** Equipamentos parados: os que estao em manutencao ganham um evento de parada aberto (alguns ha mais de 5 dias). */
    private function parados(): int
    {
        $assets = DB::table('assets')->where('tenant_id', $this->tenantId)->where('status', 'manutencao')->orderBy('created_at')->pluck('id');
        foreach ($assets as $i => $id) {
            $inicio = $this->agora->copy()->subDays([8, 3, 6, 1][$i % 4]);
            $this->inserir('asset_downtime_events', ['asset_id' => $id, 'started_at' => $inicio, 'reason' => 'manutencao', 'notes' => 'Parado para manutenção corretiva.'], $inicio);
        }

        return $assets->count();
    }

    private function retrabalho(): int
    {
        return (int) DB::table('maintenance_orders')->where('tenant_id', $this->tenantId)->where('is_rework', true)->where('os_number', 'like', self::PREFIXO.'%')->count();
    }
}
