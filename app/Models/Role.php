<?php

namespace App\Models;

use Spatie\Permission\Models\Role as SpatieRole;
use Illuminate\Database\Eloquent\Builder;
// use Illuminate\Database\Eloquent\Concerns\HasUuids; // Descomente se a PK da Role for UUID

class Role extends SpatieRole
{
    // use HasUuids;

    protected static function booted()
    {
        // 1. Escopo Global: Garante que o sistema só liste as Roles do Tenant logado
        static::addGlobalScope('tenant', function (Builder $builder) {
            if (auth()->check() && auth()->user()->tenant_id) {
                // CORREÇÃO: qualifyColumn() adiciona o nome da tabela (roles.tenant_id)
                // Isso elimina o erro SQLSTATE[42702]: Ambiguous column
                $builder->where($builder->qualifyColumn('tenant_id'), auth()->user()->tenant_id);
            }
        });

        // 2. Injeção Automática: Ao criar uma nova Role, insere o tenant_id automaticamente
        static::creating(function ($role) {
            if (auth()->check() && empty($role->tenant_id)) {
                $role->tenant_id = auth()->user()->tenant_id;
            }
        });
    }

    /**
     * Relacionamento customizado para amarrar a função a um setor específico
     */
    public function department()
    {
        return $this->belongsTo(Department::class, 'department_id');
    }
}