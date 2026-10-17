<?php

namespace App\Console\Commands;

use App\Models\TenantAsaasSetting;
use App\Services\Cobranca\GeradorDeCobrancas;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class ProcessarCobrancas extends Command
{
    protected $signature = 'cobranca:processar {--tenant-id= : Só uma empresa} {--hoje= : Data de referência (AAAA-MM-DD), para testes}';

    protected $description = 'Cobrança automática: gera as contas dos Planos de Cobrança e as cobranças no Asaas, nas empresas que ligaram a automação';

    public function handle(GeradorDeCobrancas $gerador): int
    {
        $hoje = $this->option('hoje') ? Carbon::parse($this->option('hoje')) : now();
        $contas = $enviadas = $falhas = 0;

        TenantAsaasSetting::withoutGlobalScopes()->where('enabled', true)
            ->when($this->option('tenant-id'), fn ($q, $id) => $q->where('tenant_id', $id))->get()
            ->filter(fn (TenantAsaasSetting $c) => $c->pronta())
            ->each(function (TenantAsaasSetting $config) use ($gerador, $hoje, &$contas, &$enviadas, &$falhas) {
                try {
                    if ($config->gerar_recorrentes) {
                        $contas += $gerador->gerarRecorrentes($config, $hoje);
                    }

                    if ($config->cobrar_automaticamente) {
                        $r = $gerador->enviarPendentes($config, $hoje);
                        $enviadas += $r['enviadas'];
                        $falhas += $r['falhas'];
                    }
                } catch (\Throwable $e) {
                    report($e);
                    $this->warn("Empresa {$config->tenant_id}: {$e->getMessage()}");
                }
            });

        $this->info("Contas geradas: {$contas} · cobranças criadas: {$enviadas} · falhas: {$falhas}");

        return self::SUCCESS;
    }
}
