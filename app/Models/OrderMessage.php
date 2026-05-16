<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class OrderMessage extends Model implements HasMedia
{
    use InteractsWithMedia; 

    // 🛡️ A BALA DE PRATA: Diz ao Laravel para aceitar TODAS as colunas 
    // que o nosso componente Livewire enviar, ignorando bloqueios de Mass Assignment.
    protected $guarded = [];

    public function chatRoom(): BelongsTo
    {
        return $this->belongsTo(ChatRoom::class, 'chat_room_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function maintenanceOrder(): BelongsTo
    {
        return $this->belongsTo(MaintenanceOrder::class, 'maintenance_order_id');
    }
}