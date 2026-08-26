<?php

namespace App\Models;

use App\Models\Concerns\HasSaaSMetadata;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasAvatar;
use Filament\Models\Contracts\HasTenants;
use Filament\Panel;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Jeffgreco13\FilamentBreezy\Traits\TwoFactorAuthenticatable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements FilamentUser, HasAvatar, HasTenants, MustVerifyEmail
{
    use HasApiTokens, HasFactory, HasRoles, HasUuids, Notifiable;
    use HasSaaSMetadata;
    use TwoFactorAuthenticatable;

    protected static ?string $saasFeatureKey = 'tabela_users';

    protected static ?string $saasPermissionSlug = 'funcionario';

    protected static ?string $saasModuleLabel = 'Funcionarios';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'name',
        'email',
        'password',
        'department_id',
        'hourly_rate',
        'tenant_id',
        'role',
        'last_seen',
        'is_approved',
        'job_title',
        'avatar_url',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'hourly_rate' => 'decimal:2',
        'last_seen' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::saved(function ($user) {
            Cache::forget('spatie.permission.cache');
        });
    }

    public function routeNotificationForDatabase($notification)
    {
        return $this->notifications();
    }

    public function podeReceberFinancas(): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        $temRegraFinanceira = $this->hasAnyRole(['financeiro', 'admin', 'gerente'])
            || in_array(strtolower($this->role), ['financeiro', 'admin', 'gerente']);

        if ($temRegraFinanceira) {
            return true;
        }

        if ($this->department()->exists()) {
            $nomeDepartamento = strtolower($this->department->name ?? '');

            return str_contains($nomeDepartamento, 'finan') || str_contains($nomeDepartamento, 'adm');
        }

        return false;
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    public function maintenanceOrders(): HasMany
    {
        return $this->hasMany(MaintenanceOrder::class, 'technician_id');
    }

    public function specialties(): HasMany
    {
        return $this->hasMany(UserSpecialty::class);
    }

    /**
     * $failureCategory e' um dos MaintenanceOrder::FAILURE_CATEGORY_* --
     * mesmo vocabulario reaproveitado como especialidade do tecnico (ver
     * migration user_specialty).
     */
    public function hasSpecialty(string $failureCategory): bool
    {
        return $this->specialties()->where('specialty', $failureCategory)->exists();
    }

    /**
     * IDs dos setores que este usuario supervisiona -- qualquer role dele
     * com roles.department_id preenchido (ver RoleResource, campo "Setor
     * supervisionado"). Vazio = usuario nao supervisiona ninguem, so ve a
     * propria agenda em Programacao (AgendaTecnicoWidget).
     *
     * @return array<int, string>
     */
    public function supervisedDepartmentIds(): array
    {
        return $this->roles()
            ->whereNotNull('department_id')
            ->pluck('department_id')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Verdadeira trava de hierarquia: o usuario tem algum papel com
     * hierarchy_level >= $minLevel num Department deste tenant cujo
     * sector_key seja $sectorKey. Usado por telas que precisam garantir
     * "so' Supervisor+ da Logistica" em vez de so' checar permissao
     * generica de CRUD (ver PatioAprovacoes::approve()).
     */
    public function hasMinimumLevelInSector(string $sectorKey, int $minLevel): bool
    {
        $departmentIds = Department::where('tenant_id', $this->tenant_id)
            ->where('sector_key', $sectorKey)
            ->pluck('id');

        if ($departmentIds->isEmpty()) {
            return false;
        }

        return $this->roles()
            ->whereIn('department_id', $departmentIds)
            ->where('hierarchy_level', '>=', $minLevel)
            ->exists();
    }

    /**
     * Áreas de ClientMessage que este usuário pode ver -- admin vê tudo
     * (mesmo bypass usado em AbstractPolicy), demais usuários só veem
     * a área cuja Role dedicada (ClientMessage::areaRoleName()) eles
     * possuem. $this->roles() já é implicitamente tenant-scoped (via
     * model_has_roles, só roles atribuídas a este User específico) --
     * não precisa re-filtrar por tenant_id aqui.
     *
     * @return array<int, string>
     */
    public function visibleClientMessageAreas(): array
    {
        if ($this->isAdmin()) {
            return array_keys(ClientMessage::areaLabels());
        }

        $roleNames = $this->roles->pluck('name')->all();

        return array_values(array_filter(
            array_keys(ClientMessage::areaLabels()),
            fn (string $area) => in_array(ClientMessage::areaRoleName($area), $roleNames, true)
        ));
    }

    /**
     * SEM withDefault() de proposito -- ->withDefault(closure) do Eloquent
     * sempre cria um Tenant "vazio" quando o relacionamento nao resolve
     * (mesmo a closure "retornando null"), nunca null de verdade. Isso
     * fazia Tenancy::current() nunca ser null pra um usuario sem
     * tenant_id valido, e telas como brand-logo.blade.php
     * (`@if($tenant)` -- objeto vazio ainda e' truthy em PHP) renderizavam
     * o elemento com nome em branco em vez de simplesmente nao mostrar
     * nada. Filament::getTenant() tambem sempre retorna null neste painel
     * (nao usamos tenancy nativa), entao o valor "default" nunca fazia
     * sentido de qualquer forma.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id', 'id');
    }

    public function tenants(): BelongsToMany
    {
        return $this->belongsToMany(Tenant::class, 'tenant_user', 'user_id', 'tenant_id');
    }

    /**
     * Super admin da plataforma definido por lista explicita em config/oravel.php
     * (alimentada pelo .env SUPER_ADMINS). NUNCA pelo dominio do e-mail, e NUNCA
     * editavel pela interface da aplicacao.
     */
    public function isSuperAdmin(): bool
    {
        if (! $this->email) {
            return false;
        }

        return in_array(strtolower($this->email), config('oravel.super_admins', []), true);
    }

    public function isAdmin(): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        return DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('model_has_roles.model_id', $this->id)
            ->where('model_has_roles.model_type', get_class($this))
            ->where('roles.name', 'admin')
            ->exists();
    }

    /**
     * "Ve todos os leads do CRM" nao e' role fixa -- e' um privilegio que
     * o admin do tenant liga em qualquer perfil (roles.sees_all_crm_leads,
     * mesmo padrao de roles.department_id pra Programacao). Mesmo estilo
     * de query direta de isAdmin(), evitando o cache pesado do
     * hasRole()/getAllPermissions() do Spatie.
     */
    public function canSeeAllCrmLeads(): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        return DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('model_has_roles.model_id', $this->id)
            ->where('model_has_roles.model_type', get_class($this))
            ->where('roles.sees_all_crm_leads', true)
            ->exists();
    }

    /**
     * O painel 'central' (operador SaaS: planos, tenants, receita entre
     * empresas) e' restrito a super admin -- ANTES disso retornava true
     * sempre, entao qualquer admin de qualquer tenant conseguia acessar
     * /central e ver dados de todos os outros tenants (achado de auditoria
     * de permissoes, 2026-07-08). O painel 'admin' (tenant) exige
     * is_approved=true -- usuarios que vieram do auto-cadastro
     * (Login::register()) nascem com is_approved=false e ficam bloqueados
     * ate o admin do tenant aprovar em UserResource; usuarios criados por
     * seeder/TenantProvisioner/admin nascem aprovados (default da coluna).
     * A trava por modulo/plano continua nas Policies, nao aqui.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        if ($panel->getId() === 'central') {
            return $this->isSuperAdmin();
        }

        return (bool) $this->is_approved;
    }

    /**
     * Sem isso, FilamentManager::getUserAvatarUrl() usa avatar_url "cru"
     * (o FileUpload::avatar() do filament-breezy salva so o caminho
     * relativo ao disco, nao a URL) -- o <img src> quebrava e o avatar
     * nunca aparecia, mesmo com o upload funcionando.
     */
    public function getFilamentAvatarUrl(): ?string
    {
        return $this->avatar_url ? Storage::disk('public')->url($this->avatar_url) : null;
    }

    public function getTenants(Panel $panel): Collection
    {
        if ($this->isSuperAdmin()) {
            return Tenant::all();
        }

        return Tenant::where('id', $this->tenant_id)->get();
    }

    public function canAccessTenant(Model $tenant): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        return (string) $this->tenant_id === (string) $tenant->getKey();
    }

    public function isOnline(): bool
    {
        return $this->last_seen && $this->last_seen->gt(now()->subMinutes(5));
    }
}
