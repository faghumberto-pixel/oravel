<?php

namespace App\Models;

use App\Domain\Fleet\Models\RentalOverageCharge;
use App\Models\Concerns\HasSaaSMetadata;
use App\Models\Traits\BelongsToTenant;
use Illuminate\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Notifications\Notifiable;

/**
 * Implementa Authenticatable para o guard 'client' (Portal do Cliente,
 * app/Providers/Filament/ClientPanelProvider) -- guard separado do 'web'
 * usado por User, sem overlap. As queries do portal filtram
 * tenant_id+client_id manualmente (ver ClientPanel Pages), nao dependem do
 * global scope de BelongsToTenant, que resolve Auth::user() no guard
 * default e nao enxerga o guard 'client'.
 */
class Client extends Model implements AuthenticatableContract
{
    use Authenticatable, Notifiable;
    use HasSaaSMetadata;

    protected static ?string $saasFeatureKey = 'tabela_clients';

    protected static ?string $saasPermissionSlug = 'cliente';

    protected static ?string $saasModuleLabel = 'Clientes';

    use BelongsToTenant;

    /**
     * Nicho de locação -- reaproveita 'activity_type' (coluna ja existia na
     * tabela desde 2026-05-03, fillable, mas nunca exposta em nenhum
     * form/tabela ate agora). Um mesmo tenant pode ter clientes de nichos
     * diferentes; terminologia (Prazo Fatal/Quarentena/Emergencia/etc) e
     * fixa pra todo mundo -- o nicho so' influencia sugestao de campos, nao
     * trava nada.
     */
    public const NICHE_EVENTOS = 'eventos';

    public const NICHE_INDUSTRIAL_HOSPITALAR = 'industrial_hospitalar';

    public const NICHE_CONSTRUCAO_CIVIL = 'construcao_civil';

    public const NICHE_LOCACAO_EQUIPAMENTOS = 'locacao_equipamentos';

    public const NICHE_OUTRO = 'outro';

    // Todos os Traits agora estão corretamente declarados DENTRO do corpo da classe
    use HasFactory, HasUuids, SoftDeletes;

    /**
     * Atributos que podem ser preenchidos em massa.
     */
    protected $fillable = [
        'name',
        'activity_type',
        'cpf_cnpj',
        'contact_name',
        'email',
        'cep',
        'address',
        'city',
        'uf',
        'phone',
        'whatsapp',
        'tenant_id',
        'password',
        'portal_access_enabled_at',
        // zip_code/state/neighborhood/address_complement ja existiam na
        // tabela (migration add_erp_fields_to_clients_table) e ja
        // apareciam no form do ClientResource -- mas como nao estavam
        // aqui, o mass-assignment descartava tudo silenciosamente ao
        // salvar (mesmo bug ja documentado em InternalUnit::$fillable).
        // zip_code e' o CEP "oficial" dali pra frente (o form usa esse
        // campo, nao o legado 'cep' acima). latitude/longitude sao
        // geocodificados a partir dele.
        'zip_code',
        'state',
        'neighborhood',
        'address_complement',
        'latitude',
        'longitude',
        // Resto do form (Identificacao PJ, Entrega, Contatos ERP,
        // Representante Legal, Checklist de Documentos, Analise de
        // Risco) -- mesmo bug, todos apareciam na tela e eram
        // descartados no save. check_query_history/check_sinintegra/
        // commercial_references existem como coluna mas NAO aparecem em
        // nenhum campo do form hoje, entao ficam de fora (nada grava
        // neles de qualquer forma).
        'document',
        'fantasy_name',
        'state_registration',
        'municipal_registration',
        'tax_regime',
        'delivery_address',
        'site_manager',
        'site_phone',
        'email_financial',
        'email_purchasing',
        'legal_name',
        'legal_cpf',
        'legal_rg',
        'legal_role',
        'doc_cnpj',
        'doc_statute',
        'doc_id',
        'doc_proxy',
        'doc_address',
        'doc_art',
        'doc_registration_form',
        'check_internal_fraud',
        'check_blacklist',
        'check_credit_bureau',
        'credit_score',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'credit_score' => 'integer',
        'password' => 'hashed',
        'portal_access_enabled_at' => 'datetime',
        'doc_cnpj' => 'boolean',
        'doc_statute' => 'boolean',
        'doc_id' => 'boolean',
        'doc_proxy' => 'boolean',
        'doc_address' => 'boolean',
        'doc_art' => 'boolean',
        'doc_registration_form' => 'boolean',
        'check_internal_fraud' => 'boolean',
        'check_blacklist' => 'boolean',
        'check_credit_bureau' => 'boolean',
    ];

    /**
     * RELAÇÃO COM O TENANT
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    /**
     * Relacionamento: Um cliente pode ter várias Ordens de Serviço (OS).
     */
    public function maintenanceOrders(): HasMany
    {
        return $this->hasMany(MaintenanceOrder::class, 'client_id');
    }

    /**
     * Relacionamento: Um cliente pode ter vários Contratos de locação.
     */
    public function contracts(): HasMany
    {
        return $this->hasMany(Contract::class, 'client_id');
    }

    /**
     * Espelha Asset::getFinancialSummary() no nível do Cliente: soma receita
     * de todos os contratos dele + excedente de franquia aprovado + avaria
     * cobrada dos ativos que ele usou (via Contract.asset_id), menos o custo
     * de manutenção das O.S. abertas diretamente para este cliente
     * (MaintenanceOrder.client_id) -- pedido do usuário 2026-08-24.
     */
    public function getFinancialSummary(): array
    {
        $assetIds = $this->contracts()->pluck('asset_id')->filter()->unique()->values();

        $totalRentalRevenue = (float) $this->contracts()->sum('price');

        $totalOverageRevenue = $assetIds->isEmpty() ? 0.0 : (float) RentalOverageCharge::query()
            ->whereIn('asset_id', $assetIds)
            ->where('status', RentalOverageCharge::STATUS_INVOICED)
            ->sum('amount');

        $totalDamageRevenue = $assetIds->isEmpty() ? 0.0 : (float) Quote::query()
            ->whereHasMorph('quotable', [EquipmentDamage::class], function ($query) use ($assetIds) {
                $query->whereIn('asset_id', $assetIds)
                    ->whereIn('cause', [EquipmentDamage::CAUSE_MAU_USO, EquipmentDamage::CAUSE_DANO_CLIENTE]);
            })
            ->whereIn('status', [Quote::STATUS_APROVADO, Quote::STATUS_CONCLUIDO])
            ->sum('total_value');

        $totalMaintenanceCost = (float) $this->maintenanceOrders()->sum('total_order_cost');

        $totalRevenue = $totalRentalRevenue + $totalOverageRevenue + $totalDamageRevenue;
        $result = $totalRevenue - $totalMaintenanceCost;

        return [
            'total_rental_revenue' => round($totalRentalRevenue, 2),
            'total_overage_revenue' => round($totalOverageRevenue, 2),
            'total_damage_revenue' => round($totalDamageRevenue, 2),
            'total_revenue' => round($totalRevenue, 2),
            'total_maintenance_cost' => round($totalMaintenanceCost, 2),
            'result' => round($result, 2),
        ];
    }

    /**
     * Accessor para formatar endereço completo.
     */
    public function getFullLocationAttribute(): string
    {
        return "{$this->city} - {$this->uf}";
    }

    /**
     * Escopo para busca rápida por documento
     */
    public function scopeByDocument($query, $document)
    {
        return $query->where('cpf_cnpj', $document);
    }

    /**
     * @return array<string, string>
     */
    public static function nicheLabels(): array
    {
        return [
            self::NICHE_EVENTOS => 'Eventos',
            self::NICHE_INDUSTRIAL_HOSPITALAR => 'Industrial / Hospitalar',
            self::NICHE_CONSTRUCAO_CIVIL => 'Construção Civil',
            self::NICHE_LOCACAO_EQUIPAMENTOS => 'Locação de Equipamentos',
            self::NICHE_OUTRO => 'Outro',
        ];
    }
}
