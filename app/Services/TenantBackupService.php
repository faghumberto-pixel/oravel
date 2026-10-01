<?php

namespace App\Services;

use App\Models\DatabaseBackup;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * Backup POR CLIENTE (tenant): cada arquivo contém só os dados de um tenant, mais um arquivo
 * "plataforma" com o que não é de nenhum cliente. Substitui o dump geral único (02/10/2026).
 *
 * Como funciona: copia as linhas do tenant para um schema temporário (CREATE TABLE AS SELECT),
 * faz pg_dump só dele (formato COPY), troca o nome do schema por "public" e grava .sql.gz. Para
 * restaurar basta `gunzip -c arquivo.sql.gz | psql -d <banco>` num banco com o schema atual (o
 * arquivo desliga as checagens de chave estrangeira durante a carga, dentro de uma transação).
 *
 * Quais linhas são "do tenant":
 *  1. tabelas com coluna tenant_id (WHERE tenant_id = X) e a própria linha de `tenants`;
 *  2. tabelas filhas, ligadas por chave estrangeira a uma tabela do tenant (exceto `tenants` e
 *     `users`, que prenderiam tabelas da plataforma como anúncios e CRM);
 *  3. polimórficas: media, activity_log e notifications.
 * Tabelas efêmeras ou de segredo de infraestrutura (sessões, cache, filas, tokens) nunca entram.
 */
class TenantBackupService
{
    /** Nunca entram em backup (efêmeras ou segredos de infraestrutura). */
    public const EPHEMERAL = [
        'sessions', 'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs', 'password_reset_tokens',
        'personal_access_tokens', 'breezy_sessions', 'migrations', 'database_backups',
    ];

    /** Tratadas à parte: relação com o tenant é polimórfica (tipo + id), sem chave estrangeira. */
    public const POLYMORPHIC = ['media', 'activity_log', 'notifications'];

    /** Filhas do tenant só via `users` (a regra geral ignora `users` como pai). */
    public const USER_CHILDREN = ['user_specialty', 'exports'];

    /** @var array<string, mixed>|null */
    private ?array $catalog = null;

    public function baseDir(): string
    {
        return rtrim((string) config('oravel.backups.dir'), '/');
    }

    // ------------------------------------------------------------------ catálogo do banco

    /** @return array{tables: array<int, string>, tenantTables: array<int, string>, fks: array<int, object>} */
    private function catalog(): array
    {
        return $this->catalog ??= [
            'tables' => collect(DB::select("select table_name from information_schema.tables where table_schema = 'public' and table_type = 'BASE TABLE'"))->pluck('table_name')->all(),
            'tenantTables' => collect(DB::select("select distinct table_name from information_schema.columns where table_schema = 'public' and column_name = 'tenant_id'"))->pluck('table_name')->all(),
            'fks' => DB::select("select conrelid::regclass::text as tbl, a.attname as col, confrelid::regclass::text as ref_tbl, af.attname as ref_col
                from pg_constraint c
                join pg_attribute a on a.attrelid = c.conrelid and a.attnum = any(c.conkey)
                join pg_attribute af on af.attrelid = c.confrelid and af.attnum = any(c.confkey)
                where c.contype = 'f' and c.connamespace = 'public'::regnamespace and array_length(c.conkey, 1) = 1"),
        ];
    }

    /**
     * Classificação das tabelas (não depende do tenant): do tenant (tenant_id + filhas), polimórficas
     * ou da plataforma (tudo que sobra, menos as efêmeras). Toda tabela cai em exatamente um grupo.
     *
     * @return array{tenant: array<int, string>, children: array<int, string>, platform: array<int, string>}
     */
    public function classify(): array
    {
        $c = $this->catalog();
        $owned = array_values(array_diff($c['tenantTables'], self::EPHEMERAL));
        $skip = array_merge(self::EPHEMERAL, self::POLYMORPHIC, ['tenants']);
        $children = [];

        for ($round = 0; $round < 8; $round++) {
            $added = false;

            foreach ($c['tables'] as $table) {
                if (in_array($table, $owned, true) || in_array($table, $children, true) || in_array($table, $skip, true)) {
                    continue;
                }

                $parents = collect($c['fks'])->where('tbl', $table)->pluck('ref_tbl')->unique()->all();
                // `users` e `tenants` não prendem tabelas: anúncios, IPs bloqueados e CRM da Oravel
                // apontam para eles e são da PLATAFORMA (exceção explícita: USER_CHILDREN).
                $viaOwned = array_intersect(array_diff($parents, ['users', 'tenants']), array_merge($owned, $children));

                if ($viaOwned || (in_array($table, self::USER_CHILDREN, true) && in_array('users', $parents, true))) {
                    $children[] = $table;
                    $added = true;
                }
            }

            if (! $added) {
                break;
            }
        }

        $platform = array_values(array_diff($c['tables'], $owned, $children, $skip));

        return ['tenant' => $owned, 'children' => $children, 'platform' => $platform];
    }

    // ------------------------------------------------------------------ materialização

    private function q(string $ident): string
    {
        return '"'.str_replace('"', '""', $ident).'"';
    }

    /** Condição "pertence a algum cliente" para tabelas polimórficas, a partir das tabelas-modelo do tenant. */
    private function polymorphicTenantCondition(string $typeCol, string $idCol, string $source, array $ownedTables, ?string $copySchema): string
    {
        $parts = [];
        $types = DB::table($source)->whereNotNull($typeCol)->distinct()->pluck($typeCol);

        foreach ($types as $class) {
            if (! is_string($class) || ! class_exists($class) || ! is_subclass_of($class, Model::class)) {
                continue;
            }

            $table = (new $class)->getTable();

            if (! in_array($table, $ownedTables, true)) {
                continue;
            }

            $ref = $copySchema ? $this->q($copySchema).'.'.$this->q($table) : 'public.'.$this->q($table);
            $parts[] = '('.$this->q($typeCol).' = '.DB::getPdo()->quote($class).' and '.$this->q($idCol).'::text in (select id::text from '.$ref.'))';
        }

        return $parts ? '('.implode(' or ', $parts).')' : 'false';
    }

    /**
     * Copia para $schema as linhas do tenant. Devolve [tabela => nº de linhas] só das que têm linhas.
     *
     * @return array<string, int>
     */
    public function materializeTenant(string $tenantId, string $schema): array
    {
        if (! Str::isUuid($tenantId) || ! preg_match('/^bk_[a-z0-9_]+$/', $schema)) {
            throw new RuntimeException('Parâmetros inválidos para o backup.');
        }

        $class = $this->classify();
        $tid = DB::getPdo()->quote($tenantId);
        $copied = [];
        $copy = function (string $table, string $where) use ($schema, &$copied) {
            DB::statement('create table '.$this->q($schema).'.'.$this->q($table).' as select * from public.'.$this->q($table).' where '.$where);
            $copied[] = $table;
        };

        DB::statement('create schema '.$this->q($schema));

        $copy('tenants', "id = {$tid}::uuid");

        foreach ($class['tenant'] as $table) {
            $copy($table, "tenant_id = {$tid}::uuid");
        }

        // filhas: em rodadas, até todos os pais já estarem copiados
        $pending = $class['children'];
        $fks = collect($this->catalog()['fks']);

        for ($round = 0; $round < 8 && $pending; $round++) {
            foreach ($pending as $i => $table) {
                $conds = [];
                $ready = true;

                foreach ($fks->where('tbl', $table) as $fk) {
                    $parentOwned = (! in_array($fk->ref_tbl, ['users', 'tenants'], true) && (in_array($fk->ref_tbl, $class['tenant'], true) || in_array($fk->ref_tbl, $class['children'], true)))
                        || ($fk->ref_tbl === 'users' && in_array($table, self::USER_CHILDREN, true));

                    if (! $parentOwned) {
                        continue;
                    }

                    if (! in_array($fk->ref_tbl, $copied, true)) {
                        $ready = false;
                        break;
                    }

                    $conds[] = $this->q($fk->col).' in (select '.$this->q($fk->ref_col).' from '.$this->q($schema).'.'.$this->q($fk->ref_tbl).')';
                }

                if ($ready && $conds) {
                    $copy($table, '('.implode(' or ', $conds).')');
                    unset($pending[$i]);
                }
            }
        }

        if ($pending) {
            throw new RuntimeException('Tabelas filhas sem pai copiado: '.implode(', ', $pending));
        }

        $owned = array_merge($class['tenant'], $class['children'], ['tenants']);

        $copy('notifications', "notifiable_type = 'App\\Models\\User' and notifiable_id::text in (select id::text from ".$this->q($schema).'.users)');
        $copy('media', $this->polymorphicTenantCondition('model_type', 'model_id', 'media', $owned, $schema));
        $copy(
            'activity_log',
            $this->polymorphicTenantCondition('subject_type', 'subject_id', 'activity_log', $owned, $schema)
                ." or (causer_type = 'App\\Models\\User' and causer_id::text in (select id::text from ".$this->q($schema).'.users))'
        );

        $counts = [];
        foreach ($copied as $table) {
            $n = (int) DB::scalar('select count(*) from '.$this->q($schema).'.'.$this->q($table));
            if ($n > 0) {
                $counts[$table] = $n;
            }
        }

        return $counts;
    }

    /**
     * Copia para $schema o que não é de nenhum cliente: tabelas da plataforma inteiras e, das demais,
     * as linhas sem tenant (tenant_id nulo, polimórficas sem dono).
     *
     * @return array<string, int>
     */
    public function materializePlatform(string $schema): array
    {
        if (! preg_match('/^bk_[a-z0-9_]+$/', $schema)) {
            throw new RuntimeException('Schema inválido.');
        }

        $class = $this->classify();
        $copied = [];
        $copy = function (string $table, string $where) use ($schema, &$copied) {
            DB::statement('create table '.$this->q($schema).'.'.$this->q($table).' as select * from public.'.$this->q($table).' where '.$where);
            $copied[] = $table;
        };

        DB::statement('create schema '.$this->q($schema));

        foreach ($class['platform'] as $table) {
            $copy($table, 'true');
        }

        foreach ($class['tenant'] as $table) {
            $copy($table, 'tenant_id is null');
        }

        $owned = array_merge($class['tenant'], $class['children'], ['tenants']);
        $copy('notifications', "not coalesce(notifiable_type = 'App\\Models\\User' and notifiable_id::text in (select id::text from public.users where tenant_id is not null), false)");
        // coalesce(..., false): linha sem objeto/autor (valor nulo) dava NULL no NOT e sumia do backup.
        $copy('media', 'not coalesce('.$this->polymorphicTenantCondition('model_type', 'model_id', 'media', $owned, null).', false)');
        $copy('activity_log', 'not coalesce('.$this->polymorphicTenantCondition('subject_type', 'subject_id', 'activity_log', $owned, null)
            ." or (causer_type = 'App\\Models\\User' and causer_id::text in (select id::text from public.users where tenant_id is not null)), false)");

        $counts = [];
        foreach ($copied as $table) {
            $n = (int) DB::scalar('select count(*) from '.$this->q($schema).'.'.$this->q($table));
            if ($n > 0) {
                $counts[$table] = $n;
            }
        }

        return $counts;
    }

    // ------------------------------------------------------------------ geração dos arquivos

    public function backupTenant(Tenant $tenant): DatabaseBackup
    {
        return $this->run(
            kind: DatabaseBackup::KIND_TENANT,
            label: $tenant->slug ?: $tenant->id,
            title: "tenant {$tenant->name} ({$tenant->id})",
            tenant: $tenant,
            materialize: fn (string $schema) => $this->materializeTenant($tenant->id, $schema),
        );
    }

    public function backupPlatform(): DatabaseBackup
    {
        return $this->run(
            kind: DatabaseBackup::KIND_PLATFORM,
            label: '_plataforma',
            title: 'plataforma (dados que não pertencem a nenhum cliente)',
            tenant: null,
            materialize: fn (string $schema) => $this->materializePlatform($schema),
        );
    }

    private function run(string $kind, string $label, string $title, ?Tenant $tenant, callable $materialize): DatabaseBackup
    {
        $dir = $this->baseDir().'/'.Str::slug($label, '_');
        $timestamp = now()->format('Ymd_His');
        $file = "{$dir}/".Str::slug($label, '_')."_{$timestamp}.sql.gz";
        $schema = 'bk_'.substr(md5($label.microtime(true)), 0, 12);

        if (! is_dir($dir) && ! mkdir($dir, 0750, true) && ! is_dir($dir)) {
            throw new RuntimeException("Não foi possível criar {$dir}.");
        }

        try {
            $counts = $materialize($schema);
            $this->dumpSchema($schema, $file, $title, $counts);
        } catch (\Throwable $e) {
            @unlink($file);

            return DatabaseBackup::create([
                'tenant_id' => $tenant?->id, 'kind' => $kind, 'client_label' => $tenant?->name ?? 'Plataforma', 'filename' => basename($file), 'path' => $file,
                'size_bytes' => 0, 'tenant_count' => $tenant ? 1 : 0, 'tenant_names' => $tenant ? [$tenant->name] : [],
                'status' => DatabaseBackup::STATUS_FAILED, 'error_message' => Str::limit($e->getMessage(), 900, ''),
            ]);
        } finally {
            DB::statement('drop schema if exists '.$this->q($schema).' cascade');
        }

        $sha = hash_file('sha256', $file);
        file_put_contents($file.'.manifest.json', json_encode([
            'backup' => $title, 'created_at' => now()->toIso8601String(), 'sha256' => $sha, 'tables' => $counts,
            'rows' => array_sum($counts),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        @chmod($file, 0640);
        @chmod($file.'.manifest.json', 0640);

        return DatabaseBackup::create([
            'tenant_id' => $tenant?->id, 'kind' => $kind, 'client_label' => $tenant?->name ?? 'Plataforma', 'filename' => basename($file), 'path' => $file,
            'size_bytes' => filesize($file), 'rows_count' => array_sum($counts), 'sha256' => $sha,
            'tenant_count' => $tenant ? 1 : 0, 'tenant_names' => $tenant ? [$tenant->name] : [],
            'status' => DatabaseBackup::STATUS_COMPLETED,
        ]);
    }

    /** pg_dump (só dados, formato COPY) do schema temporário -> .sql.gz com o schema trocado por public. */
    private function dumpSchema(string $schema, string $file, string $title, array $counts): void
    {
        $conn = config('database.connections.'.config('database.default'));
        $tmp = $file.'.tmp';

        $process = new Process([
            'pg_dump', '--data-only', '--no-owner', '--no-privileges', '--schema='.$schema,
            '-h', (string) $conn['host'], '-p', (string) ($conn['port'] ?? 5432), '-U', (string) $conn['username'],
            '-f', $tmp, (string) $conn['database'],
        ], null, ['PGPASSWORD' => (string) ($conn['password'] ?? '')], null, 600);
        $process->run();

        if (! $process->isSuccessful() || ! is_file($tmp)) {
            @unlink($tmp);
            throw new RuntimeException('pg_dump falhou: '.trim($process->getErrorOutput()));
        }

        $out = gzopen($file, 'wb6');
        gzwrite($out, "-- Oravel: backup de {$title}\n-- Gerado em ".now()->format('d/m/Y H:i:s').' | '.array_sum($counts).' linhas em '.count($counts)." tabelas\n"
            ."-- Somente dados (COPY). Restaurar: gunzip -c ARQUIVO | psql -d BANCO (schema atual; o cliente nao pode ja existir no banco).\n"
            ."BEGIN;\nSET LOCAL session_replication_role = replica;\n");

        $in = fopen($tmp, 'rb');
        $prefix = 'COPY '.$schema.'.';

        while (($line = fgets($in)) !== false) {
            gzwrite($out, str_starts_with($line, $prefix) ? 'COPY public.'.substr($line, strlen($prefix)) : $line);
        }

        gzwrite($out, "COMMIT;\n");
        fclose($in);
        gzclose($out);
        unlink($tmp);
    }

    // ------------------------------------------------------------------ retenção

    /** Apaga backups por cliente/plataforma mais antigos que $days (arquivos e registros). */
    public function prune(int $days): int
    {
        $old = DatabaseBackup::whereIn('kind', [DatabaseBackup::KIND_TENANT, DatabaseBackup::KIND_PLATFORM])
            ->where('created_at', '<', now()->subDays($days))->get();

        foreach ($old as $backup) {
            @unlink($backup->path);
            @unlink($backup->path.'.manifest.json');
            $backup->delete();
        }

        return $old->count();
    }
}
