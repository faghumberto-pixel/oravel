<?php

namespace App\Models;

use App\Models\Concerns\HasSaaSMetadata;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Permission\Models\Role as SpatieRole;

class Role extends SpatieRole
{
    use HasSaaSMetadata;

    protected static ?string $saasFeatureKey = 'tabela_roles';

    protected static ?string $saasPermissionSlug = 'funcao';

    protected static ?string $saasModuleLabel = 'Perfis de Acesso';

    /**
     * O modelo Role estende SpatieRole para suportar o gerenciamento de teams/tenants.
     * Com a configuração 'teams' => true no config/permission.php,
     * o Spatie utilizará automaticamente a coluna 'tenant_id' definida
     * no team_foreign_key para filtrar as permissões por tenant.
     */
    protected $fillable = [
        'name',
        'guard_name',
        'tenant_id', // Essencial para o escopo do tenant
        'sees_all_crm_leads',
        'department_id',
        'hierarchy_level',
    ];

    protected $casts = [
        'sees_all_crm_leads' => 'boolean',
        'hierarchy_level' => 'integer',
    ];

    /**
     * Nivel hierarquico anexavel a qualquer papel, junto com department_id
     * -- mesmo padrao aditivo de "privilegio anexavel a qualquer perfil" ja
     * usado por department_id/sees_all_crm_leads. So' entra em vigor onde
     * algum ponto do sistema checar explicitamente (ver
     * User::hasMinimumLevelInSector()); papel sem hierarchy_level continua
     * funcionando exatamente como hoje.
     */
    public const LEVEL_TECNICO = 1;

    public const LEVEL_AUXILIAR = 2;

    public const LEVEL_ASSISTENTE = 3;

    public const LEVEL_ANALISTA = 4;

    public const LEVEL_SUPERVISOR = 5;

    public const LEVEL_GERENTE = 6;

    /**
     * @return array<int, string>
     */
    public static function levelLabels(): array
    {
        return [
            self::LEVEL_TECNICO => 'Técnico',
            self::LEVEL_AUXILIAR => 'Auxiliar',
            self::LEVEL_ASSISTENTE => 'Assistente',
            self::LEVEL_ANALISTA => 'Analista',
            self::LEVEL_SUPERVISOR => 'Supervisor',
            self::LEVEL_GERENTE => 'Gerente',
        ];
    }

    /**
     * Relação opcional para acessar o Tenant proprietário desta role,
     * caso precise de consultas rápidas.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id');
    }
}
