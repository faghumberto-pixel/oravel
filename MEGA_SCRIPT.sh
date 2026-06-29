#!/bin/bash
mkdir -p app/Enums
mkdir -p app/Filament/Attributes
mkdir -p app/Services
mkdir -p app/Filament/Middleware
mkdir -p app/Console/Commands

cat > app/Enums/Feature.php << 'ENUM_EOF'
<?php
namespace App\Enums;
enum Feature: string {
    case MAINTENANCE = 'maintenance';
    case ASSETS = 'assets';
    case FLEET = 'fleet';
    case CLIENTS = 'clients';
    case RENTAL_REQUESTS = 'rental_requests';
    case MATERIALS = 'materials';
    case PARTS_REQUEST = 'parts_request';
    case DEPARTMENTS = 'departments';
    case LOCATIONS = 'locations';
    case BRANCHES = 'branches';
    case INTERNAL_UNITS = 'internal_units';
    case COMPANY = 'company';
    case SUPPLIERS = 'suppliers';
    case CONTRACTS = 'contracts';
    case ACCOUNTS_PAYABLE = 'accounts_payable';
    case BILL_CATEGORIES = 'bill_categories';
    case COST_CENTERS = 'cost_centers';
    case USERS = 'users';
    case ROLES = 'roles';
    case CHECKLISTS = 'checklists';
    case MAINTENANCE_MATRIX = 'maintenance_matrix';
    public function label(): string {
        return match($this) {
            self::MAINTENANCE => '📋 Manutenção',
            self::ASSETS => '🚗 Ativos',
            self::FLEET => '📊 Frotas',
            self::CLIENTS => '👥 Clientes',
            self::RENTAL_REQUESTS => '📝 Solicitações',
            self::MATERIALS => '📦 Materiais',
            self::PARTS_REQUEST => '🔧 Peças',
            self::DEPARTMENTS => '🏢 Departamentos',
            self::LOCATIONS => '📍 Localizações',
            self::BRANCHES => '🏭 Filiais',
            self::INTERNAL_UNITS => '⚙️ Unidades',
            self::COMPANY => '🏛️ Empresa',
            self::SUPPLIERS => '🤝 Fornecedores',
            self::CONTRACTS => '📄 Contratos',
            self::ACCOUNTS_PAYABLE => '💳 Contas',
            self::BILL_CATEGORIES => '📂 Categorias',
            self::COST_CENTERS => '💰 Centros',
            self::USERS => '👤 Usuários',
            self::ROLES => '🔐 Perfis',
            self::CHECKLISTS => '✅ Checklists',
            self::MAINTENANCE_MATRIX => '📊 Matriz ABC',
        };
    }
    public function description(): string {
        return match($this) {
            self::MAINTENANCE => 'Gerenciamento de ordens e planos',
            self::ASSETS => 'Cadastro de ativos',
            self::FLEET => 'Status de frotas',
            self::CLIENTS => 'Gerenciamento de clientes',
            self::RENTAL_REQUESTS => 'Solicitações de locação',
            self::MATERIALS => 'Controle de materiais',
            self::PARTS_REQUEST => 'Solicitações de peças',
            self::DEPARTMENTS => 'Departamentos',
            self::LOCATIONS => 'Localizações',
            self::BRANCHES => 'Filiais',
            self::INTERNAL_UNITS => 'Unidades internas',
            self::COMPANY => 'Informações da empresa',
            self::SUPPLIERS => 'Fornecedores',
            self::CONTRACTS => 'Contratos',
            self::ACCOUNTS_PAYABLE => 'Contas a pagar',
            self::BILL_CATEGORIES => 'Categorias',
            self::COST_CENTERS => 'Centros de custo',
            self::USERS => 'Usuários',
            self::ROLES => 'Perfis',
            self::CHECKLISTS => 'Checklists',
            self::MAINTENANCE_MATRIX => 'Matriz ABC',
        };
    }
    public static function options(): array {
        $options = [];
        foreach (self::cases() as $feature) {
            $options[$feature->value] = $feature->label();
        }
        return $options;
    }
    public static function descriptions(): array {
        $desc = [];
        foreach (self::cases() as $feature) {
            $desc[$feature->value] = $feature->description();
        }
        return $desc;
    }
}
ENUM_EOF

cat > app/Filament/Attributes/BelongsToFeature.php << 'ATTR_EOF'
<?php
namespace App\Filament\Attributes;
use Attribute;
#[Attribute(Attribute::TARGET_CLASS)]
class BelongsToFeature {
    public function __construct(public string $featureSlug) {}
}
ATTR_EOF

cat > app/Services/FeatureDiscoveryService.php << 'SERVICE_EOF'
<?php
namespace App\Services;
use App\Filament\Attributes\BelongsToFeature;
use Illuminate\Support\Facades\File;
use ReflectionClass;
class FeatureDiscoveryService {
    private static array $searchPaths = ['app/Filament/Resources', 'app/Filament/Pages'];
    public static function getAllFeatureMapping(): array {
        $mapping = [];
        foreach (self::$searchPaths as $path) {
            if (!is_dir($path)) continue;
            foreach (File::allFiles($path) as $file) {
                if ($file->getExtension() !== 'php') continue;
                $className = self::getClassNameFromFile($file->getPathname());
                if (!$className) continue;
                try {
                    $reflection = new ReflectionClass($className);
                    $attributes = $reflection->getAttributes(BelongsToFeature::class);
                    if (!empty($attributes)) {
                        $attribute = $attributes[0]->newInstance();
                        $mapping[$className] = $attribute->featureSlug;
                    }
                } catch (\Exception $e) { continue; }
            }
        }
        return $mapping;
    }
    public static function getFeatureMapping(): array {
        $mapping = self::getAllFeatureMapping();
        $byFeature = [];
        foreach ($mapping as $className => $featureSlug) {
            if (!isset($byFeature[$featureSlug])) $byFeature[$featureSlug] = [];
            $byFeature[$featureSlug][] = $className;
        }
        return $byFeature;
    }
    private static function getClassNameFromFile(string $filePath): ?string {
        $relativePath = str_replace(base_path(), '', $filePath);
        $relativePath = ltrim($relativePath, '/');
        $namespace = str_replace(['/', '.php'], ['\\', ''], $relativePath);
        if (!str_starts_with($namespace, 'app')) return null;
        $namespace = str_replace('app', 'App', $namespace, 1);
        return str_contains($namespace, '\\') ? $namespace : 'App\\' . str_replace('/', '\\', str_replace('app/', '', $relativePath, 1));
    }
}
SERVICE_EOF

cat > app/Filament/Middleware/FilterResourcesByFeatures.php << 'MIDDLEWARE_EOF'
<?php
namespace App\Filament\Middleware;
use App\Services\FeatureDiscoveryService;
use Closure;
use Filament\Facades\Filament;
use Filament\Navigation\NavigationGroup;
use Filament\Navigation\NavigationItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
class FilterResourcesByFeatures {
    public function handle(Request $request, Closure $next) {
        if (Filament::getCurrentPanel()?->getId() !== 'admin') return $next($request);
        $user = Auth::user();
        if (!$user) return $next($request);
        if ($user->isSuperAdmin()) return $next($request);
        $tenant = $user->tenant;
        if (!$tenant) return $next($request);
        $this->filterNavigation($tenant);
        return $next($request);
    }
    private function filterNavigation($tenant): void {
        $panel = Filament::panel('admin');
        if (!$panel) return;
        $navigation = $panel->getNavigation();
        foreach ($navigation as &$item) {
            if ($item instanceof NavigationGroup) {
                $items = $item->getItems();
                if (empty($this->filterNavigationItems($items, $tenant))) {
                    $item->visible(false);
                } else {
                    foreach ($items as $navItem) {
                        if ($this->shouldHideItem($navItem, $tenant)) $navItem->visible(false);
                    }
                }
            } elseif ($item instanceof NavigationItem) {
                if ($this->shouldHideItem($item, $tenant)) $item->visible(false);
            }
        }
    }
    private function filterNavigationItems(array $items, $tenant): array {
        return array_filter($items, fn($item) => !$this->shouldHideItem($item, $tenant));
    }
    private function shouldHideItem($item, $tenant): bool {
        if (!$item->getResourceClass()) return false;
        $resourceClass = $item->getResourceClass();
        $featureMapping = FeatureDiscoveryService::getAllFeatureMapping();
        $featureSlug = $featureMapping[$resourceClass] ?? null;
        if (!$featureSlug) return false;
        return !$tenant->hasFeature($featureSlug);
    }
}
MIDDLEWARE_EOF

cat > app/Console/Commands/MarkResourcesWithFeatures.php << 'COMMAND_EOF'
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
COMMAND_EOF

php artisan optimize:clear
php artisan config:cache

echo ""
echo "✅ INSTALAÇÃO COMPLETA!"
echo "Próximo: php artisan features:mark-resources"
