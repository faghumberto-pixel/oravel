<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Client extends Model
{
    use HasFactory, HasUuids, SoftDeletes, BelongsToTenant;

    /**
     * CONFIGURAÇÃO DE UUID
     * Essencial para evitar erro 404: informa ao Laravel que o ID é string e não auto-incremento.
     */
    protected $keyType = 'string';
    public $incrementing = false;

    /**
     * Atributos que podem ser preenchidos em massa.
     * Incluímos o tenant_id para que o sistema Multi-tenancy carimbe os registros corretamente.
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
     * RELAÇÃO COM O TENANT (Dono do Registro)
     * Obrigatória para o Filament Multi-tenancy identificar a qual empresa este registro pertence.
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
     * Accessor para formatar endereço completo em colunas ou documentos.
     * Uso: $client->full_location
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
