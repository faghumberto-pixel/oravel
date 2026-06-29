<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use Illuminate\Console\Command;

class TestFeatures extends Command
{
    protected $signature = 'test:features';
    protected $description = 'Testa se features estão salvando';

    public function handle()
    {
        $tenant = Tenant::where('name', 'Locadora Premium')->first();

        if (!$tenant) {
            $this->error('Tenant não encontrado!');
            return 1;
        }

        $this->info("=== Tenant: {$tenant->name} ===");
        $this->line("Features (JSON): " . json_encode($tenant->features));
        $this->line("Maintenance: " . ($tenant->hasFeature('maintenance') ? '✅ SIM' : '❌ NÃO'));
        $this->line("Assets: " . ($tenant->hasFeature('assets') ? '✅ SIM' : '❌ NÃO'));
        $this->line("Materials: " . ($tenant->hasFeature('materials') ? '✅ SIM' : '❌ NÃO'));

        return 0;
    }
}
