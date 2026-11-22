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
        '2026-08-31' => ['Total de Equipamentos' => 120, 'Equipamentos Locados' => 70, 'Equipamentos Indisponíveis' => 26, 'Valor da Frota (R$)' => 9000000,
            'Receita do Mês (R$)' => 380000, 'Custo de Manutenção do Mês (R$)' => 52000, 'Demais Custos do Mês (R$)' => 230000, 'Valor em Atraso (R$)' => 15000,
            'Propostas Emitidas no Mês' => 30, 'Propostas Ganhas no Mês' => 6, 'Receita do Maior Cliente (%)' => 32,
            'OS Corretivas no Mês' => 58, 'OS Preventivas no Mês' => 10, 'Tempo Médio de Reparo (horas)' => 40, 'Técnicos de Manutenção' => 5, 'Entregas no Prazo (%)' => 72],
        '2026-09-30' => ['Total de Equipamentos' => 120, 'Equipamentos Locados' => 78, 'Equipamentos Indisponíveis' => 18, 'Valor da Frota (R$)' => 9000000,
            'Receita do Mês (R$)' => 420000, 'Custo de Manutenção do Mês (R$)' => 44000, 'Demais Custos do Mês (R$)' => 225000, 'Valor em Atraso (R$)' => 31000,
            'Propostas Emitidas no Mês' => 36, 'Propostas Ganhas no Mês' => 10, 'Receita do Maior Cliente (%)' => 27,
            'OS Corretivas no Mês' => 46, 'OS Preventivas no Mês' => 22, 'Tempo Médio de Reparo (horas)' => 28, 'Técnicos de Manutenção' => 6, 'Entregas no Prazo (%)' => 81],
        '2026-10-31' => ['Total de Equipamentos' => 122, 'Equipamentos Locados' => 88, 'Equipamentos Indisponíveis' => 11, 'Valor da Frota (R$)' => 9300000,
            'Receita do Mês (R$)' => 468000, 'Custo de Manutenção do Mês (R$)' => 38000, 'Demais Custos do Mês (R$)' => 228000, 'Valor em Atraso (R$)' => 52000,
            'Propostas Emitidas no Mês' => 40, 'Propostas Ganhas no Mês' => 15, 'Receita do Maior Cliente (%)' => 22,
            'OS Corretivas no Mês' => 34, 'OS Preventivas no Mês' => 36, 'Tempo Médio de Reparo (horas)' => 18, 'Técnicos de Manutenção' => 6, 'Entregas no Prazo (%)' => 90],
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
        foreach (self::MESES as $data => $campos) {
            $arquivo = $this->planilha($data, $campos);
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

    /** Gera a planilha no mesmo formato do modelo que o cliente baixa (uma linha de dados). */
    private function planilha(string $data, array $campos): string
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
        $w->close();

        return $caminho;
    }
}
