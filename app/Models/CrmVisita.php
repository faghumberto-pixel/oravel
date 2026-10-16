<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasSaaSMetadata;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/** Visita de campo de um vendedor a um lead ou conta, com check-in/out, km e resultado. */
class CrmVisita extends Model
{
    use BelongsToTenant;
    use HasSaaSMetadata;
    use HasUuids;

    public const STATUS_AGENDADA = 'agendada';

    public const STATUS_EM_ANDAMENTO = 'em_andamento';

    public const STATUS_REALIZADA = 'realizada';

    public const STATUS_CANCELADA = 'cancelada';

    public const STATUS_NAO_ENCONTROU = 'nao_encontrou';

    protected $table = 'crm_visitas';

    protected static ?string $saasFeatureKey = 'tabela_crm_visitas';

    protected static ?string $saasPermissionSlug = 'crm_visita';

    protected static ?string $saasModuleLabel = 'CRM - Visitas';

    /** Padrões também no objeto recém-criado (sem precisar recarregar do banco). */
    protected $attributes = ['status' => self::STATUS_AGENDADA, 'objetivo' => 'apresentacao', 'duracao_prevista_min' => 60];

    protected $fillable = [
        'tenant_id', 'user_id', 'crm_lead_id', 'crm_conta_id', 'contato_nome', 'objetivo', 'agendada_para', 'duracao_prevista_min', 'status',
        'endereco', 'cidade', 'uf', 'latitude', 'longitude', 'crm_veiculo_id', 'km_inicio', 'km_fim',
        'checkin_em', 'checkin_lat', 'checkin_lng', 'checkin_distancia_m', 'checkout_em', 'checkout_lat', 'checkout_lng',
        'resultado', 'proximo_passo', 'proximo_contato', 'ordem_roteiro', 'observacoes',
    ];

    protected $casts = [
        'agendada_para' => 'datetime', 'checkin_em' => 'datetime', 'checkout_em' => 'datetime', 'proximo_contato' => 'date',
        'latitude' => 'decimal:8', 'longitude' => 'decimal:8', 'checkin_lat' => 'decimal:8', 'checkin_lng' => 'decimal:8',
        'checkout_lat' => 'decimal:8', 'checkout_lng' => 'decimal:8',
        'km_inicio' => 'integer', 'km_fim' => 'integer', 'checkin_distancia_m' => 'integer', 'ordem_roteiro' => 'integer',
    ];

    protected static function booted(): void
    {
        // Endereço e coordenadas herdados do lead/conta quando não informados.
        static::saving(function (CrmVisita $v) {
            if (filled($v->endereco) || $v->latitude !== null) {
                return;
            }

            $origem = $v->crm_conta_id ? CrmConta::withoutGlobalScopes()->find($v->crm_conta_id) : null;

            if ($origem) {
                $v->fill(['endereco' => $origem->endereco, 'cidade' => $origem->cidade, 'uf' => $origem->uf, 'latitude' => $origem->latitude, 'longitude' => $origem->longitude]);

                return;
            }

            $lead = $v->crm_lead_id ? CrmLead::withoutGlobalScopes()->find($v->crm_lead_id) : null;

            if ($lead) {
                $v->fill(['endereco' => $lead->address, 'cidade' => $lead->city, 'uf' => $lead->uf, 'latitude' => $lead->latitude, 'longitude' => $lead->longitude]);
            }
        });
    }

    public static function statusLabels(): array
    {
        return [
            self::STATUS_AGENDADA => 'Agendada', self::STATUS_EM_ANDAMENTO => 'Em andamento', self::STATUS_REALIZADA => 'Realizada',
            self::STATUS_CANCELADA => 'Cancelada', self::STATUS_NAO_ENCONTROU => 'Não encontrou ninguém',
        ];
    }

    public static function objetivoLabels(): array
    {
        return [
            'prospeccao' => 'Prospecção', 'apresentacao' => 'Apresentação', 'negociacao' => 'Negociação', 'entrega' => 'Entrega',
            'pos_venda' => 'Pós-venda', 'cobranca' => 'Cobrança', 'outro' => 'Outro',
        ];
    }

    public function vendedor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(CrmLead::class, 'crm_lead_id');
    }

    public function conta(): BelongsTo
    {
        return $this->belongsTo(CrmConta::class, 'crm_conta_id');
    }

    public function veiculo(): BelongsTo
    {
        return $this->belongsTo(CrmVeiculo::class, 'crm_veiculo_id');
    }

    public function despesas(): HasMany
    {
        return $this->hasMany(CrmVisitaDespesa::class, 'crm_visita_id');
    }

    public function scopeDoDia(Builder $query, $data): Builder
    {
        return $query->whereDate('agendada_para', Carbon::parse($data)->toDateString());
    }

    public function kmRodados(): ?int
    {
        return ($this->km_inicio !== null && $this->km_fim !== null) ? max(0, $this->km_fim - $this->km_inicio) : null;
    }

    public function duracaoMin(): ?int
    {
        return ($this->checkin_em && $this->checkout_em) ? (int) $this->checkin_em->diffInMinutes($this->checkout_em) : null;
    }

    /** Nome para listas: conta ou lead ligado, ou o contato avulso. */
    public function alvoNome(): string
    {
        return $this->conta?->nome ?? $this->lead?->company_name ?? $this->lead?->name ?? $this->contato_nome ?? 'Visita avulsa';
    }

    public function temCoordenadas(): bool
    {
        return $this->latitude !== null && $this->longitude !== null;
    }
}
