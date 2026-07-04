<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use ReflectionClass;

class AuditSaaSMetadata extends Command
{
    protected $signature = 'tenant:audit-saas-metadata';

    protected $description = 'Audita quais Models com Resource no painel admin não têm HasSaaSMetadata (ficam invisíveis para o gating de planos do Central)';

    public function handle()
    {
        $this->info('🔍 Auditando HasSaaSMetadata...');
        $this->newLine();

        $modelsWithResource = $this->modelsWithAdminResource();

        $withTrait = [];
        $withoutTrait = [];
        $inconsistent = [];

        foreach ($modelsWithResource as $className) {
            if (! class_exists($className)) {
                continue;
            }

            $reflection = new ReflectionClass($className);

            if (! $this->hasTrait($reflection, 'HasSaaSMetadata')) {
                $withoutTrait[] = $className;

                continue;
            }

            $featureKey = $className::saasFeatureKey();
            $slug = $className::saasPermissionSlug();
            $label = $className::saasModuleLabel();

            if (blank($featureKey) || blank($slug) || blank($label)) {
                $inconsistent[] = "{$className} (featureKey=".($featureKey ?: '-').', slug='.($slug ?: '-').', label='.($label ?: '-').')';

                continue;
            }

            $withTrait[] = $className;
        }

        $this->line('<fg=green>✅ MODELS COM Resource + HasSaaSMetadata OK ('.count($withTrait).'):</fg=green>');
        foreach ($withTrait as $model) {
            $this->line('  ✓ '.class_basename($model));
        }

        $this->newLine();

        if (! empty($inconsistent)) {
            $this->line('<fg=yellow>⚠️  MODELS COM A TRAIT MAS METADATA INCOMPLETO ('.count($inconsistent).'):</fg=yellow>');
            foreach ($inconsistent as $model) {
                $this->line('  ⚠ '.$model);
            }
            $this->newLine();
        }

        if (! empty($withoutTrait)) {
            $this->line('<fg=red>❌ MODELS COM Resource NO ADMIN MAS SEM HasSaaSMetadata ('.count($withoutTrait).'):</fg=red>');
            foreach ($withoutTrait as $model) {
                $this->line('  ✗ '.class_basename($model));
            }
            $this->newLine();
            $this->warn('⚠️  Estes módulos ficam invisíveis para o gating de planos/permissões do Central!');
        } else {
            $this->line('<fg=green>✅ TODOS os Models com Resource no admin têm HasSaaSMetadata!</fg=green>');
        }

        $this->newLine();
        $this->info('Auditoria concluída!');
    }

    /**
     * @return array<int, string>
     */
    private function modelsWithAdminResource(): array
    {
        $models = [];

        foreach (File::files(app_path('Filament/Resources')) as $file) {
            $className = 'App\\Filament\\Resources\\'.$file->getFilenameWithoutExtension();

            if (! class_exists($className) || ! property_exists($className, 'model')) {
                continue;
            }

            $reflection = new ReflectionClass($className);
            $property = $reflection->getProperty('model');
            $property->setAccessible(true);
            $modelClass = $property->getValue();

            if ($modelClass) {
                $models[] = $modelClass;
            }
        }

        return $models;
    }

    private function hasTrait(ReflectionClass $class, string $traitName): bool
    {
        foreach ($class->getTraitNames() as $trait) {
            if (str_contains($trait, $traitName)) {
                return true;
            }
        }

        return false;
    }
}
