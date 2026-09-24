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
        'segment',
        'equipment_types',
        'terms_accepted_at',
        'enabled_modules',
        'ui_customizations',
        'targets',
        'signature_id',
    ];

    protected $casts = [
        'id' => 'string',
        'plan_id' => 'string',
        'features' => 'array',
        'equipment_types' => 'array',
        'terms_accepted_at' => 'datetime',
        'onboarding_completed' => 'boolean',
        'mrr_value' => 'decimal:2',
        'enabled_modules' => 'array',
        'ui_customizations' => 'array',
        'targets' => 'array',
        'asaas_synced_at' => 'datetime',
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
    public function hasFeature(string $feature): bool
    {
        if (Auth::user()?->isSuperAdmin()) {
            return true;
        }

        $localFeatures = $this->features ?? [];

        if (is_array($localFeatures)) {
            if (array_key_exists($feature, $localFeatures)) {
                $value = $localFeatures[$feature];
                if ($value === true || $value === 1 || $value === '1' || $value === 'true') {
                    return true;
                }
            } elseif (in_array($feature, $localFeatures, true)) {
                return true;
            }
        }

        return (bool) $this->plan?->hasFeature($feature);
    }

    public function hasModuleAccess(mixed $moduleId, string $moduleSlug): bool
    {
        return $this->hasFeature($moduleSlug);
    }

    /**
     * enabled_modules e' SUBTRATIVO (ao contrario de features/hasFeature(),
     * que e' aditivo) -- o tenant desliga ruido de nicho que o proprio plano
     * ja libera (Prazo Fatal, Quarentena, SLA/Emergencia etc). Chave ausente
     * = true: todo tenant existente ja tinha esses comportamentos ligados
     * incondicionalmente antes desta coluna existir, entao ausencia nao pode
     * regredir ninguem -- so' false explicito desliga.
     */
    public function hasModuleEnabled(string $key): bool
    {
        if (Auth::user()?->isSuperAdmin()) {
            return true;
        }

        $modules = $this->enabled_modules ?? [];

        if (is_array($modules) && array_key_exists($key, $modules)) {
            $value = $modules[$key];

            return ! ($value === false || $value === 0 || $value === '0' || $value === 'false');
        }

        return true;
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

    /**
     * Predefinicao de enabled_modules por segmento -- usada SO pela tela de
     * configuracoes ao trocar o segmento (acao explicita, com confirmacao).
     * Funcao pura: nao grava nada sozinha, devolve o array pro caller
     * decidir/persistir. Reaproveita as constantes de nicho ja existentes em
     * Client (terminologia fixa, sem vocabulario paralelo).
     *
     * @return array<string, bool>
     */
    public static function applySegmentPreset(string $segment): array
    {
        return match ($segment) {
            Client::NICHE_EVENTOS => [
                'prazo_fatal' => true,
                'banco_de_carga' => true,
                'quarentena' => true,
                'sla_emergencia' => false,
                'kanban_oficina_extra' => false,
                'mau_uso' => false,
                'horimetro_rigido' => false,
                'contas_a_receber' => true,
            ],
            Client::NICHE_INDUSTRIAL_HOSPITALAR => [
                'sla_emergencia' => true,
                'quarentena' => true,
                'horimetro_rigido' => true,
                'prazo_fatal' => false,
                'banco_de_carga' => false,
                'kanban_oficina_extra' => false,
                'mau_uso' => false,
                'contas_a_receber' => true,
            ],
            Client::NICHE_CONSTRUCAO_CIVIL => [
                'kanban_oficina_extra' => true,
                'mau_uso' => true,
                'horimetro_rigido' => true,
                'quarentena' => true,
                'prazo_fatal' => false,
                'banco_de_carga' => false,
                'sla_emergencia' => false,
                'contas_a_receber' => true,
            ],
            Client::NICHE_LOCACAO_EQUIPAMENTOS => [
                'mau_uso' => true,
                'horimetro_rigido' => true,
                'quarentena' => true,
                'kanban_oficina_extra' => true,
                'prazo_fatal' => false,
                'banco_de_carga' => false,
                'sla_emergencia' => false,
                'contas_a_receber' => true,
            ],
            default => [
                'prazo_fatal' => true,
                'sla_emergencia' => true,
                'banco_de_carga' => true,
                'quarentena' => true,
                'kanban_oficina_extra' => true,
                'mau_uso' => true,
                'horimetro_rigido' => true,
                'contas_a_receber' => true,
            ],
        };
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
}
