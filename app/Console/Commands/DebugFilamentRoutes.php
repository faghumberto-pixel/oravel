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
            $this->line('   Model: ' . $resource::getModel());
            $this->line('   Slug: ' . $resource::getSlug());

            // Teste 2: Rodar filament:upgrade
            $this->line('2️⃣ Executando filament:upgrade...');
            $exitCode = $this->call('filament:upgrade');
            $this->info('   ✓ filament:upgrade completado (exit: ' . $exitCode . ')');

            // Teste 3: Listar rotas
            $this->line('3️⃣ Verificando se rota foi criada...');
            \Illuminate\Support\Facades\Artisan::call('route:list', [], $output = new \Symfony\Component\Console\Output\BufferedOutput());
            $routes = $output->fetch();
            if (str_contains($routes, 'landing-page-leads')) {
                $this->info('   ✓ Rota central/landing-page-leads ENCONTRADA');
            } else {
                $this->error('   ✗ Rota central/landing-page-leads NÃO ENCONTRADA');
            }

            $this->info('✅ Diagnóstico concluído!');

        } catch (\Exception $e) {
            $this->error('❌ ERRO: ' . $e->getMessage());
            $this->error($e->getTraceAsString());
            return 1;
        }

        return 0;
    }
}
