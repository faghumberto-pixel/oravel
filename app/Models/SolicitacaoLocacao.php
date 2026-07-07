<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasSaaSMetadata;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class SolicitacaoLocacao extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use HasSaaSMetadata;

    protected static ?string $saasFeatureKey = 'tabela_solicitacao_locacao';

    protected static ?string $saasPermissionSlug = 'solicitacao_locacao';

    protected static ?string $saasModuleLabel = 'Solicitacoes de Locacao';

    use HasUuids;

    protected $table = 'solicitacoes_locacao';

    /**
     * Campos permitidos para atribuição em massa.
     */
    protected $fillable = [
        'tenant_id',
        'user_id',
        'customer_id',
        'contract_id',
        'category_id',
        'asset_id',
        'purpose',
        'data_saida_prevista',
        'status_comercial',
        'cancellation_reason_id', // Novo campo para rastreabilidade de perdas
        'observations',
    ];

    /**
     * Define a conversão de tipos para campos específicos.
     */
    protected function casts(): array
    {
        return [
            'data_saida_prevista' => 'date',
        ];
    }

    // --- RELACIONAMENTOS ---

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(AssetCategory::class, 'category_id');
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'asset_id');
    }

    public function customer(): BelongsTo
    {
        // Mantido 'customer' como nome do método para compatibilidade com o Resource,
        // mas apontando corretamente para o modelo Client.
        return $this->belongsTo(Client::class, 'customer_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class, 'contract_id');
    }

    /**
     * Relacionamento para análise estatística de motivos de cancelamento.
     */
    public function cancellationReason(): BelongsTo
    {
        return $this->belongsTo(CancellationReason::class, 'cancellation_reason_id');
    }

    /**
     * Ativos do "combo" desta solicitacao -- aditivo ao asset_id legado
     * (que continua servindo pra solicitacao de um unico equipamento
     * especifico). Uma solicitacao "combo"/lote usa so este relacionamento.
     */
    public function assets(): BelongsToMany
    {
        return $this->belongsToMany(Asset::class, 'solicitacao_locacao_assets');
    }

    /**
     * Todo o combo esta pronto pra embarque simultaneo? (todos os ativos
     * vinculados estao com status Disponivel agora).
     */
    public function isKitComplete(): bool
    {
        $assets = $this->assets;

        if ($assets->isEmpty()) {
            return false;
        }

        return $assets->every(fn (Asset $asset) => $asset->status === Asset::STATUS_DISPONIVEL);
    }
}
