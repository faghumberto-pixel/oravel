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
