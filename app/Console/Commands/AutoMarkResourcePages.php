<?php
namespace App\Console\Commands;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class AutoMarkResourcePages extends Command {
    protected $signature = 'features:auto-mark-pages';
    protected $description = 'Marca Pages baseado no Resource pai';

    private array $resourceToFeature = [
        'AbcMatrix' => 'maintenance_matrix',
        'AccountPayable' => 'accounts_payable',
        'AssetCategory' => 'assets',
        'Asset' => 'assets',
        'BillCategory' => 'bill_categories',
        'Branch' => 'branches',
        'ChecklistGroup' => 'checklists',
        'ChecklistTemplate' => 'checklists',
        'Client' => 'clients',
        'Company' => 'company',
        'Contract' => 'contracts',
        'CostCenter' => 'cost_centers',
        'Department' => 'departments',
        'FleetStatus' => 'fleet',
        'InternalUnit' => 'internal_units',
        'Location' => 'locations',
        'MaintenanceOrder' => 'maintenance',
        'MaintenancePlan' => 'maintenance',
        'MaterialCategory' => 'materials',
        'Material' => 'materials',
        'PartsRequest' => 'parts_request',
        'Role' => 'roles',
        'SolicitacaoLocacao' => 'rental_requests',
        'Supplier' => 'suppliers',
        'User' => 'users',
    ];

    private array $pageToFeature = [
        'MaintenanceKanban' => 'maintenance',
        'AgendaTecnico' => 'maintenance',
        'Chat' => 'users',
        'Dashboard' => 'users',
        'PainelGestao' => 'users',
        'RegisterTenant' => 'company',
    ];

    public function handle(): int {
        $paths = ['app/Filament/Resources', 'app/Filament/Pages'];
        $marked = 0;

        foreach ($paths as $path) {
            if (!is_dir($path)) continue;
            foreach (File::allFiles($path) as $file) {
                if ($file->getExtension() !== 'php') continue;
                $content = File::get($file->getPathname());
                if (str_contains($content, '#[BelongsToFeature')) continue;
                
                if (!preg_match('/class\s+(\w+)/', $content, $matches)) continue;
                
                $className = $matches[1];
                $feature = $this->detectFeature($className);

                if (!$feature) continue;

                if (!str_contains($content, 'use App\Filament\Attributes\BelongsToFeature')) {
                    $content = preg_replace(
                        "/(namespace [^;]+;)/",
                        "$1\n\nuse App\Filament\Attributes\BelongsToFeature;",
                        $content,
                        1
                    );
                }

                $content = preg_replace(
                    "/(class $className)/",
                    "#[BelongsToFeature('$feature')]\n\$1",
                    $content,
                    1
                );

                File::put($file->getPathname(), $content);
                $marked++;
                $this->line("✅ $className -> $feature");
            }
        }

        $this->info("\n✅ MARCADOS: $marked");
        return 0;
    }

    private function detectFeature(string $className): ?string {
        // Check explicit mapping
        if (isset($this->pageToFeature[$className])) {
            return $this->pageToFeature[$className];
        }

        // Remove CRUD prefixes
        $baseName = preg_replace('/^(Create|Edit|List|View|Manage)/', '', $className);
        $baseName = preg_replace('/s$/', '', $baseName); // Remove plural

        // Find in resource mapping
        foreach ($this->resourceToFeature as $resource => $feature) {
            if (str_contains($baseName, $resource)) {
                return $feature;
            }
        }

        // Widget/Chart detection
        if (str_contains($className, 'Chart') || str_contains($className, 'Stats')) {
            if (str_contains($className, 'MaintenanceOrder')) return 'maintenance';
            if (str_contains($className, 'Asset')) return 'assets';
            if (str_contains($className, 'Maintenance')) return 'maintenance';
        }

        // Relation managers
        if (str_contains($className, 'RelationManager')) {
            if (str_contains($className, 'Maintenance')) return 'maintenance';
            if (str_contains($className, 'Asset')) return 'assets';
            if (str_contains($className, 'Material')) return 'materials';
            if (str_contains($className, 'Activity')) return 'maintenance';
            if (str_contains($className, 'Checklist')) return 'checklists';
            if (str_contains($className, 'Communication')) return 'maintenance';
            if (str_contains($className, 'TimeEntry')) return 'maintenance';
        }

        return null;
    }
}
