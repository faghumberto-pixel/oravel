<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Item de checklist. Nao e modulo SaaS proprio: herda chave e permissao de CkTemplate. */
class CkItem extends Model
{
    use BelongsToTenant;
    use HasUuids;
    use SoftDeletes;

    public const TIPOS_RESPOSTA = [
        'conforme_nao_conforme_na' => 'Conforme / Não conforme / N.A.',
        'numerico' => 'Número',
        'texto' => 'Texto',
        'foto' => 'Foto',
        'selecao' => 'Seleção',
    ];

    protected $table = 'ck_items';

    protected $guarded = [];

    protected $casts = [
        'opcoes' => 'array', 'obrigatorio' => 'boolean', 'critico' => 'boolean', 'exige_foto_se_nao_conforme' => 'boolean',
        'validar_norma' => 'boolean', 'ativo' => 'boolean', 'personalizado' => 'boolean', 'ordem' => 'integer',
    ];

    /** Padroes aplicados quando o formulario manda null (toggle nunca tocado): as colunas booleanas sao NOT NULL. */
    private const PADROES = ['obrigatorio' => true, 'critico' => false, 'exige_foto_se_nao_conforme' => false, 'validar_norma' => false, 'ativo' => true, 'personalizado' => false];

    protected static function booted(): void
    {
        static::saving(function (self $item) {
            foreach (self::PADROES as $campo => $padrao) {
                if ($item->getAttribute($campo) === null) {
                    $item->setAttribute($campo, $padrao);
                }
            }
        });
    }

    public function secao(): BelongsTo
    {
        return $this->belongsTo(CkSection::class, 'section_id');
    }

    /** Requisito do catalogo (G1) que este item alimenta quando e reprovado. */
    public function requisito(): BelongsTo
    {
        return $this->belongsTo(CatalogoRequisito::class, 'catalogo_requisito_id');
    }
}
