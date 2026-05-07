<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Auth;

class InternalUnit extends Model
{
    use HasUuids, SoftDeletes, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'name',
        'code',
        'is_active',
        'zip_code',
        'address',
        'number',
        'neighborhood',
        'city',
        'state',
    ];

    /**
     * Gatilho de segurança: Garante que a unidade sempre pertença ao Tenant logado.
     */
    protected static function booted(): void
    {
        static::creating(function (InternalUnit $unit) {
            if (empty($unit->tenant_id) && Auth::check()) {
                $unit->tenant_id = Auth::user()->tenant_id;
            }
        });
    }

    /**
     * RELAÇÕES
     */

    // Lista de ativos alocados nesta unidade (veículos, máquinas, etc)
    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class, 'internal_unit_id');
    }
}