<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasSaaSMetadata;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Falta/ausencia registrada pelo proprio colaborador (app mobile), com
 * atestado opcional anexado. RH aprova/rejeita pelo admin
 * (App\Filament\Resources\AbsenceResource).
 */
class Absence extends Model
{
    use BelongsToTenant;
    use HasSaaSMetadata;
    use HasUuids;

    protected static ?string $saasFeatureKey = 'tabela_absences';

    protected static ?string $saasPermissionSlug = 'ausencia';

    protected static ?string $saasModuleLabel = 'Faltas e Ausências';

    public const STATUS_PENDENTE = 'pendente';

    public const STATUS_APROVADO = 'aprovado';

    public const STATUS_REJEITADO = 'rejeitado';

    protected $fillable = [
        'tenant_id',
        'employee_id',
        'start_date',
        'end_date',
        'reason',
        'attachment_path',
        'status',
        'reviewed_by',
        'reviewed_at',
        'review_notes',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'reviewed_at' => 'datetime',
    ];

    public static function statusLabels(): array
    {
        return [
            self::STATUS_PENDENTE => 'Pendente',
            self::STATUS_APROVADO => 'Aprovado',
            self::STATUS_REJEITADO => 'Rejeitado',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
