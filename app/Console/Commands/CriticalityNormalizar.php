<?php

namespace App\Console\Commands;

use App\Models\CriticalityLevel;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * F0 (higiene da criticidade): cria o padrao A/B/C para os tenants que ainda nao tem NENHUM nivel e
 * lista os que usam codigos fora do padrao (ex.: alta/media/baixa/urgente). Por padrao so' mostra o que faria
 * (dry-run); `--apply` grava. Nao renomeia codigo existente: abc_matrices.nivel guarda o codigo como texto,
 * entao renomear exige decidir o de-para junto com o cliente.
 */
class CriticalityNormalizar extends Command
{
    protected $signature = 'criticality:normalizar {--apply : Grava os niveis padrao (sem isso, so mostra)}';

    protected $description = 'Cria os niveis de criticidade A/B/C nos tenants sem nenhum e lista tenants com codigos fora do padrao';

    private const PADRAO = [
        ['code' => 'A', 'name' => 'Alta', 'color' => '#ef4444', 'is_urgent' => true, 'sort_order' => 1],
        ['code' => 'B', 'name' => 'Média', 'color' => '#f59e0b', 'is_urgent' => false, 'sort_order' => 2],
        ['code' => 'C', 'name' => 'Baixa', 'color' => '#22c55e', 'is_urgent' => false, 'sort_order' => 3],
    ];

    public function handle(): int
    {
        $aplicar = (bool) $this->option('apply');
        $semNiveis = 0;
        $foraDoPadrao = [];

        foreach (Tenant::query()->get(['id', 'name']) as $tenant) {
            $codigos = CriticalityLevel::withoutGlobalScopes()->where('tenant_id', $tenant->id)->pluck('code');

            if ($codigos->isEmpty()) {
                $semNiveis++;
                $this->line("[{$tenant->name}] sem níveis: ".($aplicar ? 'criando A/B/C' : 'criaria A/B/C'));

                if ($aplicar) {
                    CriticalityLevel::withoutGlobalScopes()->insert(array_map(fn (array $n) => $n + [
                        'id' => (string) Str::uuid7(), 'tenant_id' => $tenant->id, 'created_at' => now(), 'updated_at' => now(),
                    ], self::PADRAO));
                }

                continue;
            }

            if ($codigos->contains(fn ($c) => ! in_array($c, ['A', 'B', 'C'], true))) {
                $foraDoPadrao[$tenant->name] = $codigos->implode(', ');
            }
        }

        foreach ($foraDoPadrao as $nome => $codigos) {
            $this->warn("[{$nome}] códigos fora do padrão A/B/C: {$codigos} (não alterado; combinar o de-para com o cliente)");
        }

        $this->info(($aplicar ? 'Criados' : 'A criar').": níveis padrão em {$semNiveis} tenant(s) sem níveis; ".count($foraDoPadrao).' tenant(s) fora do padrão (só listados).');

        return self::SUCCESS;
    }
}
