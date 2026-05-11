<?php

namespace App\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasTenants;
use Filament\Panel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Tenant;

class User extends Authenticatable implements FilamentUser, HasTenants
{
    use HasApiTokens, HasFactory, HasRoles, Notifiable;

    protected $fillable = [
        'name', 
        'email', 
        'password', 
        'tenant_id', 
        'role',
        'company_id',
        'department_id',
        'hourly_rate'
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

    // --- FILAMENT TENANCY INTERFACE ---
    
    /**
     * Retorna os tenants aos quais o usuário tem acesso.
     * Crucial para evitar o 403.
     */
    public function getTenants(Panel $panel): \Illuminate\Support\Collection
    {
        // Administradores do sistema (SaaS Owner)
        if (str_ends_with($this->email, '@oravel.com.br')) {
            return Tenant::all();
        }

        // Usuário comum: só acessa o próprio tenant vinculado
        return collect([$this->tenant])->filter();
    }

    /**
     * Valida se o usuário pode acessar um tenant específico.
     * Se retornar false aqui, gera o erro 403 Forbidden.
     */
    public function canAccessTenant(Model $tenant): bool
    {
        // Dono do SaaS tem acesso irrestrito
        if (str_ends_with($this->email, '@oravel.com.br')) {
            return true;
        }

        // Bloqueio de segurança: o tenant da URL deve ser o mesmo do perfil do usuário
        // Usamos (string) para garantir comparação correta de UUIDs
        return (string) $this->tenant_id === (string) $tenant->id;
    }
    // ----------------------------------

    protected static function booted(): void
    {
        static::creating(function ($user) {
            if (auth()->check() && auth()->user()->tenant_id) {
                $user->tenant_id = $user->tenant_id ?? auth()->user()->tenant_id;
            }
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    public function canAccessPanel(Panel $panel): bool
    {
        if ($panel->getId() === 'central') {
            return str_ends_with($this->email, '@oravel.com.br');
        }

        return true;
    }

    /**
     * Ajuste Spatie + Tenancy
     * Removido scopes para evitar conflitos de permissão entre tenants
     */
    public function roles(): \Illuminate\Database\Eloquent\Relations\MorphToMany
    {
        return $this->morphToMany(
            \Spatie\Permission\Models\Role::class,
            'model',
            config('permission.table_names.model_has_roles'),
            'model_id',
            'role_id'
        )->withoutGlobalScopes();
    }
}