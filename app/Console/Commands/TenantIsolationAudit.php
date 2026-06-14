<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use ReflectionClass;

class TenantIsolationAudit extends Command
{
    protected $signature = 'tenant:audit {--strict : Retorna exit 1 se houver qualquer violacao (uso em CI)}';

    protected $description = 'Audita o isolamento multi-tenant: todo model cuja tabela tem tenant_id deve aplicar o global scope tenant, salvo allowlist explicita e documentada.';

    /**
     * Models intencionalmente NAO escopados por global scope.
     * Cada isencao exige que o isolamento seja garantido por OUTRO meio (filtro no Resource),
     * e a justificativa fica registrada aqui de proposito.
     */
    private const EXEMPT = [
        \App\Models\User::class => 'Login e painel central precisam enxergar usuarios de todos os tenants; isolamento feito no UserResource via Auth::user()->tenant_id.',
        \App\Models\Role::class => 'Spatie Permission roda com teams mode DESLIGADO e resolve roles/permissoes globalmente; um global scope quebraria a resolucao de permissoes. Isolamento feito no RoleResource.',
    ];

    public function handle(): int
    {
        // Verdade do schema (somente leitura).
        $tenantTables = collect(DB::select(
            "SELECT table_name FROM information_schema.columns WHERE column_name = 'tenant_id' AND table_schema = 'public'"
        ))->pluck('table_name')->flip();

        $existingTables = collect(DB::select(
            "SELECT table_name FROM information_schema.tables WHERE table_schema = 'public'"
        ))->pluck('table_name')->flip();

        $rows = [];
        $violations = [];
        $warnings = [];

        foreach (glob(app_path('Models') . '/*.php') as $file) {
            $class = 'App\\Models\\' . basename($file, '.php');
            if (! class_exists($class)) {
                continue;
            }

            $ref = new ReflectionClass($class);
            if ($ref->isAbstract() || ! $ref->isSubclassOf(Model::class)) {
                continue;
            }

            try {
                $model = new $class;
            } catch (\Throwable $e) {
                $rows[] = [class_basename($class), '?', '?', '?', 'ERRO: ' . $e->getMessage()];
                $violations[] = "{$class}: nao instanciavel ({$e->getMessage()})";
                continue;
            }

            $table = $model->getTable();

            // Tabela inexistente -> model morto/incompleto. AVISO (nao falha o build):
            // sem tabela nao ha dado a vazar, mas e divida tecnica visivel (ex.: Resource que daria 500).
            if (! $existingTables->has($table)) {
                $rows[] = [class_basename($class), $table, 'n/a', 'n/a', 'TABELA INEXISTENTE (morto?)'];
                $warnings[] = "{$class}: aponta para a tabela '{$table}', que nao existe no banco (model morto/incompleto).";
                continue;
            }

            $hasColumn = $tenantTables->has($table);

            $hasScope = false;
            foreach (array_keys($model->getGlobalScopes()) as $scope) {
                if (stripos((string) $scope, 'tenant') !== false) {
                    $hasScope = true;
                    break;
                }
            }

            $isExempt = array_key_exists($class, self::EXEMPT);

            if ($hasColumn && ! $hasScope && ! $isExempt) {
                $status = 'VAZAMENTO (coluna sem scope)';
                $violations[] = "{$class}: tabela '{$table}' tem tenant_id, mas o model nao aplica o global scope 'tenant' e nao esta na allowlist.";
            } elseif ($hasColumn && ! $hasScope && $isExempt) {
                $status = 'isento (documentado)';
            } elseif (! $hasColumn && $hasScope) {
                $status = 'SCOPE SEM COLUNA (quebra)';
                $violations[] = "{$class}: aplica o global scope 'tenant', mas a tabela '{$table}' nao tem coluna tenant_id.";
            } elseif ($hasColumn && $hasScope) {
                $status = 'ok';
            } else {
                $status = '- (global, sem tenant)';
            }

            $rows[] = [
                class_basename($class),
                $table,
                $hasColumn ? 'sim' : 'nao',
                $hasScope ? 'sim' : 'nao',
                $status,
            ];
        }

        usort($rows, fn ($a, $b) => [$a[4], $a[0]] <=> [$b[4], $b[0]]);
        $this->table(['Model', 'Tabela', 'Coluna', 'Scope', 'Status'], $rows);

        if (! empty($warnings)) {
            $this->newLine();
            $this->warn(count($warnings) . ' aviso(s) (NAO falham o build):');
            foreach ($warnings as $w) {
                $this->line('  ~ ' . $w);
            }
        }

        if (empty($violations)) {
            $this->newLine();
            $this->info('Isolamento multi-tenant OK: ' . count($rows) . ' models auditados, 0 violacoes.');
            return self::SUCCESS;
        }

        $this->newLine();
        $this->error(count($violations) . ' violacao(oes) encontrada(s):');
        foreach ($violations as $v) {
            $this->line('  - ' . $v);
        }

        if ($this->option('strict')) {
            return self::FAILURE;
        }

        $this->newLine();
        $this->comment('Rode com --strict para falhar o build (exit 1) em CI.');
        return self::SUCCESS;
    }
}
