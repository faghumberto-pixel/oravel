<?php

namespace App\Models;

use App\Models\Concerns\HasSaaSMetadata;

use Spatie\Permission\Models\Role as SpatieRole;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Role extends SpatieRole
{
    use HasSaaSMetadata;

    protected static ?string $saasFeatureKey = "tabela_roles";
    protected static ?string $saasPermissionSlug = "funcao";
    protected static ?string $saasModuleLabel = "Perfis de Acesso";

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
    ];

    /**
     * Relação opcional para acessar o Tenant proprietário desta role,
     * caso precise de consultas rápidas.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }
}