<?php

namespace App\Services\Demo;

use App\Domain\Intelligence\Models\IntelligenceSnapshot;
use App\Domain\Intelligence\Services\ImportadorExcel;
use App\Domain\Intelligence\Support\Catalogo;
use App\Models\Plan;
use App\Models\Tenant;
use App\Services\TenantProvisioner;
use App\Support\Pacotes;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use RuntimeException;

/**
 * Empresa de teste do Oravel Intelligence: um tenant marcado is_demo, com contrato do pacote Intelligence, um administrador
 * e 3 fotografias mensais passando pelo importador de verdade (planilha .xlsx), para ver dashboard, evolução e o chat de IA.
 * Não mexe em nenhum tenant real: recusa-se se o slug existir sem a marca is_demo.
 */
class IntelligenceDemoService
{
    public const SLUG = 'intelligence-demo';

    public const NOME = 'Intelligence Demo';

    public const PLANO_NOME = 'Intelligence Demo (contrato de teste)';

    public const EMAIL = 'intelligence.demo@oravel.test';

    /** Evolução de propósito: frota e oficina melhoram, a inadimplência piora (o dashboard precisa mostrar os dois sentidos). */
    private const MESES = [
        '2026-08-31' => ['Demais Custos do Mês (R$)' => 230000, 'Valor em Atraso (R$)' => 15000,
            'Propostas Emitidas no Mês' => 30, 'Propostas Ganhas no Mês' => 6, 'Receita do Maior Cliente (%)' => 32,
            'Tempo Médio de Reparo (horas)' => 40, 'Técnicos de Manutenção' => 5, 'Entregas no Prazo (%)' => 72],
        '2026-09-30' => ['Demais Custos do Mês (R$)' => 225000, 'Valor em Atraso (R$)' => 31000,
            'Propostas Emitidas no Mês' => 36, 'Propostas Ganhas no Mês' => 10, 'Receita do Maior Cliente (%)' => 27,
            'Tempo Médio de Reparo (horas)' => 28, 'Técnicos de Manutenção' => 6, 'Entregas no Prazo (%)' => 81],
        '2026-10-31' => ['Demais Custos do Mês (R$)' => 228000, 'Valor em Atraso (R$)' => 52000,
            'Propostas Emitidas no Mês' => 40, 'Propostas Ganhas no Mês' => 15, 'Receita do Maior Cliente (%)' => 22,
            'Tempo Médio de Reparo (horas)' => 18, 'Técnicos de Manutenção' => 6, 'Entregas no Prazo (%)' => 90],
    ];

    private const PERFIL = ['Estado (UF)' => 'SP', 'Porte' => 'Médio', 'Segmento' => 'Locação de equipamentos'];

    public function tenant(): ?Tenant
    {
        return Tenant::withoutGlobalScopes()->where('slug', self::SLUG)->first();
    }

    /** @return array{email: string, senha: string, snapshots: int, indice_geral: ?float} */
    public function criar(string $senha): array
    {
        $existente = $this->tenant();
        if ($existente && ! $existente->is_demo) {
            throw new RuntimeException('Já existe uma empresa com o slug "'.self::SLUG.'" que NÃO está marcada como demo. Nada foi feito.');
        }
        if ($existente) {
            $this->apagar();
        }

        // Formato MAPA (chave => true), o mesmo que a Central grava: o saas:sync-modules do deploy acrescenta chaves novas como
        // false, e num contrato em formato de lista isso derrubava os módulos.
        $features = collect(Pacotes::features('intelligence'))->mapWithKeys(fn ($c) => [$c => true])->all();

        $tenant = DB::transaction(function () use ($features, $senha) {
            $plano = Plan::create([
                'name' => self::PLANO_NOME, 'pacote' => 'intelligence', 'price' => 0, 'base_price' => 0, 'level' => 1,
                'billing_cycle' => 'monthly', 'is_active' => true, 'features' => $features,
            ]);
            $tenant = Tenant::create(['name' => self::NOME, 'slug' => self::SLUG, 'status' => 'active', 'plan_id' => $plano->id, 'is_demo' => true]);
            $user = TenantProvisioner::provision($tenant, ['name' => 'Gestor Demo', 'email' => self::EMAIL, 'password' => $senha]);
            $user->forceFill(['is_approved' => true])->save();

            return $tenant;
        });

        $importador = app(ImportadorExcel::class);
        $ultimo = null;
        $mes = 0;
        foreach (self::MESES as $data => $campos) {
            $arquivo = $this->planilha($data, $campos, $this->frota($mes++, $data));
            try {
                $r = $importador->importar($arquivo, $tenant, null, false, 'demo-'.$data.'.xlsx');
            } finally {
                @unlink($arquivo);
            }
            if (! $r['ok']) {
                throw new RuntimeException("Importação demo de {$data} falhou: ".implode(' | ', $r['erros']));
            }
            $ultimo = $r['diagnostico']['geral']['pontuacao'] ?? null;
        }

        return [
            'email' => self::EMAIL, 'senha' => $senha,
            'snapshots' => IntelligenceSnapshot::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count(),
            'indice_geral' => $ultimo,
        ];
    }

    /** @return array{apagado: bool, linhas: int} */
    public function apagar(): array
    {
        $tenant = $this->tenant();
        if (! $tenant) {
            return ['apagado' => false, 'linhas' => 0];
        }
        if (! $tenant->is_demo) {
            throw new RuntimeException('A empresa "'.self::SLUG.'" existe mas NÃO está marcada como demo: nada foi apagado.');
        }

        $id = (string) $tenant->id;
        $planoId = (string) $tenant->plan_id;
        $usuarios = DB::table('users')->where('tenant_id', $id)->pluck('id')->map(fn ($v) => (string) $v)->all();
        $linhas = 0;

        DB::transaction(function () use ($id, $planoId, $usuarios, &$linhas) {
            foreach (['model_has_roles', 'model_has_permissions', 'notifications'] as $tabela) {
                if ($usuarios && Schema::hasTable($tabela)) {
                    $coluna = $tabela === 'notifications' ? 'notifiable_id' : 'model_id';
                    $linhas += DB::table($tabela)->whereIn($coluna, $usuarios)->delete();
                }
            }
            if (Schema::hasTable('activity_log')) {
                $linhas += DB::table('activity_log')->whereIn('subject_id', array_merge($usuarios, [$id, $planoId]))->delete();
            }

            // Tabelas com tenant_id, em varias passadas ate resolver as chaves estrangeiras (cada DELETE em savepoint).
            $pendentes = collect(Schema::getTables())->map(fn ($t) => $t['name'])->reject(fn ($n) => $n === 'tenants')
                ->filter(fn ($n) => Schema::hasColumn($n, 'tenant_id'))->values()->all();

            for ($passada = 0; $passada < 12 && $pendentes; $passada++) {
                $restantes = [];
                foreach ($pendentes as $tabela) {
                    try {
                        $linhas += DB::transaction(fn () => DB::table($tabela)->where('tenant_id', $id)->delete());
                    } catch (QueryException) {
                        $restantes[] = $tabela;
                    }
                }
                if (count($restantes) === count($pendentes)) {
                    break;
                }
                $pendentes = $restantes;
            }

            DB::table('tenants')->where('id', $id)->delete();
            $linhas++;

            if ($planoId !== '' && ! DB::table('tenants')->where('plan_id', $planoId)->exists()) {
                $linhas += DB::table('plans')->where('id', $planoId)->where('name', self::PLANO_NOME)->delete();
            }
        });

        return ['apagado' => true, 'linhas' => $linhas];
    }

    /**
     * Frota de exemplo (120, 120 e 122 equipamentos), determinística: o mesmo patrimônio em todos os meses, com perfis fixos
     * (os 6 primeiros são "trocar ou vender", depois 10 "revisar", 15 subutilizados e o resto saudável). Frota e oficina melhoram de agosto a outubro.
     *
     * @return list<array<string, mixed>> título da coluna => valor
     */
    private function frota(int $mes, string $data): array
    {
        $dias = (int) Carbon::parse($data)->daysInMonth;
        $n = [120, 120, 122][$mes] ?? 120;
        $cats = [['Empilhadeira', 185000], ['Gerador', 120000], ['Plataforma elevatória', 240000], ['Compressor', 70000]];
        $r = fn (int $i, string $k, float $min, float $max): float => $min + (crc32("{$i}-{$k}") % 1000) / 1000 * ($max - $min);
        $linhas = [];

        for ($i = 0; $i < $n; $i++) {
            [$cat, $base] = $cats[$i % 4];
            $valor = round($base * $r($i, 'v', .8, 1.2), -3);
            $perfil = $i < 6 ? 'trocar' : ($i < 16 ? 'revisar' : ($i < 31 ? 'ocioso' : 'bom'));
            // movimento entre os meses (para a tela mostrar tendência): 16-19 saem da ociosidade em outubro; 20-23 pioram em outubro
            $ativouEmOutubro = $mes === 2 && $i >= 16 && $i <= 19;
            $pioraEmOutubro = $mes === 2 && $i >= 20 && $i <= 23;
            if ($ativouEmOutubro) {
                $perfil = 'bom';
            }
            // melhora ao longo dos meses: mais dias locado e menos dias parado (exceto os ociosos, que seguem parados sem contrato)
            $fator = [0.82, 0.94, 1.04][$mes];
            $locado = match ($perfil) {
                'ocioso' => (int) round($r($i, 'l', 2, 8)),
                'trocar' => (int) round($r($i, 'l', 6, 16) * $fator),
                'revisar' => (int) round($r($i, 'l', 12, 22) * $fator),
                default => (int) round($r($i, 'l', 18, 27) * $fator),
            };
            if ($ativouEmOutubro) {
                $locado = (int) round($r($i, 'l2', 15, 22));
            }
            $indisp = match ($perfil) {
                'ocioso' => (int) round($r($i, 'i', 0, 2)),
                'trocar' => (int) round($r($i, 'i', 8, 14) * [1.2, 1.0, 0.85][$mes]),
                'revisar' => (int) round($r($i, 'i', 3, 8) * [1.3, 1.0, 0.7][$mes]),
                default => (int) round($r($i, 'i', 0, 3) * [1.6, 1.0, 0.5][$mes]),
            };
            if ($pioraEmOutubro) {
                $indisp = (int) round($r($i, 'i2', 10, 14));
                $locado = (int) round($r($i, 'l3', 6, 12));
            }
            $locado = min($locado, $dias);
            $indisp = min($indisp, max(0, $dias - $locado));
            [$corMin, $corMax, $custoPct, $acumMin, $acumMax, $reincPct] = match ($perfil) {
                'trocar' => [3, 6, 0.020, 0.62, 0.80, 0.45],
                'revisar' => [2, 4, 0.012, 0.42, 0.56, 0.30],
                'ocioso' => [0, 1, 0.002, 0.05, 0.25, 0.0],
                default => [0, 2, 0.004, 0.05, 0.30, 0.10],
            };
            $corretivas = (int) round($r($i, 'c', $corMin, $corMax));
            if ($pioraEmOutubro) {
                $corretivas = (int) round($r($i, 'c2', 3, 5));
            }
            $reinc = (int) min($corretivas, round($corretivas * $reincPct * $r($i, 'r', .6, 1.4) * [1.25, 1.0, 0.8][$mes]));
            $acum = $valor * $r($i, 'a', $acumMin, $acumMax) * [0.93, 0.97, 1.0][$mes];
            if ($i >= 6 && $i <= 9) {
                // 4 equipamentos "em revisão" cruzam os 60% do valor em outubro: passam a "trocar ou vender" (piorando)
                $acum = $valor * 0.50 * [0.9, 1.0, 1.28][$mes];
            }

            $linhas[] = [
                'Nº Patrimônio' => sprintf('EMP-%03d', $i + 1), 'Nome / Modelo' => $cat.' '.sprintf('%03d', $i + 1), 'Categoria' => $cat, 'Ano de Fabricação' => 2010 + ($i % 14),
                'Valor do Equipamento (R$)' => $valor, 'Receita no Mês (R$)' => round($locado * $valor * 0.0012, 2), 'Custo de Manutenção no Mês (R$)' => round($valor * $custoPct * $r($i, 'k', .7, 1.3) * [1.15, 1.0, 0.88][$mes], 2),
                'Custo de Manutenção Acumulado (R$)' => round($acum, 2), 'OS Corretivas no Mês' => $corretivas, 'OS Preventivas no Mês' => (int) round($r($i, 'p', 0, 1.4) * [0.5, 1.0, 1.6][$mes]),
                'Corretivas Reincidentes no Mês' => $reinc, 'Dias Locado no Mês' => $locado, 'Dias Indisponível no Mês' => $indisp,
            ];
        }

        return $linhas;
    }

    /** Gera a planilha no mesmo formato do modelo que o cliente baixa (uma linha de dados). */
    private function planilha(string $data, array $campos, array $frota = []): string
    {
        $linha = array_merge(self::PERFIL, $campos, ['Data de Referência' => Carbon::parse($data)->format('d/m/Y')]);
        $titulos = array_column(Catalogo::campos(), 'title');

        $caminho = tempnam(sys_get_temp_dir(), 'intel-demo').'.xlsx';
        $w = new Writer;
        $w->openToFile($caminho);
        $w->getCurrentSheet()->setName(Catalogo::SHEET);
        $w->addRow(Row::fromValues(array_column(Catalogo::campos(), 'group')));
        $w->addRow(Row::fromValues($titulos));
        $w->addRow(Row::fromValues(array_map(fn ($t) => $linha[$t] ?? null, $titulos)));
        if ($frota !== []) {
            $w->addNewSheetAndMakeItCurrent()->setName(Catalogo::SHEET_EQUIPAMENTOS);
            $te = array_column(Catalogo::equipamentos(), 'title');
            $w->addRow(Row::fromValues($te));
            foreach ($frota as $linha) {
                $w->addRow(Row::fromValues(array_map(fn ($t) => $linha[$t] ?? null, $te)));
            }
        }
        $w->close();

        return $caminho;
    }
}
