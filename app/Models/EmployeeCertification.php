<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * Certificacao de Norma Regulamentadora (NR) do colaborador -- mesmo padrao
 * de FleetDriverDocument (documento com vencimento + upload via Media
 * Library), aplicado a colaborador em vez de motorista de frota.
 */
class EmployeeCertification extends Model implements HasMedia
{
    use BelongsToTenant;
    use HasUuids;
    use InteractsWithMedia;

    protected $fillable = [
        'tenant_id',
        'employee_id',
        'norma',
        'data_emissao',
        'data_validade',
        'observacoes',
    ];

    protected $casts = [
        'data_emissao' => 'date',
        'data_validade' => 'date',
    ];

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('arquivo')->singleFile();
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function isVencida(): bool
    {
        return $this->data_validade && $this->data_validade->isPast();
    }

    public function isProximoVencimento(int $dias = 30): bool
    {
        return $this->data_validade
            && ! $this->isVencida()
            && $this->data_validade->lessThanOrEqualTo(now()->addDays($dias));
    }
}
