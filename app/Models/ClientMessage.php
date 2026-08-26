<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasSaaSMetadata;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * Chat bidirecional Tenant<->Client -- append-only, sem edição/exclusão.
 * sender_type resolve manualmente quem mandou ('client' ou 'user'), sem
 * morphs() completo (só 2 tipos possíveis). Anexo via MediaLibrary
 * (coleção 'anexos', sem restrição de mimetype -- aceita imagem OU
 * documento), mesmo padrão de EmailMessage/CaixaDeEmail.
 */
class ClientMessage extends Model implements HasMedia
{
    use BelongsToTenant, HasFactory, HasSaaSMetadata, HasUuids, InteractsWithMedia;

    protected static ?string $saasFeatureKey = 'tabela_client_messages';

    protected static ?string $saasPermissionSlug = 'mensagem_cliente';

    protected static ?string $saasModuleLabel = 'Mensagens de Clientes';

    public const SENDER_CLIENT = 'client';

    public const SENDER_USER = 'user';

    protected $fillable = [
        'tenant_id', 'client_id', 'sender_type', 'sender_id', 'body', 'read_at',
    ];

    protected $casts = [
        'read_at' => 'datetime',
    ];

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('anexos');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function isFromClient(): bool
    {
        return $this->sender_type === self::SENDER_CLIENT;
    }

    public function senderName(): string
    {
        if ($this->isFromClient()) {
            return $this->client?->name ?? 'Cliente';
        }

        return User::withoutGlobalScope('tenant')->find($this->sender_id)?->name ?? 'Equipe';
    }
}
