<?php

namespace App\Models;

use App\Models\Concerns\HasSaaSMetadata;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChatRoom extends Model
{
    // Essencial para aceitar os IDs em formato UUID da Oravel
    use BelongsToTenant; // Injetado com sucesso no lugar correto!
    use HasSaaSMetadata;
    use HasUuids;

    protected static ?string $saasFeatureKey = 'modulo_chat';

    protected static ?string $saasPermissionSlug = 'chat';

    protected static ?string $saasModuleLabel = 'Chat Interno';

    protected $fillable = [
        'id',
        'tenant_id',
        'type',
        'title',
        'maintenance_order_id',
    ];

    /**
     * Define quem tem permissão para ler e escrever nesta sala (Garante o sigilo)
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'chat_room_user', 'chat_room_id', 'user_id');
    }

    /**
     * Histórico de mensagens/audios/fotos vinculados a esta sala
     * CORREÇÃO: Apontando definitivamente para ChatMessage (para evitar o erro de UUID vs Integer na tabela Media)
     */
    public function messages(): HasMany
    {
        return $this->hasMany(ChatMessage::class, 'chat_room_id');
    }

    /**
     * Isolamento multi-tenant da Oravel
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Relacionamento direto com a Ordem de Serviço (OS)
     */
    public function maintenanceOrder(): BelongsTo
    {
        return $this->belongsTo(MaintenanceOrder::class, 'maintenance_order_id');
    }
}
