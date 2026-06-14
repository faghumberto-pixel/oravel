<?php

namespace App\Models;

use App\Models\Concerns\HasSaaSMetadata;

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
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Throwable;

class User extends Authenticatable implements FilamentUser, HasTenants, MustVerifyEmail
{
    use HasSaaSMetadata;

    protected static ?string $saasFeatureKey = "tabela_users";
    protected static ?string $saasPermissionSlug = "funcionario";
    protected static ?string $saasModuleLabel = "Funcionarios";

    use HasFactory, Notifiable, HasRoles;

    protected $fillable = [
        'name',
        'email',
        'password',
        'department_id',
        'hourly_rate',
        'tenant_id',
        'role',
        'last_seen', // Adicionado aqui
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'hourly_rate' => 'decimal:2',
        'last_seen' => 'datetime', // Adicionado aqui
    ];

    public function routeNotificationForDatabase($notification)
    {
        return $this->notifications();
    }

    public function podeReceberFinancas(): bool
    {
        if ($this->isSuperAdmin()) return true;

        $temRegraFinanceira = $this->hasAnyRole(['financeiro', 'admin', 'gerente']) 
            || in_array(strtolower($this->role), ['financeiro', 'admin', 'gerente']);

        if ($temRegraFinanceira) return true;

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

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id', 'id')->withDefault(function () {
            try {
                return Filament::getTenant();
            } catch (Throwable $e) {
                return null;
            }
        });
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
        if ($this->isSuperAdmin()) return true;

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
        if ($this->isSuperAdmin()) return Tenant::all();
        return Tenant::where('id', $this->tenant_id)->get();
    }

    public function canAccessTenant(Model $tenant): bool
    {
        if ($this->isSuperAdmin()) return true;
        return (string) $this->tenant_id === (string) $tenant->getKey();
    }

    /**
     * Verifica se o usuário está online com base no último acesso.
     */
    public function isOnline(): bool
    {
        return $this->last_seen && $this->last_seen->gt(now()->subMinutes(5));
    }
}
