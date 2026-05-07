<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Auth;

class Client extends Model
{
    use HasFactory, HasUuids, SoftDeletes, BelongsToTenant;

    protected $fillable = [
        'name',
        'activity_type', // <-- INCLUSÃO MÍNIMA: É isso que libera o salvamento no banco!
        'cpf_cnpj',
        'contact_name',
        'cep',
        'address',
        'city',
        'uf',
        'phone',
        'whatsapp',
        'tenant_id',
    ];

    protected static function booted()
    {
        static::creating(function ($client) {
            if (Auth::check() && empty($client->tenant_id)) {
                $client->tenant_id = Auth::user()->tenant_id;
            }
        });
    }

    /**
     * Relacionamento: Um cliente pode ter várias OS
     */
    public function maintenanceOrders(): HasMany
    {
        return $this->hasMany(MaintenanceOrder::class);
    }
}