<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Filament\Models\Contracts\FilamentUser; // Import obrigatório para o Filament
use Filament\Panel; // Import obrigatório para o Filament
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements MustVerifyEmail, FilamentUser
{
    use HasApiTokens, HasFactory, Notifiable, HasRoles;
    use BelongsToTenant;

    protected $fillable = [
        'name',
        'email',
        'password',
        'tenant_id',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    /**
     * Implementação da interface FilamentUser:
     * Define quem pode acessar o painel administrativo.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        // Aqui você pode adicionar regras de negócio. 
        // Por enquanto, permitimos acesso a qualquer usuário logado.
        return true;
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}