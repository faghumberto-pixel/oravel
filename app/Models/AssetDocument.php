<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * Documento de um ativo (laudo, nota fiscal, manual...). O arquivo fica no disco PRIVADO
 * 'media_private': só abre por link assinado temporário (ver DocumentsRelationManager).
 */
class AssetDocument extends Model implements HasMedia
{
    use BelongsToTenant;
    use HasUuids;
    use InteractsWithMedia;

    public const TIPO_LAUDO = 'laudo';

    public const TIPO_NOTA_FISCAL = 'nota_fiscal';

    public const TIPO_MANUAL = 'manual';

    public const TIPO_CERTIFICADO = 'certificado';

    public const TIPO_SEGURO = 'seguro';

    public const TIPO_OUTRO = 'outro';

    protected $fillable = ['tenant_id', 'asset_id', 'tipo', 'titulo', 'numero', 'data_emissao', 'data_validade', 'valor', 'observacoes'];

    protected $casts = [
        'data_emissao' => 'date',
        'data_validade' => 'date',
        'valor' => 'decimal:2',
    ];

    /** @return array<string, string> */
    public static function tipoLabels(): array
    {
        return [
            self::TIPO_LAUDO => 'Laudo',
            self::TIPO_NOTA_FISCAL => 'Nota fiscal',
            self::TIPO_MANUAL => 'Manual',
            self::TIPO_CERTIFICADO => 'Certificado',
            self::TIPO_SEGURO => 'Seguro / apólice',
            self::TIPO_OUTRO => 'Outro',
        ];
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('arquivo')
            ->useDisk('media_private')
            ->singleFile()
            ->acceptsMimeTypes([
                'application/pdf', 'image/jpeg', 'image/png', 'image/webp',
                'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ]);
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function isVencido(): bool
    {
        return $this->data_validade !== null && $this->data_validade->isPast();
    }

    public function isProximoVencimento(): bool
    {
        return $this->data_validade !== null && ! $this->isVencido() && $this->data_validade->diffInDays(now()) <= 30;
    }
}
