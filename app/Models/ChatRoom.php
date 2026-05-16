<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChatRoom extends Model
{
    use HasUuids; // Essencial para aceitar os IDs em formato UUID da Oravel

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