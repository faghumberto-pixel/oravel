<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

class Department extends Model
{
    use HasUuids;

    protected $fillable = [
        'name',
        'code',       // Adicionado: necessário para salvar o código (ex: MANUT001)
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

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}