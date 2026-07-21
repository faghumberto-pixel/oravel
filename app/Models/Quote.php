<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasSaaSMetadata;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Orçamento pro cliente final -- peças (Almoxarifado) + serviços, com
 * fluxo de aprovação. quotable (polimorfico) liga numa MaintenanceOrder
 * (orçamento comum) ou EquipmentDamage (orçamento indenizatorio); fica
 * null quando e' um orçamento comercial solto, sem origem.
 *
 * Cobre os POPs 1-4 da auditoria (elaboração, orçamento a terceiro, envio
 * pra aprovação do cliente, conclusão do processo). Ver
 * App\Models\EquipmentDamage pro POP 5/6, que ja existia antes.
 */
class Quote extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use HasSaaSMetadata;
    use HasUuids;
    use LogsActivity;

    public const TYPE_INTERNO = 'interno';

    public const TYPE_TERCEIRO = 'terceiro';

    public const TYPE_INDENIZATORIO = 'indenizatorio';

    public const STATUS_RASCUNHO = 'rascunho';

    public const STATUS_ENVIADO = 'enviado';

    public const STATUS_APROVADO = 'aprovado';

    public const STATUS_REPROVADO = 'reprovado';

    public const STATUS_CONCLUIDO = 'concluido';

    protected static ?string $saasFeatureKey = 'tabela_quotes';

    protected static ?string $saasPermissionSlug = 'orcamento';

    protected static ?string $saasModuleLabel = 'Orçamentos';

    /**
     * Default tambem em PHP (nao so' na migration) -- sem isso, status/type
     * ficam null no objeto em memoria logo apos create() ate' um refresh(),
     * porque o Eloquent nao releh o DEFAULT do banco sozinho (mesma
     * armadilha ja documentada em SalesLead::pipeline_stage).
     */
    protected $attributes = [
        'status' => self::STATUS_RASCUNHO,
        'type' => self::TYPE_INTERNO,
        'total_value' => 0,
    ];

    protected $fillable = [
        'tenant_id',
        'quotable_type',
        'quotable_id',
        'client_id',
        'assigned_user_id',
        'third_party_supplier_id',
        'type',
        'status',
        'technical_report',
        'total_value',
        'sent_at',
        'client_viewed_at',
        'client_responded_at',
        'rejection_reason',
        'completed_at',
        'financeiro_forwarded_at',
        'approval_token',
    ];

    protected $casts = [
        'total_value' => 'decimal:2',
        'sent_at' => 'datetime',
        'client_viewed_at' => 'datetime',
        'client_responded_at' => 'datetime',
        'completed_at' => 'datetime',
        'financeiro_forwarded_at' => 'datetime',
    ];

    /**
     * @return array<string, string>
     */
    public static function typeLabels(): array
    {
        return [
            self::TYPE_INTERNO => 'Interno',
            self::TYPE_TERCEIRO => 'A Terceiro',
            self::TYPE_INDENIZATORIO => 'Indenizatório',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function statusLabels(): array
    {
        return [
            self::STATUS_RASCUNHO => 'Rascunho',
            self::STATUS_ENVIADO => 'Enviado',
            self::STATUS_APROVADO => 'Aprovado',
            self::STATUS_REPROVADO => 'Reprovado',
            self::STATUS_CONCLUIDO => 'Concluído',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnlyDirty();
    }

    public function activities()
    {
        return $this->activitiesAsSubject();
    }

    public function quotable(): MorphTo
    {
        return $this->morphTo();
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function thirdPartySupplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'third_party_supplier_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(QuoteItem::class);
    }

    public function isOpen(): bool
    {
        return ! in_array($this->status, [self::STATUS_REPROVADO, self::STATUS_CONCLUIDO], true);
    }

    /**
     * Soma dos itens -- chamado pelo QuoteItemObserver toda vez que um
     * item e' criado/editado/removido, pra total_value nunca ficar
     * desatualizado (evita ter que recalcular na hora de exibir).
     */
    public function recalculateTotal(): void
    {
        $this->update(['total_value' => $this->items()->sum('subtotal')]);
    }

    /**
     * POP 3: emite o orçamento (precisa ter pelo menos 1 item, senão nao
     * ha' o que aprovar) e gera o token do link publico de aprovação.
     */
    public function send(): void
    {
        if ($this->status !== self::STATUS_RASCUNHO) {
            throw new \RuntimeException('Só é possível enviar um orçamento em rascunho.');
        }

        if ($this->items()->doesntExist()) {
            throw new \RuntimeException('Adicione pelo menos um item (peça ou serviço) antes de enviar.');
        }

        $this->update([
            'status' => self::STATUS_ENVIADO,
            'sent_at' => now(),
            'approval_token' => $this->approval_token ?? Str::random(48),
        ]);
    }

    /**
     * Chamado quando o cliente abre o link publico de aprovação (POP 3:
     * "confirma recebimento") -- so' registra a PRIMEIRA visualização.
     */
    public function markViewedByClient(): void
    {
        if ($this->client_viewed_at) {
            return;
        }

        $this->update(['client_viewed_at' => now()]);
    }

    public function approve(): void
    {
        if ($this->status !== self::STATUS_ENVIADO) {
            throw new \RuntimeException('Só é possível aprovar um orçamento enviado.');
        }

        $this->update([
            'status' => self::STATUS_APROVADO,
            'client_responded_at' => now(),
        ]);
    }

    public function reject(string $reason): void
    {
        if ($this->status !== self::STATUS_ENVIADO) {
            throw new \RuntimeException('Só é possível reprovar um orçamento enviado.');
        }

        $this->update([
            'status' => self::STATUS_REPROVADO,
            'client_responded_at' => now(),
            'rejection_reason' => $reason,
        ]);
    }

    /**
     * POP 4: reúne PDF + e-mails de aprovação e encaminha ao Financeiro.
     * So' um flag/timestamp por enquanto -- ainda não existe ponte real
     * com App\Models\AccountPayable (fila do Financeiro em si e' um item
     * separado da auditoria).
     */
    public function forwardToFinanceiro(): void
    {
        if ($this->status !== self::STATUS_APROVADO) {
            throw new \RuntimeException('Só é possível encaminhar ao Financeiro um orçamento aprovado.');
        }

        $this->update(['financeiro_forwarded_at' => now()]);
    }

    public function complete(): void
    {
        if ($this->status !== self::STATUS_APROVADO) {
            throw new \RuntimeException('Só é possível concluir um orçamento aprovado.');
        }

        $this->update([
            'status' => self::STATUS_CONCLUIDO,
            'completed_at' => now(),
        ]);
    }
}
