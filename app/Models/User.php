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

    // --- FILAMENT TENANCY INTERFACE (ESSENCIAL PARA O SAAS) ---
    public function getTenants(Panel $panel): \Illuminate\Support\Collection
    {
        // Se for o dono do SaaS, ele lista e acessa TODAS as empresas
        if (str_ends_with($this->email, '@oravel.com.br')) {
            return Tenant::all();
        }

        // Clientes normais veem apenas a própria empresa
        return collect([$this->tenant])->filter();
    }

    public function canAccessTenant(Model $tenant): bool
    {
        // O dono do SaaS tem passe livre para entrar em qualquer tenant
        if (str_ends_with($this->email, '@oravel.com.br')) {
            return true;
        }

        // Validação de segurança para clientes normais (isolamento)
        return $this->tenant_id === $tenant->id;
    }
    // ----------------------------------------------------------

    protected static function booted(): void
    {
        // 1. Vínculo Automático: Novos usuários herdam o tenant de quem os criou
        static::creating(function ($user) {
            if (auth()->check() && auth()->user()->tenant_id) {
                $user->tenant_id = $user->tenant_id ?? auth()->user()->tenant_id;
            }
        });

        // O GLOBAL SCOPE FOI REMOVIDO DAQUI PARA ACABAR COM O LOOP INFINITO NO LOGIN
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    public function canAccessPanel(Panel $panel): bool
    {
        // Regra do QG: O painel /central é exclusivo do proprietário
        if ($panel->getId() === 'central') {
            return str_ends_with($this->email, '@oravel.com.br');
        }

        // O painel normal (/admin) é liberado para todos
        return true;
    }

    /**
     * Ajuste para compatibilidade Spatie + Multi-tenancy
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