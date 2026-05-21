<?php

namespace App\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasTenants;
use Filament\Panel;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Throwable;

class User extends Authenticatable implements FilamentUser, HasTenants
{
    use HasFactory, Notifiable, HasRoles;

    protected $fillable = [
        'name',
        'email',
        'password',
        'department_id',
        'hourly_rate',
        'tenant_id', // Adicionado para garantir persistência no multi-tenant
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'hourly_rate' => 'decimal:2',
    ];

    /**
     * 📂 RELACIONAMENTO DE DEPARTAMENTO
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    /**
     * 🔄 RELACIONAMENTO MULTI-TENANT (1:N)
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id', 'id')->withDefault(function () {
            try {
                // Protegido contra execuções em Jobs de fila ou Comandos Artisan onde o painel não existe
                return Filament::getTenant();
            } catch (Throwable $e) {
                return null;
            }
        });
    }

    /**
     * Relacionamento nativo conectando o Usuário aos seus Tenants (N:N)
     */
    public function tenants(): BelongsToMany
    {
        return $this->belongsToMany(Tenant::class, 'tenant_user', 'user_id', 'tenant_id');
    }

    public function roles(): BelongsToMany
    {
        return $this->morphToMany(
            config('permission.models.role'),
            'model',
            config('permission.table_names.model_has_roles'),
            config('permission.column_names.model_morph_key'),
            'role_id'
        );
    }

    public function isAdmin(): bool
    {
        if ($this->email && str_ends_with($this->email, '@oravel.com.br')) {
            return true;
        }

        return DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('model_has_roles.model_id', $this->id)
            ->where('model_has_roles.model_type', get_class($this))
            ->where('roles.name', 'admin')
            ->exists();
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return true;
    }

    public function getTenants(Panel $panel): Collection
    {
        if ($this->isAdmin()) {
            return Tenant::all();
        }

        return $this->tenants; 
    }

    public function canAccessTenant(Model $tenant): bool
    {
        // 1. Libera imediatamente se for admin da plataforma
        if ($this->isAdmin()) {
            return true;
        }

        // 2. Resolve direto no banco de dados via SQL ao invés de carregar collections na memória.
        // Além disso, evitamos chamar `Filament::getCurrentPanel()` aqui, pois em rotas POST de login 
        // o painel pode não estar 100% resolvido, gerando falhas de roteamento.
        return $this->tenants()->whereKey($tenant->getKey())->exists();
    }
}