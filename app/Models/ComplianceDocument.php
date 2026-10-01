<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasSaaSMetadata;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/** ART, laudos, certificados de calibração e seguros, com validade. */
class ComplianceDocument extends Model
{
    use BelongsToTenant;
    use HasSaaSMetadata;
    use HasUuids;
    use SoftDeletes;

    protected static ?string $saasFeatureKey = 'tabela_compliance_documents';

    protected static ?string $saasPermissionSlug = 'documento_conformidade';

    protected static ?string $saasModuleLabel = 'Segurança e Documentação';

    protected $fillable = [
        'tenant_id', 'document_type', 'number', 'issuer', 'asset_id', 'contract_id', 'issue_date',
        'expires_at', 'coverage_value', 'responsible', 'attachment', 'notes',
    ];

    protected $casts = [
        'issue_date' => 'date',
        'expires_at' => 'date',
        'coverage_value' => 'decimal:2',
    ];

    /** @return array<string, string> */
    public static function typeLabels(): array
    {
        return [
            'art' => 'ART (Anotação de Responsabilidade Técnica)',
            'laudo_aterramento' => 'Laudo de aterramento',
            'certificado_calibracao' => 'Certificado de calibração (cabos/ganchos)',
            'seguro_reta' => 'Seguro RETA',
            'seguro_riscos_diversos' => 'Seguro de Riscos Diversos',
            'outro' => 'Outro laudo/certificado',
        ];
    }

    public function isInsurance(): bool
    {
        return in_array($this->document_type, ['seguro_reta', 'seguro_riscos_diversos'], true);
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }
}
