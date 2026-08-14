<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasSaaSMetadata;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Permission\Models\Role;

class Department extends Model
{
    use BelongsToTenant;
    use HasSaaSMetadata;
    use HasUuids;

    protected static ?string $saasFeatureKey = 'tabela_departments';

    protected static ?string $saasPermissionSlug = 'departamento';

    protected static ?string $saasModuleLabel = 'Departamentos';

    protected $fillable = [
        'name',
        'code',
        'tenant_id',
        'sector_key',
    ];

    /**
     * Department e' 100% texto livre por tenant (nome/codigo digitados a
     * mao, sem taxonomia canonica) -- sector_key resolve "qual
     * departamento deste tenant e' o setor X de verdade", pra travas de
     * hierarquia que precisam de certeza. Nullable/opcional: departamento
     * "customizado" sem chave fica de fora das travas novas.
     */
    public const SECTOR_COMERCIAL = 'comercial';

    public const SECTOR_MANUTENCAO = 'manutencao';

    public const SECTOR_SUPRIMENTOS = 'suprimentos';

    public const SECTOR_LOGISTICA = 'logistica';

    public const SECTOR_FINANCEIRO = 'financeiro';

    public const SECTOR_ADMINISTRATIVO = 'administrativo';

    /**
     * Adicionados 2026-08 junto com o seed de estrutura organizacional
     * padrão (App\Services\OrganizationalStructureSeeder) -- antes só
     * existiam como parte de "administrativo" sem setor próprio.
     */
    public const SECTOR_DEPARTAMENTO_PESSOAL = 'departamento_pessoal';

    public const SECTOR_SEGURANCA_TRABALHO = 'seguranca_trabalho';

    /**
     * @return array<string, string>
     */
    public static function sectorLabels(): array
    {
        return [
            self::SECTOR_COMERCIAL => 'Comercial',
            self::SECTOR_MANUTENCAO => 'Manutenção',
            self::SECTOR_SUPRIMENTOS => 'Suprimentos',
            self::SECTOR_LOGISTICA => 'Logística',
            self::SECTOR_FINANCEIRO => 'Financeiro',
            self::SECTOR_ADMINISTRATIVO => 'Administrativo',
            self::SECTOR_DEPARTAMENTO_PESSOAL => 'Departamento Pessoal',
            self::SECTOR_SEGURANCA_TRABALHO => 'Segurança do Trabalho',
        ];
    }

    /**
     * Relacionamento com a Empresa (Tenant)
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    /**
     * Funções (Roles) vinculadas a este departamento
     */
    public function roles(): HasMany
    {
        return $this->hasMany(Role::class, 'department_id');
    }

    /**
     * Funcionários (Users) alocados neste departamento
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'department_id');
    }
}
