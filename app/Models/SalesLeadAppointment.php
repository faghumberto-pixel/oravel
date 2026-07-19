<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Compromisso agendado (tela "Programacao" / agenda do CRM comercial) --
 * status proprio do compromisso, nao so uma data de follow-up.
 */
class SalesLeadAppointment extends Model
{
    use HasFactory;
    use HasUuids;

    public const STATUS_PENDENTE = 'pendente';

    public const STATUS_AGUARDANDO = 'aguardando';

    public const STATUS_EM_ANDAMENTO = 'em_andamento';

    public const STATUS_CONCLUIDO = 'concluido';

    public const TYPE_DEMONSTRACAO = 'demonstracao';

    public const TYPE_LIGACAO = 'ligacao';

    public const TYPE_REUNIAO = 'reuniao';

    public const TYPE_OUTRO = 'outro';

    protected $fillable = [
        'sales_lead_id',
        'assigned_user_id',
        'title',
        'notes',
        'type',
        'scheduled_at',
        'status',
        'completed_at',
        'last_alerted_at',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'completed_at' => 'datetime',
        'last_alerted_at' => 'datetime',
    ];

    public static function statusLabels(): array
    {
        return [
            self::STATUS_PENDENTE => 'Pendente',
            self::STATUS_AGUARDANDO => 'Aguardando',
            self::STATUS_EM_ANDAMENTO => 'Em Andamento',
            self::STATUS_CONCLUIDO => 'Concluído',
        ];
    }

    public static function statusColors(): array
    {
        return [
            self::STATUS_PENDENTE => '#dc2626',
            self::STATUS_AGUARDANDO => '#f59e0b',
            self::STATUS_EM_ANDAMENTO => '#0ea5e9',
            self::STATUS_CONCLUIDO => '#16a34a',
        ];
    }

    public static function typeLabels(): array
    {
        return [
            self::TYPE_DEMONSTRACAO => 'Demonstração',
            self::TYPE_LIGACAO => 'Ligação',
            self::TYPE_REUNIAO => 'Reunião',
            self::TYPE_OUTRO => 'Outro',
        ];
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(SalesLead::class, 'sales_lead_id');
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }
}
