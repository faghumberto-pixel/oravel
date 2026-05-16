<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Models\Role;

class Department extends Model
{
    use HasUuids;

    protected $fillable = [
        'name',
        'code',       // Necessário para salvar o código (ex: MANUT001)
        'tenant_id'
    ];

    /**
     * Gatilho de segurança para garantir que o tenant_id seja sempre preenchido,
     * mesmo que o formulário falhe ou seja ignorado.
     */
    protected static function booted(): void
    {
        static::creating(function (Department $department) {
            // Verifica se o tenant_id está vazio e se há um usuário autenticado
            // Se estivermos em um seeder, o tenant_id deve vir preenchido manualmente
            if (empty($department->tenant_id) && Auth::check()) {
                $department->tenant_id = Auth::user()->tenant_id;
            }
        });
    }

    /**
     * Relacionamento com a Empresa (Tenant)
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    /**
     * INTEGRADO: Retorna as Funções (Roles) vinculadas a este departamento
     */
    public function roles(): HasMany
    {
        return $this->hasMany(Role::class, 'department_id');
    }

    /**
     * INTEGRADO: Retorna os Funcionários (Users) alocados neste departamento
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'department_id');
    }
}