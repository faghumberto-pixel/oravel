<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

class MaintenanceOrderMaterial extends Model
{
    use BelongsToTenant;
    use HasFactory;

    // Todos os Traits agora declarados corretamente DENTRO das chaves da classe
    use HasUuids;

    protected $fillable = [
        'maintenance_order_id',
        'material_id',
        'name',
        'quantity',
        'serial_number',
        'unit_price',
        'tenant_id',
    ];

    protected $casts = [
        'unit_price' => 'decimal:2',
    ];

    /**
     * Gatilho de segurança para garantir o tenant_id + bloquear a gravação
     * (nao so alertar depois) quando o material exige numero de serie --
     * tem que acontecer em creating(), antes do INSERT, senao a linha ja
     * fica salva sem o dado obrigatorio quando a excecao estoura.
     */
    protected static function booted(): void
    {
        static::creating(function ($model) {
            if (empty($model->tenant_id) && Auth::check()) {
                $model->tenant_id = Auth::user()->tenant_id;
            }

            if ($model->material_id) {
                $material = Material::find($model->material_id);

                if ($material?->requires_serial_number && blank($model->serial_number)) {
                    throw new \InvalidArgumentException(
                        "O material \"{$material->name}\" exige número de série antes de aplicar."
                    );
                }

                if (blank($model->name)) {
                    $model->name = $material?->name;
                }
            }
        });
    }

    public function maintenanceOrder(): BelongsTo
    {
        return $this->belongsTo(MaintenanceOrder::class, 'maintenance_order_id');
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }
}
