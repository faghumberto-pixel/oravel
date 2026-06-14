<?php

namespace App\Models;

use App\Models\Concerns\HasSaaSMetadata;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SolicitacaoLocacao extends Model
{
    use \App\Models\Concerns\BelongsToTenant;
    use HasSaaSMetadata;

    protected static ?string $saasFeatureKey = "tabela_solicitacao_locacao";
    protected static ?string $saasPermissionSlug = "solicitacao_locacao";
    protected static ?string $saasModuleLabel = "Solicitacoes de Locacao";

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
}
