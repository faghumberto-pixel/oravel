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
 * Documentação técnica de um equipamento NR-13 (prontuário, projeto, certificado de inspeção
 * etc.) -- mesmo padrão de FleetVehicleDocument/EmployeeCertification: tipo + validade + arquivo.
 *
 * NÃO é o mesmo domínio de App\Models\Pmoc (esse é o PMOC de ar-condicionado da RE-ANVISA
 * 09/2003). TIPO_PMOC aqui é só um rótulo de documento, caso o tenant chame assim um plano de
 * manutenção do próprio equipamento sujeito à NR-13 -- tabelas e models totalmente separados.
 */
class Nr13Document extends Model implements HasMedia
{
    use BelongsToTenant;
    use HasFactory;
    use HasSaaSMetadata;
    use HasUuids;
    use InteractsWithMedia;

    protected $table = 'nr13_documents';

    protected static ?string $saasFeatureKey = 'tabela_nr13_documents';

    protected static ?string $saasPermissionSlug = 'documento_nr13';

    protected static ?string $saasModuleLabel = 'Documentos NR-13';

    public const TIPO_PRONTUARIO = 'prontuario';

    public const TIPO_PROJETO = 'projeto';

    public const TIPO_PMOC = 'pmoc';

    public const TIPO_CERTIFICADO_INSPECAO = 'certificado_inspecao';

    public const TIPO_LAUDO = 'laudo';

    public const TIPO_OUTRO = 'outro';

    protected $fillable = [
        'tenant_id',
        'asset_id',
        'tipo',
        'data_emissao',
        'data_validade',
        'observacoes',
    ];

    protected $casts = [
        'data_emissao' => 'date',
        'data_validade' => 'date',
    ];

    public static function tipoLabels(): array
    {
        return [
            self::TIPO_PRONTUARIO => 'Prontuário',
            self::TIPO_PROJETO => 'Projeto',
            self::TIPO_PMOC => 'PMOC',
            self::TIPO_CERTIFICADO_INSPECAO => 'Certificado de Inspeção',
            self::TIPO_LAUDO => 'Laudo',
            self::TIPO_OUTRO => 'Outro',
        ];
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('arquivo')->singleFile();
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function isVencido(): bool
    {
        return $this->data_validade && $this->data_validade->isPast();
    }

    public function isProximoVencimento(int $dias = 30): bool
    {
        return $this->data_validade
            && ! $this->isVencido()
            && $this->data_validade->lessThanOrEqualTo(now()->addDays($dias));
    }
}
