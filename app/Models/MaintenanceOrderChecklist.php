<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

class MaintenanceOrderChecklist extends Model
{
    use HasUuids, BelongsToTenant;

    protected $table = 'maintenance_order_checklists';
    
    /**
     * Campos preenchíveis.
     * Incluído 'category' para evitar violações de restrição NOT NULL no banco.
     */
    protected $fillable = [
        'maintenance_order_id', 
        'checklist_group_id', 
        'section', 
        'item_name', 
        'category', // Adicionado
        'is_completed', 
        'is_template', 
        'notes', 
        'tenant_id'
    ];

    protected $casts = [
        'is_completed' => 'boolean',
        'is_template'  => 'boolean',
    ];

    /**
     * Gatilhos de automação (booted).
     */
    protected static function booted(): void
    {
        static::creating(function (MaintenanceOrderChecklist $checklist) {
            $checklist->tenant_id ??= Auth::user()?->tenant_id;
            
            // Valores padrão de segurança
            $checklist->item_name  ??= 'Tarefa sem descrição';
            $checklist->section    ??= 'Geral';
            $checklist->category   ??= 'Geral'; // Valor padrão para evitar nulos
            $checklist->is_completed ??= false;
            $checklist->is_template  ??= false; 
        });
    }

    /**
     * RELAÇÕES
     */

    public function group(): BelongsTo
    {
        return $this->belongsTo(ChecklistGroup::class, 'checklist_group_id');
    }

    public function maintenanceOrder(): BelongsTo
    {
        return $this->belongsTo(MaintenanceOrder::class, 'maintenance_order_id');
    }
}