<?php

namespace App\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasTenants;
use Filament\Panel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
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
        'hourly_rate',
        'last_seen_at' // Injetado de forma segura no fillable
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'hourly_rate' => 'decimal:2',
        'last_seen_at' => 'datetime', // BLINDAGEM OBRIGATÓRIA CONTRA CRASH DE STRING NO BLADE
    ];

    // --- FILAMENT TENANCY INTERFACE ---
    
    /**
     * Retorna os tenants aos quais o usuário tem acesso.
     */
    public function getTenants(Panel $panel): \Illuminate\Support\Collection
    {
        // Administradores do sistema (SaaS Owner) acessam tudo
        if (str_ends_with($this->email, '@oravel.com.br')) {
            return Tenant::all();
        }

        // Usuário comum: só acessa o próprio tenant vinculado
        return collect([$this->tenant])->filter();
    }

    /**
     * Valida se o usuário pode acessar um tenant específico.
     */
    public function canAccessTenant(Model $tenant): bool
    {
        // Dono do SaaS tem acesso irrestrito
        if (str_ends_with($this->email, '@oravel.com.br')) {
            return true;
        }

        // Comparação de segurança para usuários comuns
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

    /**
     * AJUSTE ORGANOGRAMA: Relacionamento do usuário com o Departamento do organograma
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    /**
     * RELACIONAMENTO: Um usuário pertence a muitas salas de chat (pivô chat_room_user)
     * Utilizado para o motor de busca global de auditoria de termos do pátio corporativo.
     */
    public function chatRooms(): BelongsToMany
    {
        return $this->belongsToMany(
            ChatRoom::class,    // Modelo da sala
            'chat_room_user',   // Tabela pivô no banco
            'user_id',          // Chave estrangeira deste modelo na pivô
            'chat_room_id'      // Chave estrangeira da sala na pivô
        );
    }

    /**
     * Define quem pode fazer login no painel administrativo.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        // Se for o painel central, apenas e-mails @oravel entram
        if ($panel->getId() === 'central') {
            return str_ends_with($this->email, '@oravel.com.br');
        }

        // Para os outros painéis (admin/app), liberamos o acesso base
        // O canAccessTenant cuidará do filtro por empresa
        return true;
    }

    /**
     * Ajuste Spatie + Tenancy
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