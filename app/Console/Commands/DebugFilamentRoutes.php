<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class DebugFilamentRoutes extends Command
{
    protected $signature = 'debug:filament-routes';
    protected $description = 'Debug Filament route discovery for Central Panel';

    public function handle()
    {
        $this->info('🔍 Diagnosticando Central Panel...');

        try {
            // Teste 1: Carregar Resource
            $this->line('1️⃣ Testando carregamento do Resource...');
            $resource = new \App\Filament\Central\Resources\LandingPageLeadResource();
            $this->info('   ✓ LandingPageLeadResource carregada');
            $this->line('   Slug: ' . $resource::getSlug());

            // Teste 2: Verificar se está registrada no Provider
            $this->line('2️⃣ Verificando CentralPanelProvider...');
            $provider = new \App\Providers\Filament\CentralPanelProvider();
            $this->info('   ✓ CentralPanelProvider instanciada');

            // Teste 3: Rodar filament:upgrade
            $this->line('3️⃣ Executando filament:upgrade...');
            $this->call('filament:upgrade', ['--quiet' => true]);
            $this->info('   ✓ filament:upgrade completado');

            // Teste 4: Listar rotas
            $this->line('4️⃣ Listando rotas de landing-page...');
            $this->call('route:list', ['--grep' => 'landing-page']);

            $this->info('✅ Diagnóstico concluído!');

        } catch (\Exception $e) {
            $this->error('❌ ERRO: ' . $e->getMessage());
            $this->error($e->getTraceAsString());
            return 1;
        }

        return 0;
    }
}
