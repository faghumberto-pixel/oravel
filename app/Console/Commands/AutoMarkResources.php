<?php
namespace App\Console\Commands;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class AutoMarkResources extends Command {
    protected $signature = 'features:auto-mark';
    protected $description = 'Marca automaticamente todos os Resources/Pages';

    private array $mapping = [
        'MaintenanceOrderResource' => 'maintenance',
        'MaintenancePlanResource' => 'maintenance',
        'MaintenanceKanban' => 'maintenance',
        'AgendaTecnico' => 'maintenance',
        'AbcMatrixResource' => 'maintenance_matrix',
        'AssetResource' => 'assets',
        'AssetCategoryResource' => 'assets',
        'FleetStatusResource' => 'fleet',
        'ClientResource' => 'clients',
        'SolicitacaoLocacaoResource' => 'rental_requests',
        'MaterialResource' => 'materials',
        'MaterialCategoryResource' => 'materials',
        'PartsRequestResource' => 'parts_request',
        'DepartmentResource' => 'departments',
        'LocationResource' => 'locations',
        'BranchResource' => 'branches',
        'InternalUnitResource' => 'internal_units',
        'CompanyResource' => 'company',
        'SupplierResource' => 'suppliers',
        'ContractResource' => 'contracts',
        'AccountPayableResource' => 'accounts_payable',
        'BillCategoryResource' => 'bill_categories',
        'CostCenterResource' => 'cost_centers',
        'UserResource' => 'users',
        'RoleResource' => 'roles',
        'ChecklistGroupResource' => 'checklists',
        'ChecklistTemplateResource' => 'checklists',
    ];

    public function handle(): int {
        $paths = ['app/Filament/Resources', 'app/Filament/Pages'];
        $marked = 0;
        $skipped = 0;

        foreach ($paths as $path) {
            if (!is_dir($path)) continue;
            foreach (File::allFiles($path) as $file) {
                if ($file->getExtension() !== 'php') continue;
                $content = File::get($file->getPathname());
                if (str_contains($content, '#[BelongsToFeature')) {
                    $skipped++;
                    continue;
                }
                if (!preg_match('/class\s+(\w+)/', $content, $matches)) {
                    continue;
                }
                $className = $matches[1];
                $feature = $this->mapping[$className] ?? null;
                if (!$feature) {
                    continue;
                }
                // Add import after namespace
                if (!str_contains($content, 'use App\Filament\Attributes\BelongsToFeature')) {
                    $content = preg_replace(
                        "/(namespace [^;]+;)/",
                        "$1\n\nuse App\Filament\Attributes\BelongsToFeature;",
                        $content,
                        1
                    );
                }
                // Add attribute before class
                $content = preg_replace(
                    "/class $className/",
                    "#[BelongsToFeature('$feature')]\nclass $className",
                    $content,
                    1
                );
                File::put($file->getPathname(), $content);
                $marked++;
                $this->line("✅ $className -> $feature");
            }
        }

        $this->info("\n✅ MARCADOS: $marked | PULADOS: $skipped");
        return 0;
    }
}
