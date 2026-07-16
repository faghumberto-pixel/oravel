<?php

use App\Models\CriticalityLevel;
use App\Models\Tenant;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Str;

/**
 * Reconciliacao de dados: CriticalityLevel ja tinha linhas semeadas pra
 * quase todo tenant com codes 'alta'/'media'/'baixa' (usadas antigamente
 * so' pelo Kanban), enquanto AbcMatrix.nivel (por Ativo) sempre usou
 * 'A'/'B'/'C' -- dois vocabularios diferentes pro mesmo conceito, nunca
 * unificados ate' agora (ver decisao registrada em CriticalityLevel.php).
 * A tela de cadastro nova e o Select da Matriz ABC passam a ler as opcoes
 * daqui, entao os codes precisam bater com o que ja esta' gravado em
 * abc_matrices.nivel (A/B) -- daqui pra frente 'A'/'B'/'C' e' o vocabulario
 * unico. Nada aponta pra criticality_levels.id por FK hoje (confirmado:
 * MaintenanceOrder.criticality_level_id e Asset.criticality_level_id nunca
 * sao preenchidos por nenhuma tela real), entao so' renomear os codes das
 * linhas existentes e' seguro.
 */
return new class extends Migration
{
    public function up(): void
    {
        $renameMap = ['alta' => 'A', 'media' => 'B', 'baixa' => 'C'];

        foreach ($renameMap as $old => $new) {
            CriticalityLevel::withoutGlobalScopes()
                ->where('code', $old)
                ->update(['code' => $new]);
        }

        CriticalityLevel::withoutGlobalScopes()
            ->where('code', 'A')
            ->update(['is_urgent' => true, 'sort_order' => 1]);
        CriticalityLevel::withoutGlobalScopes()
            ->where('code', 'B')
            ->update(['sort_order' => 2]);
        CriticalityLevel::withoutGlobalScopes()
            ->where('code', 'C')
            ->update(['sort_order' => 3]);

        // Tenants sem nenhuma linha ainda (nunca passaram pelo seeder de
        // demo que criava alta/media/baixa) ganham o padrao A/B/C do zero,
        // mantendo o comportamento visual identico ao que o Kanban sempre
        // teve (vermelho == mais critico).
        $tenantsComLinhas = CriticalityLevel::withoutGlobalScopes()->distinct()->pluck('tenant_id');

        Tenant::query()
            ->whereNotIn('id', $tenantsComLinhas)
            ->get(['id'])
            ->each(function (Tenant $tenant) {
                CriticalityLevel::withoutGlobalScopes()->insert([
                    [
                        'id' => (string) Str::uuid7(),
                        'tenant_id' => $tenant->id,
                        'code' => 'A',
                        'name' => 'Alta',
                        'color' => '#ef4444',
                        'is_urgent' => true,
                        'sort_order' => 1,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ],
                    [
                        'id' => (string) Str::uuid7(),
                        'tenant_id' => $tenant->id,
                        'code' => 'B',
                        'name' => 'Média',
                        'color' => '#f59e0b',
                        'is_urgent' => false,
                        'sort_order' => 2,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ],
                    [
                        'id' => (string) Str::uuid7(),
                        'tenant_id' => $tenant->id,
                        'code' => 'C',
                        'name' => 'Baixa',
                        'color' => '#22c55e',
                        'is_urgent' => false,
                        'sort_order' => 3,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ],
                ]);
            });
    }

    public function down(): void
    {
        // Dado renomeado/inserido -- sem reverso seguro (nao sabemos quais
        // linhas eram originais vs. criadas aqui). Intencionalmente vazio,
        // mesmo padrao de outras migrations de backfill neste repositorio.
    }
};
