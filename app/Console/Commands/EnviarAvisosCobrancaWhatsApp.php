<?php

namespace App\Console\Commands;

use App\Models\TenantWhatsappSetting;
use App\Services\AvisosWhatsApp;
use Illuminate\Console\Command;

class EnviarAvisosCobrancaWhatsApp extends Command
{
    protected $signature = 'whatsapp:avisos-cobranca';

    protected $description = 'Envia por WhatsApp os lembretes de vencimento e de atraso das contas a receber (empresas que ligaram o aviso)';

    public function handle(): int
    {
        $total = 0;

        TenantWhatsappSetting::withoutGlobalScopes()->where('enabled', true)->where('aviso_cobranca', true)->get()
            ->each(function (TenantWhatsappSetting $config) use (&$total) {
                try {
                    $total += AvisosWhatsApp::cobrancas($config);
                } catch (\Throwable $e) {
                    $this->warn("Empresa {$config->tenant_id}: {$e->getMessage()}");
                }
            });

        $this->info("Avisos de cobrança enviados: {$total}");

        return self::SUCCESS;
    }
}
