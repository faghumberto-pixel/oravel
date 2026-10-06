<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Item de um modelo de checklist. Sem tenant_id: o isolamento vem do modelo (pai). */
class FrotaItemModeloChecklist extends Model
{
    use HasUuids;

    protected $table = 'frota_itens_modelo_checklist';

    public const GRAVIDADE_CRITICA = 'critica';

    public const GRAVIDADE_ATENCAO = 'atencao';

    public const GRAVIDADE_INFORMATIVA = 'informativa';

    public const RESPOSTA_OK_PROBLEMA = 'ok_problema';

    public const RESPOSTA_NUMERO = 'numero';

    public const RESPOSTA_TEXTO = 'texto';

    protected $fillable = ['modelo_id', 'descricao', 'categoria', 'gravidade', 'tipo_resposta', 'unidade', 'exige_foto_se_problema', 'ordem'];

    protected $casts = ['exige_foto_se_problema' => 'boolean', 'ordem' => 'integer'];

    /** @return array<string, string> */
    public static function categoriaLabels(): array
    {
        return [
            'pneus' => 'Pneus', 'fluidos' => 'Fluidos', 'luzes' => 'Luzes', 'freios' => 'Freios', 'documentos' => 'Documentos',
            'seguranca' => 'Segurança', 'carroceria' => 'Carroceria', 'outros' => 'Outros',
        ];
    }

    /** @return array<string, string> */
    public static function gravidadeLabels(): array
    {
        return [
            self::GRAVIDADE_CRITICA => 'Crítica (bloqueia a saída)',
            self::GRAVIDADE_ATENCAO => 'Atenção',
            self::GRAVIDADE_INFORMATIVA => 'Informativa',
        ];
    }

    /** @return array<string, string> */
    public static function tipoRespostaLabels(): array
    {
        return [
            self::RESPOSTA_OK_PROBLEMA => 'OK ou Problema',
            self::RESPOSTA_NUMERO => 'Número (com OK ou Problema)',
            self::RESPOSTA_TEXTO => 'Texto',
        ];
    }

    public function modelo(): BelongsTo
    {
        return $this->belongsTo(FrotaModeloChecklist::class, 'modelo_id');
    }
}
