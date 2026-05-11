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
    
    protected $fillable = [
        'maintenance_order_id', 
        'checklist_group_id', 
        'section', 
        'item_name', 
        'category', 
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
     * RELAÇÃO OBRIGATÓRIA PARA O FILAMENT MULTI-TENANCY
     * Isso resolve o erro: "model does not have a relationship named [tenant]"
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Gatilhos de automação (booted).
     */
    protected static function booted(): void
    {
        static::creating(function (MaintenanceOrderChecklist $checklist) {
            // Garante o vínculo com a empresa logada
            $checklist->tenant_id ??= Auth::user()?->tenant_id;
            
            // Valores padrão de segurança para evitar erros de banco de dados
            $checklist->item_name    ??= 'Tarefa sem descrição';
            $checklist->section      ??= 'Geral';
            $checklist->category     ??= 'Geral'; 
            $checklist->is_completed ??= false;
            $checklist->is_template  ??= false; 
        });
    }

    /**
     * OUTRAS RELAÇÕES
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