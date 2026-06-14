<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class MaintenanceStatusHistory extends Model
{
    use HasUuids; // Essencial pois usamos UUID na migration

    protected $table = 'maintenance_status_histories';

    // Desabilitamos timestamps automáticos do Laravel pois usaremos 'created_at' (ou changed_at)
    public $timestamps = false; 
    
    protected $fillable = [
        'tenant_id',
        'maintenance_order_id',
        'old_status',
        'new_status',
        'observation',
        'user_id',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    /**
     * Relacionamento com a Ordem de Serviço
     */
    public function maintenanceOrder(): BelongsTo
    {
        return $this->belongsTo(MaintenanceOrder::class, 'maintenance_order_id');
    }

    /**
     * Relacionamento com o Usuário que realizou a alteração
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}