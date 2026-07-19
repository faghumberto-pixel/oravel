<?php

namespace App\Models;

use App\Services\TenantProvisioner;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * CRM comercial da propria Oravel (prospeccao de novos tenants), painel
 * Central. NAO confundir com CrmLead (esse sim por tenant, pra cada
 * locadora vender pros clientes dela) -- vocabulario e estrutura parecidos
 * de proposito (mesmo padrao ja provado), mas sao dominios diferentes.
 */
class SalesLead extends Model
{
    use HasFactory;
    use HasUuids;

    public const STAGE_PROSPECCAO = 'prospeccao';

    public const STAGE_CONTATO_QUALIFICADO = 'contato_qualificado';

    public const STAGE_DEMONSTRACAO_REALIZADA = 'demonstracao_realizada';

    public const STAGE_PROPOSTA_ENVIADA = 'proposta_enviada';

    public const STAGE_GANHO = 'ganho';

    public const STAGE_PERDIDO = 'perdido';

    public const SOURCE_INDICACAO = 'indicacao';

    public const SOURCE_SITE = 'site';

    public const SOURCE_PROSPECCAO_ATIVA = 'prospeccao_ativa';

    public const SOURCE_EVENTO = 'evento';

    public const SOURCE_OUTRO = 'outro';

    public const LOST_REASON_PRECO = 'preco';

    public const LOST_REASON_CONCORRENCIA = 'concorrencia';

    public const LOST_REASON_SEM_NECESSIDADE = 'sem_necessidade';

    public const LOST_REASON_SEM_ORCAMENTO = 'sem_orcamento';

    public const LOST_REASON_PRODUTO_NAO_APLICA = 'produto_nao_aplica';

    public const LOST_REASON_DESISTENCIA = 'desistencia';

    public const LOST_REASON_OUTRO = 'outro';

    protected $fillable = [
        'company_name',
        'website',
        'decision_maker_name',
        'decision_maker_role',
        'phone',
        'email',
        'source',
        'segment',
        'estimated_contract_value',
        'critical_pain',
        'oravel_solution',
        'pipeline_stage',
        'lost_reason',
        'lost_reason_detail',
        'assigned_user_id',
        'converted_tenant_id',
        'address',
        'city',
        'uf',
        'cep',
        'latitude',
        'longitude',
        'last_interaction_at',
        'next_followup_date',
    ];

    protected $casts = [
        'estimated_contract_value' => 'decimal:2',
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'last_interaction_at' => 'datetime',
        'next_followup_date' => 'date',
    ];

    public static function stageLabels(): array
    {
        return [
            self::STAGE_PROSPECCAO => 'Prospecção',
            self::STAGE_CONTATO_QUALIFICADO => 'Contato Qualificado',
            self::STAGE_DEMONSTRACAO_REALIZADA => 'Demonstração Realizada',
            self::STAGE_PROPOSTA_ENVIADA => 'Proposta Enviada',
            self::STAGE_GANHO => 'Ganho',
            self::STAGE_PERDIDO => 'Perdido',
        ];
    }

    public static function sourceLabels(): array
    {
        return [
            self::SOURCE_INDICACAO => 'Indicação',
            self::SOURCE_SITE => 'Site',
            self::SOURCE_PROSPECCAO_ATIVA => 'Prospecção Ativa',
            self::SOURCE_EVENTO => 'Evento',
            self::SOURCE_OUTRO => 'Outro',
        ];
    }

    public static function lostReasonLabels(): array
    {
        return [
            self::LOST_REASON_PRECO => 'Preço',
            self::LOST_REASON_CONCORRENCIA => 'Concorrência',
            self::LOST_REASON_SEM_NECESSIDADE => 'Sem Necessidade',
            self::LOST_REASON_SEM_ORCAMENTO => 'Sem Orçamento',
            self::LOST_REASON_PRODUTO_NAO_APLICA => 'Produto Não Se Aplica',
            self::LOST_REASON_DESISTENCIA => 'Desistência',
            self::LOST_REASON_OUTRO => 'Outro',
        ];
    }

    /**
     * Pesos usados na projecao de receita ponderada do funil -- soma bruta
     * ilusiona o numero, a ponderacao reflete a chance real de fechar
     * dependendo de quao avancado o lead esta.
     */
    public static function stageProbabilityWeights(): array
    {
        return [
            self::STAGE_PROSPECCAO => 0.10,
            self::STAGE_CONTATO_QUALIFICADO => 0.25,
            self::STAGE_DEMONSTRACAO_REALIZADA => 0.50,
            self::STAGE_PROPOSTA_ENVIADA => 0.75,
            self::STAGE_GANHO => 1.0,
        ];
    }

    public function isOpen(): bool
    {
        return ! in_array($this->pipeline_stage, [self::STAGE_GANHO, self::STAGE_PERDIDO], true);
    }

    public function interactions(): HasMany
    {
        return $this->hasMany(SalesLeadInteraction::class)->latest('contact_date');
    }

    public function refreshInteractionCache(): void
    {
        $latest = $this->interactions()->first();

        $this->forceFill([
            'last_interaction_at' => $latest?->contact_date,
        ])->saveQuietly();
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(SalesLeadAppointment::class);
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function convertedTenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'converted_tenant_id');
    }

    /**
     * Cada estagio so' avanca com um gatilho operacional real, nao um
     * drag-and-drop intuitivo -- pedido explicito do usuario. Retorna
     * null quando pode avancar, ou a mensagem explicando o que falta.
     */
    public function blockerForNextStage(): ?string
    {
        return match ($this->pipeline_stage) {
            self::STAGE_PROSPECCAO => $this->interactions()->exists()
                ? null
                : 'Registre pelo menos uma interação antes de avançar.',

            self::STAGE_CONTATO_QUALIFICADO => match (true) {
                blank($this->critical_pain) => 'Preencha a dor crítica mapeada antes de avançar.',
                ! $this->appointments()->where('type', 'demonstracao')->exists() => 'Agende uma demonstração antes de avançar.',
                default => null,
            },

            self::STAGE_DEMONSTRACAO_REALIZADA => $this->appointments()
                ->where('type', 'demonstracao')
                ->where('status', SalesLeadAppointment::STATUS_CONCLUIDO)
                ->exists()
                ? null
                : 'Marque a demonstração como concluída antes de avançar.',

            self::STAGE_PROPOSTA_ENVIADA => filled($this->estimated_contract_value) && $this->estimated_contract_value > 0
                ? null
                : 'Preencha o valor estimado do contrato antes de avançar.',

            default => null,
        };
    }

    public function nextStage(): ?string
    {
        return match ($this->pipeline_stage) {
            self::STAGE_PROSPECCAO => self::STAGE_CONTATO_QUALIFICADO,
            self::STAGE_CONTATO_QUALIFICADO => self::STAGE_DEMONSTRACAO_REALIZADA,
            self::STAGE_DEMONSTRACAO_REALIZADA => self::STAGE_PROPOSTA_ENVIADA,
            self::STAGE_PROPOSTA_ENVIADA => self::STAGE_GANHO,
            default => null,
        };
    }

    public function advanceStage(): void
    {
        $blocker = $this->blockerForNextStage();

        if ($blocker) {
            throw new \RuntimeException($blocker);
        }

        $next = $this->nextStage();

        if (! $next) {
            return;
        }

        if ($next === self::STAGE_GANHO) {
            throw new \RuntimeException('Use convertToTenant() pra fechar como ganho -- precisa dos dados do plano e do admin.');
        }

        $this->update(['pipeline_stage' => $next]);
    }

    public function markLost(string $reason, ?string $detail = null): void
    {
        $this->update([
            'pipeline_stage' => self::STAGE_PERDIDO,
            'lost_reason' => $reason,
            'lost_reason_detail' => $detail,
        ]);
    }

    /**
     * Fecha o lead como ganho: cria o Tenant de verdade (com o segmento ja
     * certo, vindo do lead) e reaproveita TenantProvisioner -- mesmo
     * caminho usado pela criacao manual de tenant em TenantResource.
     *
     * @param  array{name: string, email: string, password: string}  $adminData
     */
    public function convertToTenant(string $planId, array $adminData): Tenant
    {
        $blocker = $this->blockerForNextStage();

        if ($this->pipeline_stage !== self::STAGE_PROPOSTA_ENVIADA || $blocker) {
            throw new \RuntimeException($blocker ?? 'O lead precisa estar em "Proposta Enviada" com valor preenchido antes de converter.');
        }

        $tenant = Tenant::create([
            'name' => $this->company_name,
            'slug' => Str::slug($this->company_name).'-'.Str::lower(Str::random(6)),
            'plan_id' => $planId,
            'status' => 'active',
            'segment' => $this->segment,
        ]);

        TenantProvisioner::provision($tenant, $adminData);

        $this->update([
            'pipeline_stage' => self::STAGE_GANHO,
            'converted_tenant_id' => $tenant->id,
        ]);

        return $tenant;
    }
}
