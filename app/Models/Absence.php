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
        'type',
        'start_date',
        'end_date',
        'reason',
        'cid_code',
        'details',
        'attachment_path',
        'attachments',
        'status',
        'reviewed_by',
        'reviewed_at',
        'review_notes',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'attachments' => 'array',
        'reviewed_at' => 'datetime',
    ];

    public const TIPO_ATESTADO = 'atestado_medico';

    /** @return array<string, string> */
    public static function typeLabels(): array
    {
        return [
            self::TIPO_ATESTADO => 'Atestado médico',
            'consulta_exame' => 'Consulta ou exame',
            'falta_justificada' => 'Falta justificada',
            'falta_injustificada' => 'Falta sem justificativa',
            'acompanhamento_familiar' => 'Acompanhamento de familiar',
            'licenca' => 'Licença (luto, casamento, paternidade...)',
            'outro' => 'Outro',
        ];
    }

    /** Dias corridos do período (início e fim contam). */
    public function getDaysAttribute(): int
    {
        return $this->start_date->diffInDays($this->end_date) + 1;
    }

    /** @return array<int, string> caminhos no disco local, incluindo o anexo antigo (attachment_path) */
    public function attachmentPaths(): array
    {
        return array_values(array_unique(array_filter([...($this->attachments ?? []), $this->attachment_path])));
    }

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
