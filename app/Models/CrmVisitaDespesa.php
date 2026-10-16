<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasSaaSMetadata;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/** Despesa de campo (combustível, pedágio…) de um vendedor, ligada ou não a uma visita, com comprovante privado e aprovação. */
class CrmVisitaDespesa extends Model implements HasMedia
{
    use BelongsToTenant;
    use HasSaaSMetadata;
    use HasUuids;
    use InteractsWithMedia;

    public const STATUS_PENDENTE = 'pendente';

    public const STATUS_APROVADA = 'aprovada';

    public const STATUS_REJEITADA = 'rejeitada';

    public const STATUS_PAGA = 'paga';

    protected $table = 'crm_visita_despesas';

    protected static ?string $saasFeatureKey = 'tabela_crm_despesas';

    protected static ?string $saasPermissionSlug = 'crm_despesa';

    protected static ?string $saasModuleLabel = 'CRM - Despesas de Visita';

    /** Padrões também no objeto recém-criado. */
    protected $attributes = ['status' => self::STATUS_PENDENTE, 'tipo' => 'combustivel'];

    protected $fillable = [
        'tenant_id', 'crm_visita_id', 'user_id', 'crm_veiculo_id', 'data', 'tipo', 'valor', 'descricao', 'litros',
        'status', 'aprovado_por_user_id', 'aprovado_em',
    ];

    protected $casts = ['data' => 'date', 'valor' => 'decimal:2', 'litros' => 'decimal:2', 'aprovado_em' => 'datetime'];

    public static function tipoLabels(): array
    {
        return ['combustivel' => 'Combustível', 'pedagio' => 'Pedágio', 'estacionamento' => 'Estacionamento', 'alimentacao' => 'Alimentação', 'hospedagem' => 'Hospedagem', 'outro' => 'Outro'];
    }

    public static function statusLabels(): array
    {
        return [self::STATUS_PENDENTE => 'Pendente', self::STATUS_APROVADA => 'Aprovada', self::STATUS_REJEITADA => 'Rejeitada', self::STATUS_PAGA => 'Paga'];
    }

    /** Comprovante em disco privado: só abre por link assinado temporário. */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('comprovante')
            ->useDisk('media_private')
            ->singleFile()
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp', 'application/pdf']);
    }

    public function vendedor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function visita(): BelongsTo
    {
        return $this->belongsTo(CrmVisita::class, 'crm_visita_id');
    }

    public function veiculo(): BelongsTo
    {
        return $this->belongsTo(CrmVeiculo::class, 'crm_veiculo_id');
    }

    /** Admin decide: aprovar, rejeitar ou marcar como paga. */
    public function decidir(string $status, User $por): void
    {
        $this->update(['status' => $status, 'aprovado_por_user_id' => $por->id, 'aprovado_em' => now()]);
    }
}
