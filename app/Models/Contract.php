<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant; // Vital para o Multi-tenancy
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Support\Facades\Auth;

class Contract extends Model
{
    use SoftDeletes, HasUuids, BelongsToTenant;

    /**
     * Usar $fillable é mais seguro para o core comercial do Oravel.
     * Incluí todos os campos que adicionamos na migration.
     */
    protected $fillable = [
        'tenant_id',
        'client_id',
        'asset_id',
        'contract_number',
        'start_date',
        'end_date',
        'price',
        'payment_method',
        'usage_purpose',
        'required_nrs',
        'prohibit_sublease',
        'maintenance_clause',
        'initial_horimeter',
        'initial_odometer',
        'cep_obra',
        'latitude_obra',
        'longitude_obra',
        'legal_forum',
        'insurance_details',
        'is_active',
        'status',
        'observations'
    ];

    /**
     * Casting para garantir que datas sejam objetos Carbon e 
     * valores decimais/booleanos sejam tratados corretamente.
     */
    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'price' => 'decimal:2',
        'initial_horimeter' => 'decimal:2',
        'initial_odometer' => 'decimal:2',
        'prohibit_sublease' => 'boolean',
        'is_active' => 'boolean',
    ];

    /**
     * Booted para garantir que o tenant_id nunca vá nulo para o Postgres.
     */
    protected static function booted()
    {
        static::creating(function ($model) {
            if (empty($model->tenant_id) && auth()->check()) {
                $model->tenant_id = auth()->user()->tenant_id;
            }
        });
    }

    /** --- Relações --- **/

    public function tenant(): BelongsTo 
    { 
        return $this->belongsTo(Tenant::class); 
    }

    public function client(): BelongsTo 
    { 
        return $this->belongsTo(Client::class); 
    }

    public function asset(): BelongsTo 
    { 
        return $this->belongsTo(Asset::class, 'asset_id'); 
    }

    /** --- Helpers de Negócio --- **/

    /**
     * Verifica se o contrato está vencido.
     */
    public function getIsExpiredAttribute(): bool
    {
        return $this->end_date && $this->end_date->isPast();
    }
}