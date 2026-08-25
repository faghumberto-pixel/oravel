<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasSaaSMetadata;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Proposta comercial (equipamento e/ou serviço), criada pelo vendedor de
 * campo (wizard mobile, App\Livewire\PropostaComercialMobile) e revisada
 * pelo time Comercial -- distinta de Quote (orçamento de peça/avaria,
 * aprovado pelo CLIENTE final, vira conta a receber). Aqui quem aprova é
 * interno (role "Comercial", mesma já usada em EquipmentDamage), e o
 * resultado da aprovação é "acionar" o equipamento/serviço, criando uma
 * SolicitacaoLocacao real -- o fluxo comercial tradicional (escolha fina
 * de Ativo, fechamento de contrato) continua dali em diante, como já
 * funciona hoje.
 */
class PropostaComercial extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use HasSaaSMetadata;
    use HasUuids;
    use LogsActivity;

    protected $table = 'proposta_comerciais';

    public const STATUS_RASCUNHO = 'rascunho';

    public const STATUS_ENVIADA_PARA_COMERCIAL = 'enviada_para_comercial';

    public const STATUS_APROVADA = 'aprovada';

    public const STATUS_REJEITADA = 'rejeitada';

    protected static ?string $saasFeatureKey = 'tabela_proposta_comercial';

    protected static ?string $saasPermissionSlug = 'proposta_comercial';

    protected static ?string $saasModuleLabel = 'Propostas Comerciais';

    /**
     * Default também em PHP (não só na migration) -- mesma armadilha já
     * documentada em Quote/SalesLead: sem isso, status/total_value ficam
     * null no objeto em memória logo após create() até um refresh().
     */
    protected $attributes = [
        'status' => self::STATUS_RASCUNHO,
        'total_value' => 0,
    ];

    protected $fillable = [
        'tenant_id',
        'crm_lead_id',
        'client_id',
        'seller_user_id',
        'reviewed_by_user_id',
        'status',
        'valid_until',
        'terms',
        'rejection_reason',
        'total_value',
        'sent_at',
        'reviewed_at',
        'solicitacao_locacao_id',
    ];

    protected $casts = [
        'valid_until' => 'date',
        'total_value' => 'decimal:2',
        'sent_at' => 'datetime',
        'reviewed_at' => 'datetime',
    ];

    /**
     * @return array<string, string>
     */
    public static function statusLabels(): array
    {
        return [
            self::STATUS_RASCUNHO => 'Rascunho',
            self::STATUS_ENVIADA_PARA_COMERCIAL => 'Enviada para o Comercial',
            self::STATUS_APROVADA => 'Aprovada',
            self::STATUS_REJEITADA => 'Rejeitada',
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

    public function crmLead(): BelongsTo
    {
        return $this->belongsTo(CrmLead::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function sellerUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_user_id');
    }

    public function reviewedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }

    public function solicitacaoLocacao(): BelongsTo
    {
        return $this->belongsTo(SolicitacaoLocacao::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PropostaComercialItem::class);
    }

    /**
     * Soma dos itens -- chamado pelo PropostaComercialItemObserver toda vez
     * que um item é criado/editado/removido, mesmo padrão de
     * Quote::recalculateTotal().
     */
    public function recalculateTotal(): void
    {
        $this->update(['total_value' => $this->items()->sum('subtotal')]);
    }

    /**
     * Copia o texto padrão do template escolhido (ou do is_default) pro
     * campo terms da proposta -- CÓPIA, não referência: editar o template
     * depois não altera esta proposta.
     */
    public function fillFromTemplate(?PropostaComercialTemplate $template = null): void
    {
        $template ??= PropostaComercialTemplate::where('tenant_id', $this->tenant_id)
            ->where('is_default', true)
            ->where('is_active', true)
            ->first();

        if (! $template) {
            return;
        }

        $this->terms = $template->default_terms;

        if ($template->default_valid_days && ! $this->valid_until) {
            $this->valid_until = now()->addDays($template->default_valid_days);
        }
    }

    /**
     * Vendedor confirma e envia pro Comercial revisar -- precisa ter
     * cliente definido (aqui sim vira obrigatório, diferente do create) e
     * pelo menos 1 item, senão não há o que o Comercial avaliar.
     */
    public function enviarParaComercial(): void
    {
        if ($this->status !== self::STATUS_RASCUNHO) {
            throw new \RuntimeException('Só é possível enviar uma proposta em rascunho.');
        }

        if (! $this->client_id) {
            throw new \RuntimeException('Defina o cliente antes de enviar a proposta.');
        }

        if ($this->items()->doesntExist()) {
            throw new \RuntimeException('Adicione pelo menos um item (equipamento ou serviço) antes de enviar.');
        }

        $this->update([
            'status' => self::STATUS_ENVIADA_PARA_COMERCIAL,
            'sent_at' => now(),
        ]);
    }

    /**
     * Comercial aprova: aciona o equipamento/serviço criando uma
     * SolicitacaoLocacao real, exceto quando a proposta é 100%-serviço
     * (sem nenhum item "equipamento") -- nesse caso aprova normalmente,
     * mas o acionamento fica bloqueado com aviso claro (resolvido de
     * verdade só na Fase 2, ver plano). category_id de SolicitacaoLocacao
     * é NOT NULL no banco, então não dá pra criar sem pelo menos 1 item de
     * equipamento definindo a categoria.
     */
    public function aprovar(User $revisor): void
    {
        if ($this->status !== self::STATUS_ENVIADA_PARA_COMERCIAL) {
            throw new \RuntimeException('Só é possível aprovar uma proposta enviada ao Comercial.');
        }

        $this->update([
            'status' => self::STATUS_APROVADA,
            'reviewed_by_user_id' => $revisor->id,
            'reviewed_at' => now(),
        ]);

        $primeiroEquipamento = $this->items()->where('type', PropostaComercialItem::TYPE_EQUIPAMENTO)->first();

        if (! $primeiroEquipamento) {
            return;
        }

        $solicitacao = $this->criarSolicitacaoLocacao($primeiroEquipamento);

        $this->update(['solicitacao_locacao_id' => $solicitacao->id]);
    }

    public function rejeitar(User $revisor, string $motivo): void
    {
        if ($this->status !== self::STATUS_ENVIADA_PARA_COMERCIAL) {
            throw new \RuntimeException('Só é possível rejeitar uma proposta enviada ao Comercial.');
        }

        $this->update([
            'status' => self::STATUS_REJEITADA,
            'rejection_reason' => $motivo,
            'reviewed_by_user_id' => $revisor->id,
            'reviewed_at' => now(),
        ]);
    }

    /**
     * Permite o vendedor corrigir e reenviar sem duplicar a proposta
     * inteira -- zera os campos de revisão anterior.
     */
    public function reabrirParaEdicao(): void
    {
        if ($this->status !== self::STATUS_REJEITADA) {
            throw new \RuntimeException('Só é possível reabrir uma proposta rejeitada.');
        }

        $this->update([
            'status' => self::STATUS_RASCUNHO,
            'rejection_reason' => null,
            'reviewed_by_user_id' => null,
            'reviewed_at' => null,
        ]);
    }

    private function criarSolicitacaoLocacao(PropostaComercialItem $primeiroEquipamento): SolicitacaoLocacao
    {
        $dataSaidaPrevista = $this->items()
            ->whereNotNull('start_date')
            ->orderBy('start_date')
            ->value('start_date') ?? $this->valid_until ?? now()->addDays(7);

        $resumoItens = $this->items->map(function (PropostaComercialItem $item) {
            $tipo = PropostaComercialItem::typeLabels()[$item->type] ?? $item->type;

            return "{$tipo}: {$item->description} (qtd {$item->quantity})";
        })->implode(' | ');

        return SolicitacaoLocacao::create([
            'tenant_id' => $this->tenant_id,
            'user_id' => $this->seller_user_id,
            'customer_id' => $this->client_id,
            'category_id' => $primeiroEquipamento->asset_category_id,
            'purpose' => "Proposta Comercial #{$this->id}: {$resumoItens}",
            'data_saida_prevista' => $dataSaidaPrevista,
            'status_comercial' => 'proposta_em_andamento',
            'observations' => $this->terms,
        ]);
    }
}
