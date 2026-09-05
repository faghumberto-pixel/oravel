<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class CheckViteAssets extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'vite:check
        {--fix : Detectar e reportar problemas de Vite assets}
        {--strict : Falhar (exit code 1) se há problemas}';

    /**
     * The command description.
     *
     * @var string
     */
    protected $description = 'Verifica integridade de assets do Vite (manifest.json, APP_ENV, APP_DEBUG)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->line('');
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->info('VERIFICAÇÃO DE VITE ASSETS');
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->line('');

        $errors = 0;
        $warnings = 0;

        // ====================================================================
        // 1. Verificar APP_ENV e APP_DEBUG
        // ====================================================================
        $this->line('<fg=yellow>[1/5]</> Verificando configuração de ambiente...');

        $appEnv = config('app.env');
        $appDebug = config('app.debug');

        if ($appEnv !== 'production') {
            $this->error("  ❌ APP_ENV é '{$appEnv}', esperava 'production'");
            $this->line('     Se está em PROD e APP_ENV≠production, vai usar Vite dev server!');
            $errors++;
        } else {
            $this->info('  ✅ APP_ENV=production');
        }

        if ($appDebug === true) {
            $this->error('  ❌ APP_DEBUG=true (deve ser false em production)');
            $this->line('     Se está em PROD e APP_DEBUG=true, vai usar Vite dev server!');
            $errors++;
        } else {
            $this->info('  ✅ APP_DEBUG=false');
        }

        // ====================================================================
        // 2. Verificar se manifest.json existe e é válido
        // ====================================================================
        $this->line('<fg=yellow>[2/5]</> Verificando manifest.json...');

        $manifestPath = public_path('build/manifest.json');

        if (! File::exists($manifestPath)) {
            $this->error('  ❌ manifest.json não encontrado em ' . $manifestPath);
            $this->line('     Execute: npm run build');
            $errors++;
        } else {
            // Validar JSON
            $manifest = @json_decode(File::get($manifestPath), true);
            if ($manifest === null) {
                $this->error('  ❌ manifest.json não é um JSON válido');
                $errors++;
            } else {
                $entryCount = count($manifest);
                $this->info("  ✅ manifest.json válido ({$entryCount} entries)");
            }
        }

        // ====================================================================
        // 3. Verificar se assets existem em public/build/assets
        // ====================================================================
        $this->line('<fg=yellow>[3/5]</> Verificando assets em public/build/assets...');

        $assetsDir = public_path('build/assets');

        if (! File::isDirectory($assetsDir)) {
            $this->error('  ❌ Diretório public/build/assets não existe');
            $this->line('     Execute: npm run build');
            $errors++;
        } else {
            $files = File::files($assetsDir);
            $fileCount = count($files);

            if ($fileCount === 0) {
                $this->error('  ❌ Diretório public/build/assets está vazio');
                $this->line('     Execute: npm run build');
                $errors++;
            } else {
                $this->info("  ✅ {$fileCount} assets encontrados");
            }
        }

        // ====================================================================
        // 4. Verificar se há node_modules/.vite (indicador de dev server)
        // ====================================================================
        $this->line('<fg=yellow>[4/5]</> Verificando se servidor dev está rodando...');

        $viteDir = base_path('node_modules/.vite');

        if (File::isDirectory($viteDir)) {
            $this->warn('  ⚠️  node_modules/.vite existe (dev server pode estar rodando)');
            $this->line('     Isso é OK em DEV, MAS em PROD significa que há um dev server ativo.');
            $warnings++;
        } else {
            $this->info('  ✅ Nenhum dev server ativo');
        }

        // ====================================================================
        // 5. Resumo
        // ====================================================================
        $this->line('<fg=yellow>[5/5]</> Resultado...');

        $this->line('');
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

        if ($errors === 0) {
            $this->info('✅ VERIFICAÇÃO PASSOU');

            if ($warnings > 0) {
                $this->warn("⚠️  {$warnings} aviso(s)");
            }

            $this->line('');
            $this->info('Assets estão prontos!');
            $this->line('');

            return 0;
        } else {
            $this->error("❌ {$errors} ERRO(S) ENCONTRADO(S)");

            if ($warnings > 0) {
                $this->warn("⚠️  {$warnings} aviso(s)");
            }

            $this->line('');

            if ($this->option('strict')) {
                $this->error('Falha em modo --strict');
                return 1;
            }

            return 0;
        }
    }
}
