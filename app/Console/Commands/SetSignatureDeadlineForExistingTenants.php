<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use Illuminate\Console\Command;

class SetSignatureDeadlineForExistingTenants extends Command
{
    protected $signature = 'tenant:set-signature-deadline {days=30 : Dias até o prazo (padrão 30)}';

    protected $description = 'Define prazo de 30 dias para tenants existentes assinarem SLA + LGPD';

    public function handle()
    {
        $days = (int) $this->argument('days');
        $deadline = now()->addDays($days)->toDateString();

        $updated = Tenant::whereNull('signature_id')
            ->whereNull('signature_required_by')
            ->update(['signature_required_by' => $deadline]);

        $this->info("✅ {$updated} tenants receberam prazo: $deadline");
    }
}
