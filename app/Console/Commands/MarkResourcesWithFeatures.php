<?php
namespace App\Console\Commands;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
class MarkResourcesWithFeatures extends Command {
    protected $signature = 'features:mark-resources';
    protected $description = 'Descobre Resources sem Feature';
    private array $manualMapping = [
        'MaintenanceOrderResource' => 'maintenance',
        'MaintenancePlanResource' => 'maintenance',
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
        $unmarked = [];
        foreach (['app/Filament/Resources', 'app/Filament/Pages'] as $path) {
            if (!File::isDirectory($path)) continue;
            foreach (File::allFiles($path) as $file) {
                if ($file->getExtension() !== 'php') continue;
                if (!str_contains(File::get($file->getPathname()), '#[BelongsToFeature')) {
                    $className = str_replace('.php', '', basename($file->getPathname()));
                    $feature = $this->manualMapping[$className] ?? '❓';
                    $unmarked[] = ['Class' => $className, 'Feature' => $feature];
                }
            }
        }
        if (empty($unmarked)) { $this->info('✅ TODOS marcados!'); return 0; }
        $this->warn('⚠️ ' . count($unmarked) . ' sem Attribute:');
        $this->table(['Class', 'Feature'], $unmarked);
        return 1;
    }
}
