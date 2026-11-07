<?php

namespace App\Services\Catalogo\Aptidao;

use App\Models\AptidaoLiberacao;
use App\Models\Asset;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/** Liberacao excepcional de ativo Inapto: so quem tem a permissao propria, com justificativa, prazo (max. 30 dias) e trilha. */
class AptidaoLiberacaoService
{
    public const PRAZO_MAXIMO_DIAS = 30;

    public function __construct(private AssetFitnessService $motor) {}

    /**
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function liberar(Asset $ativo, string $justificativa, Carbon $validaAte, User $usuario): AptidaoLiberacao
    {
        Gate::forUser($usuario)->authorize('create', AptidaoLiberacao::class);

        if (mb_strlen(trim($justificativa)) < 10) {
            throw ValidationException::withMessages(['justificativa' => 'Informe a justificativa (mínimo de 10 caracteres).']);
        }

        if ($validaAte->lte(now()) || $validaAte->gt(now()->addDays(self::PRAZO_MAXIMO_DIAS)->endOfDay())) {
            throw ValidationException::withMessages(['valida_ate' => 'O prazo da liberação deve ser futuro e de no máximo '.self::PRAZO_MAXIMO_DIAS.' dias.']);
        }

        $r = $this->motor->calcular($ativo);

        if ($r->status !== ResultadoAptidao::INAPTO) {
            throw ValidationException::withMessages(['asset_id' => 'Só um ativo Inapto precisa de liberação excepcional (status atual: '.AptidaoImpacto::rotulo($r->status).').']);
        }

        if ($r->liberacao) {
            throw ValidationException::withMessages(['asset_id' => 'Este ativo já tem uma liberação vigente. Revogue-a antes de criar outra.']);
        }

        $l = AptidaoLiberacao::withoutGlobalScopes()->create([
            'tenant_id' => $ativo->tenant_id, 'asset_id' => $ativo->id, 'usuario_id' => $usuario->id,
            'justificativa' => trim($justificativa), 'valida_ate' => $validaAte, 'motivos_snapshot' => $r->motivos,
        ]);

        activity()->performedOn($ativo)->causedBy($usuario)->withProperties([
            'justificativa' => $l->justificativa, 'valida_ate' => $validaAte->toIso8601String(), 'motivos' => $r->motivos,
            'aviso' => 'Liberação excepcional de ativo Inapto: validar com o responsável técnico.',
        ])->log('Aptidão: liberação excepcional concedida');

        $this->motor->recalcular($ativo);

        return $l;
    }

    public function revogar(AptidaoLiberacao $liberacao, string $justificativa, User $usuario): void
    {
        Gate::forUser($usuario)->authorize('create', AptidaoLiberacao::class);

        if (mb_strlen(trim($justificativa)) < 5) {
            throw ValidationException::withMessages(['justificativa' => 'Informe a justificativa da revogação.']);
        }

        $liberacao->forceFill(['revogada_em' => now(), 'revogada_por' => $usuario->id, 'revogada_justificativa' => trim($justificativa)])->save();

        $ativo = Asset::withoutGlobalScopes()->find($liberacao->asset_id);

        activity()->performedOn($ativo)->causedBy($usuario)->withProperties(['justificativa' => trim($justificativa), 'liberacao_id' => $liberacao->id])
            ->log('Aptidão: liberação excepcional revogada');

        $this->motor->recalcular($ativo);
    }
}
