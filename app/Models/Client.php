<?php

namespace App\Models;

use App\Models\Concerns\HasSaaSMetadata;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Client extends Model
{
    use HasSaaSMetadata;

    protected static ?string $saasFeatureKey = "tabela_clients";
    protected static ?string $saasPermissionSlug = "cliente";
    protected static ?string $saasModuleLabel = "Clientes";

    // Todos os Traits agora estão corretamente declarados DENTRO do corpo da classe
    use HasFactory, HasUuids, SoftDeletes;
    use \App\Models\Traits\BelongsToTenant;

    /**
     * Atributos que podem ser preenchidos em massa.
     */
    protected $fillable = [
        'name',
        'activity_type', 
        'cpf_cnpj',
        'contact_name',
        'cep',
        'address',
        'city',
        'uf',
        'phone',
        'whatsapp',
        'tenant_id',
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
}
