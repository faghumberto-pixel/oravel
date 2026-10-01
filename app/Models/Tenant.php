<?php

namespace App\Models;

use App\Models\Concerns\HasSignatures;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Auth;

class Tenant extends Model
{
    // Contrato de Assinatura (pedido do usuário 2026-09-23): assinatura
    // eletrônica obrigatória do plano negociado ANTES do Checkout de
    // pagamento -- mesmo mecanismo já usado em Contract/MaintenanceOrder
    // (DocumentSignature + SignatureService), Tenant como terceiro tipo de
    // documento assinável. Ver AsaasCheckoutController.
    use HasSignatures;
    use HasUuids;

    protected $keyType = 'string';

    public $incrementing = false;

    /**
     * Status de PAGAMENTO da assinatura SaaS, atualizado pelo webhook do
     * Asaas (App\Http\Controllers\AsaasWebhookController) -- separado de
     * asaas_status, que é sobre sincronização de CADASTRO (customer/
     * subscription criados na Asaas), não sobre a cobrança em si.
     */
    public const PAYMENT_STATUS_EM_DIA = 'em_dia';

    public const PAYMENT_STATUS_ATRASADO = 'atrasado';

    public const PAYMENT_STATUS_CANCELADO = 'cancelado';

    protected $fillable = [
        'name',
        'slug',
        'status',
        'address',
        'cep',
        'logradouro',
        'numero',
        'complemento',
        'bairro',
        'cidade',
        'uf',
        'mrr_value',
        'cpf_cnpj',
        'telefone',
        'plan_id',
        'onboarding_completed',
        'features',
        'asaas_customer_id',
        'asaas_subscription_id',
        'asaas_checkout_id',
        'asaas_status',
        'asaas_synced_at',
        'asaas_payment_status',
        'asaas_last_payment_id',
        'asaas_payment_updated_at',
        'asaas_overdue_since',
        'asaas_current_invoice_url',
        'implementation_fee',
        'implementation_installments',
        'implementation_billing_mode',
        'subscription_reverted_to_base_at',
        'razao_social',
        'nome_fantasia',
        'natureza_juridica',
        'inscricao_estadual',
        'email_contato',
        'representante_nome',
        'representante_cpf',
        'representante_cargo',
        'segment',
        'equipment_types',
        'terms_accepted_at',
        'enabled_modules',
        'ui_customizations',
        'targets',
        'signature_id',
        'auto_generate_patrimonio',
    ];

    protected $casts = [
        'id' => 'string',
        'plan_id' => 'string',
        'features' => 'array',
        'equipment_types' => 'array',
        'terms_accepted_at' => 'datetime',
        'onboarding_completed' => 'boolean',
        'auto_generate_patrimonio' => 'boolean',
        'mrr_value' => 'decimal:2',
        'enabled_modules' => 'array',
        'ui_customizations' => 'array',
        'targets' => 'array',
        'asaas_synced_at' => 'datetime',
        'implementation_fee' => 'decimal:2',
        'implementation_installments' => 'integer',
        'subscription_reverted_to_base_at' => 'datetime',
        'asaas_payment_updated_at' => 'datetime',
        'asaas_overdue_since' => 'datetime',
    ];

    /**
     * Acessor virtual (não é coluna real) -- só existe pra satisfazer
     * SignatureService::generateSignatureLink(), que grava
     * DocumentSignature.tenant_id a partir de $signable->tenant_id assumindo
     * que todo model assinável (Contract, MaintenanceOrder) tem essa
     * coluna. Tenant é o próprio tenant, então "o tenant_id do contrato de
     * assinatura de um Tenant" é logicamente o id dele mesmo -- sem isso,
     * o registro nasceria com tenant_id NULL (BelongsToTenant de
     * DocumentSignature só preenche via auth()->user(), que não existe
     * nesse fluxo, autoatendimento sem login) e ficaria invisível nas
     * telas do painel admin que filtram assinatura por tenant.
     */
    public function getTenantIdAttribute(): string
    {
        return $this->id;
    }

    /**
     * Metas padrao dos KPIs de "Gestao a Vista" -- usadas quando o tenant
     * ainda nao configurou (ou nunca configurou) uma meta propria em
     * targets. Mesma semantica "ausente = valor default" de
     * hasModuleEnabled()/isFieldVisible(): nenhum tenant existente
     * regride, todos ja nascem com meta razoavel sem precisar configurar
     * nada.
     */
    private const DEFAULT_TARGETS = [
        'manutencao_realizada' => 90.0,
        'disponibilidade' => 90.0,
        'efetividade' => 85.0,
    ];

    /**
     * Meta configuravel por tenant para um KPI de "Gestao a Vista" (ex:
     * 'disponibilidade'). Retorna o valor default do KPI quando o tenant
     * nao tem targets configurado ou a chave especifica esta ausente.
     */
    public function getTarget(string $key): float
    {
        $targets = $this->targets ?? [];

        if (is_array($targets) && array_key_exists($key, $targets) && is_numeric($targets[$key])) {
            return (float) $targets[$key];
        }

        return self::DEFAULT_TARGETS[$key] ?? 0.0;
    }

    /**
     * Override aditivo: o tenant pode ganhar features alem do plano contratado
     * (ex: cliente pagou o plano Basico mas ganhou um modulo do Premium).
     * Nao da pra bloquear via tenant algo que o plano permite -- so o plano
     * define o que e negado. Ver App\Policies\AbstractPolicy::check().
     */
    /**
     * Removido 2026-09-27 (decisão do usuário): existia um override
     * aditivo aqui, lendo $this->features (coluna própria do Tenant,
     * diferente de Plan::features) pra liberar módulos extras além do
     * Contrato -- editável em TenantResource ("🔐 Recursos Adicionais").
     * Mesmo raciocínio já aplicado a Planos genéricos e a "Módulos por
     * Nicho": uma segunda porta de entrada pra controlar acesso, fora do
     * Contrato, era exatamente o tipo de dívida silenciosa que o usuário
     * pediu pra eliminar (tenant podia acumular exceções que ninguém
     * lembra depois). Confirmado antes de remover: nenhum tenant real
     * tinha dado nessa coluna (null pros dois existentes em PROD).
     */
    public function hasFeature(string $feature): bool
    {
        if (Auth::user()?->isSuperAdmin()) {
            return true;
        }

        return (bool) $this->plan?->hasFeature($feature);
    }

    public function hasModuleAccess(mixed $moduleId, string $moduleSlug): bool
    {
        return $this->hasFeature($moduleSlug);
    }

    /**
     * Bloqueio real por inadimplência (pedido do usuário 2026-09-23):
     * asaas_payment_status virava 'atrasado'/'cancelado' só como
     * informação (dashboard da Central), sem travar nada de fato -- este
     * é o gate de verdade, usado por
     * App\Http\Middleware\EnsureTenantPaymentIsCurrent.
     *
     * Cancelamento (assinatura excluída/reembolsada na Asaas, ou checkout
     * cancelado/expirado) bloqueia IMEDIATAMENTE, sem tolerância -- não
     * tem "prazo" quando não existe mais cobrança nenhuma em aberto.
     * Atraso (fatura pendente) só bloqueia depois de
     * config('oravel.payment_grace_days') dias corridos desde que
     * asaas_overdue_since foi setado (a TRANSIÇÃO pra atrasado, não o
     * último webhook recebido -- ver a migration que criou esse campo).
     *
     * Super admin nunca é bloqueado (mesmo padrão de
     * hasFeature()/hasModuleEnabled() acima); tenant sem
     * asaas_payment_status definido (nunca sincronizado com a Asaas, ex:
     * tenant provisionado manualmente na Central sem cobrança) também não
     * é bloqueado -- ausência de status não é o mesmo que inadimplência.
     */
    public function isAccessBlockedForNonPayment(): bool
    {
        if (Auth::user()?->isSuperAdmin()) {
            return false;
        }

        if ($this->asaas_payment_status === self::PAYMENT_STATUS_CANCELADO) {
            return true;
        }

        if ($this->asaas_payment_status === self::PAYMENT_STATUS_ATRASADO && $this->asaas_overdue_since) {
            $graceDays = (int) config('oravel.payment_grace_days', 5);

            return $this->asaas_overdue_since->addDays($graceDays)->isPast();
        }

        return false;
    }

    /**
     * Visibilidade de campo/preferencia de UI especifica (ex: campo
     * billing_plan_id, view padrao do Kanban) -- mesma semantica de
     * "ausente = true" de hasModuleEnabled(), mas guardada em coluna
     * separada por ser granularidade de campo, nao de modulo inteiro.
     */
    public function isFieldVisible(string $key): bool
    {
        if (Auth::user()?->isSuperAdmin()) {
            return true;
        }

        $custom = $this->ui_customizations ?? [];

        if (is_array($custom) && array_key_exists($key, $custom)) {
            $value = $custom[$key];

            return ! ($value === false || $value === 0 || $value === '0' || $value === 'false');
        }

        return true;
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class, 'plan_id', 'id');
    }

    public function signature(): BelongsTo
    {
        return $this->belongsTo(Signature::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'tenant_user', 'tenant_id', 'user_id');
    }

    public function adminUser(): HasOne
    {
        return $this->hasOne(User::class, 'tenant_id', 'id')->where('role', 'admin');
    }

    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class);
    }

    public function maintenanceOrders(): HasMany
    {
        return $this->hasMany(MaintenanceOrder::class);
    }

    public function materials(): HasMany
    {
        return $this->hasMany(Material::class);
    }

    /**
     * Dados da empresa Contratante exibidos no Contrato de Assinatura
     * (rótulo => valor), só os preenchidos -- usado pelo PDF e pela tela de
     * assinatura pra nunca divergirem.
     *
     * @return array<string, string>
     */
    public function contractPartyDetails(): array
    {
        $address = collect([
            trim(($this->logradouro ?? '').(filled($this->numero) ? ', '.$this->numero : '')),
            $this->complemento,
            $this->bairro,
            collect([$this->cidade, $this->uf])->filter()->implode('/'),
            filled($this->cep) ? 'CEP '.$this->cep : null,
        ])->filter(fn ($part) => filled($part))->implode(' — ');

        return array_filter([
            'Razão social' => $this->razao_social ?: $this->name,
            'Nome fantasia' => $this->nome_fantasia,
            'CNPJ / CPF' => $this->cpf_cnpj,
            'Natureza jurídica' => $this->natureza_juridica,
            'Inscrição estadual' => $this->inscricao_estadual,
            'Endereço' => $address,
            'Telefone' => $this->telefone,
            'E-mail' => $this->email_contato,
            'Representante legal' => collect([$this->representante_nome, $this->representante_cargo])->filter()->implode(', '),
            'CPF do representante' => $this->representante_cpf,
        ], fn ($value) => filled($value));
    }

    /**
     * Valor da taxa de implantação (cobrança única): o valor negociado no
     * próprio tenant, ou o do contrato (Plan) quando não houver override.
     */
    public function implementationAmount(): float
    {
        return (float) ($this->implementation_fee ?? $this->plan?->implementation_fee ?? 0);
    }

    /** Parcelas da implantação (1 ou 2): override do tenant, senão o contrato. */
    public function implementationInstallments(): int
    {
        return max(1, min(2, (int) ($this->implementation_installments ?? $this->plan?->implementation_installments ?? 1)));
    }

    /**
     * Valores das parcelas em centavos exatos: a 1ª leva o arredondamento,
     * a última fecha a conta (soma sempre = total).
     *
     * @return array<int, float>
     */
    public function implementationInstallmentAmounts(): array
    {
        $total = $this->implementationAmount();
        $count = $this->implementationInstallments();
        $base = round($total / $count, 2);
        $amounts = array_fill(0, $count, $base);
        $amounts[$count - 1] = round($total - $base * ($count - 1), 2);

        return $amounts;
    }

    /** 'separada' (cobranças avulsas) ou 'somada' (parcelas somadas às primeiras mensalidades). */
    public function implementationBillingMode(): string
    {
        $mode = $this->implementation_billing_mode ?? $this->plan?->implementation_billing_mode ?? 'separada';

        return $mode === 'somada' ? 'somada' : 'separada';
    }

    /** Há implantação a cobrar E ela vai somada à mensalidade? */
    public function isImplementationSummed(): bool
    {
        return $this->implementationAmount() > 0 && $this->implementationBillingMode() === 'somada';
    }

    /**
     * Valor de cada cobrança da assinatura nos primeiros ciclos no modo somado:
     * mensalidade + parcela da implantação daquele ciclo (índice 0 = 1º ciclo).
     * Depois das parcelas, volta a ser só a mensalidade.
     *
     * @return array<int, float>
     */
    public function summedCycleAmounts(): array
    {
        $mrr = (float) $this->mrr_value;

        return array_map(fn (float $installment) => round($mrr + $installment, 2), $this->implementationInstallmentAmounts());
    }

    /**
     * Situação da implantação do cliente para a Central: rótulo + cor do badge.
     *
     * @return array{label: string, color: string}
     */
    public function implementationSummary(): array
    {
        if ($this->implementationAmount() <= 0) {
            return ['label' => 'Sem implantação', 'color' => 'gray'];
        }

        $charges = $this->implementationCharges()->where('status', '!=', ImplementationCharge::CANCELADO)->get();

        return match (true) {
            $charges->isEmpty() => ['label' => 'Não cobrada', 'color' => 'gray'],
            $charges->every(fn ($c) => $c->status === ImplementationCharge::PAGO) => ['label' => 'Paga', 'color' => 'success'],
            $charges->contains(fn ($c) => $c->status === ImplementationCharge::ATRASADO) => ['label' => 'Atrasada', 'color' => 'danger'],
            $charges->contains(fn ($c) => $c->status === ImplementationCharge::PAGO) => ['label' => 'Parcialmente paga', 'color' => 'warning'],
            default => ['label' => 'Pendente', 'color' => 'warning'],
        };
    }

    public function implementationCharges(): HasMany
    {
        return $this->hasMany(ImplementationCharge::class)->orderBy('installment_number');
    }

    /** Já existe alguma parcela de implantação não cancelada? */
    public function hasActiveImplementationCharge(): bool
    {
        return $this->implementationCharges()->where('status', '!=', ImplementationCharge::CANCELADO)->exists();
    }
}
