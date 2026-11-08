<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasSaaSMetadata;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Liberacao excepcional de um ativo INAPTO (G1). Nao apaga a inaptidao: o status continua Inapto e visivel; a liberacao so
 * permite alocar/despachar ate valida_ate. Exige justificativa, tem prazo e trilha (activity log). Nao levanta o bloqueio de
 * regra especial (ex.: guindaste sem plano de icamento) nem o "cadastro incompleto".
 *
 * Permissao propria (decisao do plano): criar_liberacao_aptidao ("liberar ativo inapto") so para gestor/responsavel tecnico,
 * atribuida em Perfis de Acesso; o admin do tenant tem o bypass padrao. Mesma chave de funcionalidade do catalogo.
 */
class AptidaoLiberacao extends Model
{
    use BelongsToTenant;
    use HasSaaSMetadata;
    use HasUuids;

    protected static ?string $saasFeatureKey = CatalogoItem::FEATURE_KEY;

    protected static ?string $saasPermissionSlug = 'liberacao_aptidao';

    protected static ?string $saasModuleLabel = 'Liberação excepcional de ativo Inapto';

    protected $table = 'aptidao_liberacoes';

    protected $guarded = [];

    protected $casts = ['valida_ate' => 'datetime', 'revogada_em' => 'datetime', 'motivos_snapshot' => 'array'];

    /** Liberacao vigente do ativo (nao revogada e dentro do prazo), a mais recente. */
    public static function vigente(Asset $ativo, ?\DateTimeInterface $agora = null): ?self
    {
        return static::withoutGlobalScopes()->where('tenant_id', $ativo->tenant_id)->where('asset_id', $ativo->id)
            ->whereNull('revogada_em')->where('valida_ate', '>', $agora ?? now())->orderByDesc('valida_ate')->first();
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }
}
