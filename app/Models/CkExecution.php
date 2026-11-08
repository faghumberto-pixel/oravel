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

    protected $casts = ['iniciado_em' => 'datetime', 'finalizado_em' => 'datetime', 'leitura_medidor' => 'decimal:1', 'versao_template' => 'integer', 'folhas' => 'array'];

    public const ORIGEM_PAPEL_IA = 'papel_ia';

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'asset_id');
    }

    public function ordemServico(): BelongsTo
    {
        return $this->belongsTo(MaintenanceOrder::class, 'ordem_servico_id');
    }

    /** OS em que este checklist foi feito (o atendimento). A OS corretiva aberta pela reprovacao e a `ordemServico`. */
    public function os(): BelongsTo
    {
        return $this->belongsTo(MaintenanceOrder::class, 'os_id');
    }

    public function origemRotulo(): string
    {
        return match (true) {
            $this->legado_tipo === 'frota_checklist' => 'Frota',
            $this->registro_origem === self::ORIGEM_PAPEL_IA => 'Papel (IA)',
            default => 'Checklist',
        };
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
