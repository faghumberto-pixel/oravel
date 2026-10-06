<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Resposta de um item no checklist (com cópia do texto e da gravidade). Sem tenant_id: isolamento pelo checklist. */
class FrotaRespostaChecklist extends Model
{
    use HasUuids;

    protected $table = 'frota_respostas_checklist';

    public const OK = 'ok';

    public const PROBLEMA = 'problema';

    public const NAO_SE_APLICA = 'nao_se_aplica';

    protected $fillable = [
        'checklist_id', 'item_id', 'descricao_registrada', 'categoria_registrada', 'gravidade_registrada',
        'resultado', 'valor_numerico', 'unidade', 'observacao',
    ];

    protected $casts = ['valor_numerico' => 'decimal:2'];

    /** @return array<string, string> */
    public static function resultadoLabels(): array
    {
        return [self::OK => 'OK', self::PROBLEMA => 'Problema', self::NAO_SE_APLICA => 'Não se aplica'];
    }

    public function checklist(): BelongsTo
    {
        return $this->belongsTo(FrotaChecklist::class, 'checklist_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(FrotaItemModeloChecklist::class, 'item_id');
    }
}
