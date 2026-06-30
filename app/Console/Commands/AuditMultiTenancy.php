<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use ReflectionClass;

class AuditMultiTenancy extends Command
{
    protected $signature = 'tenant:audit-models';
    protected $description = 'Audita quais modelos têm BelongsToTenant trait';

    public function handle()
    {
        $this->info('🔍 Auditando Multi-Tenancy...');
        $this->newLine();

        $modelsPath = app_path('Models');
        $files = File::files($modelsPath);

        $withTrait = [];
        $withoutTrait = [];

        foreach ($files as $file) {
            $className = 'App\\Models\\' . $file->getFilenameWithoutExtension();
            
            if (!class_exists($className)) {
                continue;
            }

            try {
                $reflection = new ReflectionClass($className);
                
                // Skip traits do Laravel
                if (str_contains($className, 'Scope') || str_contains($className, 'Builder')) {
                    continue;
                }

                $hasTrait = $this->hasTrait($reflection, 'BelongsToTenant');
                
                if ($hasTrait) {
                    $withTrait[] = $className;
                } else {
                    $withoutTrait[] = $className;
                }
            } catch (\Exception $e) {
                continue;
            }
        }

        // Output COM trait
        $this->line('<fg=green>✅ MODELOS COM BelongsToTenant (' . count($withTrait) . '):</fg=green>');
        foreach ($withTrait as $model) {
            $this->line('  ✓ ' . class_basename($model));
        }

        $this->newLine();

        // Output SEM trait
        if (!empty($withoutTrait)) {
            $this->line('<fg=red>❌ MODELOS SEM BelongsToTenant (' . count($withoutTrait) . '):</fg=red>');
            foreach ($withoutTrait as $model) {
                $this->line('  ✗ ' . class_basename($model));
            }
            $this->newLine();
            $this->warn('⚠️  Estes modelos podem ter data leaks entre tenants!');
        } else {
            $this->line('<fg=green>✅ TODOS os modelos estão protegidos!</fg=green>');
        }

        $this->newLine();
        $this->info('Auditoria concluída!');
    }

    private function hasTrait(ReflectionClass $class, string $traitName): bool
    {
        $traits = $class->getTraitNames();
        
        foreach ($traits as $trait) {
            if (str_contains($trait, $traitName)) {
                return true;
            }
        }
        
        return false;
    }
}
