<?php

namespace App\Models;

use App\Models\Concerns\HasSaaSMetadata;
use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Client extends Model
{
    use HasSaaSMetadata;

    protected static ?string $saasFeatureKey = 'tabela_clients';

    protected static ?string $saasPermissionSlug = 'cliente';

    protected static ?string $saasModuleLabel = 'Clientes';

    use BelongsToTenant;

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
        'cep',
        'address',
        'city',
        'uf',
        'phone',
        'whatsapp',
        'tenant_id',
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
