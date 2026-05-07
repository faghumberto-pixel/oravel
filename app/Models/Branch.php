<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Branch extends Model
{
    use HasFactory, HasUuids, SoftDeletes, BelongsToTenant;

    protected $fillable = [
        'name',
        'description',
        'tenant_id',
    ];

    /**
     * Relação com as Ordens de Serviço (Internas) vinculadas a esta unidade.
     */
    public function maintenanceOrders(): HasMany
    {
        return $this->hasMany(MaintenanceOrder::class, 'branch_id');
    }

    /**
     * Relação com os Ativos que estão custodiados ou pertencem a esta unidade/filial.
     */
    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class, 'branch_id');
    }
}