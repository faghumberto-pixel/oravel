<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasSaaSMetadata;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * Reclamação, sugestão ou elogio registrado pelo cliente no Portal do Cliente (tela "Fale Conosco"). Cada registro
 * ganha um protocolo para o cliente acompanhar; a equipe da locadora responde em App\Filament\Resources\ClientFeedbackResource.
 * Anexos (foto, documento) pela coleção 'anexos', como nas mensagens do cliente.
 */
class ClientFeedback extends Model implements HasMedia
{
    use BelongsToTenant, HasSaaSMetadata, HasUuids, InteractsWithMedia;

    protected static ?string $saasFeatureKey = 'tabela_client_feedbacks';

    protected static ?string $saasPermissionSlug = 'feedback_cliente';

    protected static ?string $saasModuleLabel = 'Reclamações, Sugestões e Elogios';

    protected $table = 'client_feedbacks';

    public const TYPE_RECLAMACAO = 'reclamacao';

    public const TYPE_SUGESTAO = 'sugestao';

    public const TYPE_ELOGIO = 'elogio';

    public const STATUS_RECEBIDO = 'recebido';

    public const STATUS_EM_ANALISE = 'em_analise';

    public const STATUS_CONCLUIDO = 'concluido';

    protected $fillable = [
        'tenant_id', 'client_id', 'protocol', 'type', 'subject', 'description', 'status', 'response', 'responded_by', 'responded_at',
    ];

    protected $casts = [
        'responded_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $feedback) {
            $feedback->protocol ??= self::newProtocol($feedback->tenant_id);
        });
    }

    /** Protocolo curto e único por empresa: FB-AAMMDD-XXXX. */
    public static function newProtocol(?string $tenantId): string
    {
        do {
            $protocol = 'FB-'.now()->format('ymd').'-'.Str::upper(Str::random(4));
        } while (self::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('protocol', $protocol)->exists());

        return $protocol;
    }

    /** @return array<string, string> */
    public static function typeLabels(): array
    {
        return [
            self::TYPE_RECLAMACAO => 'Reclamação',
            self::TYPE_SUGESTAO => 'Sugestão',
            self::TYPE_ELOGIO => 'Elogio',
        ];
    }

    /** @return array<string, string> */
    public static function statusLabels(): array
    {
        return [
            self::STATUS_RECEBIDO => 'Recebido',
            self::STATUS_EM_ANALISE => 'Em análise',
            self::STATUS_CONCLUIDO => 'Concluído',
        ];
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('anexos');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function responder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responded_by');
    }
}
