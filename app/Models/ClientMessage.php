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

    /**
     * 4 áreas fixas -- Client escolhe ao mandar a mensagem (não é
     * inferida depois). Cada área tem uma role dedicada (ver
     * areaRoleName()/database/seeders/ClientMessageAreaRolesSeeder.php)
     * que filtra quem no Tenant enxerga a mensagem
     * (User::visibleClientMessageAreas()). Mensagens antigas sem área
     * (area = null) ficam visíveis a todos -- fallback seguro, não
     * esconde histórico pré-existente.
     */
    public const AREA_FINANCEIRO = 'financeiro';

    public const AREA_MANUTENCAO = 'manutencao';

    public const AREA_COMERCIAL = 'comercial';

    public const AREA_LOGISTICA = 'logistica';

    protected $fillable = [
        'tenant_id', 'client_id', 'area', 'sender_type', 'sender_id', 'body', 'read_at',
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

    /**
     * @return array<string, string>
     */
    public static function areaLabels(): array
    {
        return [
            self::AREA_FINANCEIRO => 'Financeiro',
            self::AREA_MANUTENCAO => 'Manutenção',
            self::AREA_COMERCIAL => 'Comercial',
            self::AREA_LOGISTICA => 'Logística',
        ];
    }

    /**
     * Nome da Role dedicada que enxerga mensagens desta área -- ver
     * ClientMessageAreaRolesSeeder. Roles próprias (sufixo "(Mensagens)"),
     * não reaproveitam roles existentes de outro domínio (ex: 'Comercial'
     * já usada por EquipmentDamageObserver com semântica diferente).
     */
    public static function areaRoleName(string $area): ?string
    {
        return [
            self::AREA_FINANCEIRO => 'Financeiro (Mensagens)',
            self::AREA_MANUTENCAO => 'Manutenção (Mensagens)',
            self::AREA_COMERCIAL => 'Comercial (Mensagens)',
            self::AREA_LOGISTICA => 'Logística (Mensagens)',
        ][$area] ?? null;
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
