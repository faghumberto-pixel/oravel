<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasSaaSMetadata;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Execucao de um checklist em um ativo. Guarda SNAPSHOT (nome e versao do template; texto de cada item nas respostas):
 * editar o template depois nao altera o que ja foi executado. Modulo SaaS proprio porque quem executa (tecnico) nao e quem
 * edita os modelos (permissoes separadas).
 */
class CkExecution extends Model
{
    use BelongsToTenant;
    use HasSaaSMetadata;
    use HasUuids;

    public const STATUS_EM_ANDAMENTO = 'em_andamento';

    public const STATUS_APROVADO = 'aprovado';

    public const STATUS_APROVADO_COM_RESSALVA = 'aprovado_com_ressalva';

    public const STATUS_REPROVADO = 'reprovado';

    protected static ?string $saasFeatureKey = 'tabela_ck_execucoes';

    protected static ?string $saasPermissionSlug = 'execucao_checklist';

    protected static ?string $saasModuleLabel = 'Execuções de Checklist';

    protected $table = 'ck_executions';

    protected $guarded = [];

    protected $casts = ['iniciado_em' => 'datetime', 'finalizado_em' => 'datetime', 'leitura_medidor' => 'decimal:1', 'versao_template' => 'integer'];

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'asset_id');
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(CkTemplate::class, 'template_id');
    }

    public function executor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'executor_id');
    }

    public function respostas(): HasMany
    {
        return $this->hasMany(CkAnswer::class, 'execution_id');
    }
}
