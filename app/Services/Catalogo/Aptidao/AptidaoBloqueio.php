<?php

namespace App\Services\Catalogo\Aptidao;

use App\Models\Asset;
use Carbon\CarbonInterface;
use Illuminate\Validation\ValidationException;

/**
 * Consumidor do motor para ALOCACAO/DESPACHO. Bloqueia (so com a chave do contrato ligada) quando o ativo esta:
 *  - Inapto (sem liberacao excepcional vigente);
 *  - com cadastro incompleto (sem grupo/grupo arquivado) -- "nao entra em despacho";
 *  - sob regra especial de bloqueio (ex.: guindaste sem plano de icamento) -- a liberacao NAO levanta este.
 * "Apto com restricao" nao bloqueia. Calcula na hora (nunca depende do cache, que pode estar velho por vencimento de data).
 */
class AptidaoBloqueio
{
    public function __construct(private AssetFitnessService $motor) {}

    /** Mensagem do bloqueio, ou null se o ativo pode ser alocado. */
    public function motivo(Asset $ativo, ?CarbonInterface $agora = null): ?string
    {
        if (! AptidaoGate::ligada($ativo->tenant_id)) {
            return null;
        }

        $r = $this->motor->calcular($ativo, $agora);
        $nome = $ativo->name ?: $ativo->tag;
        $texto = collect($r->motivos)->where('nivel', '!=', ResultadoAptidao::APTO)->pluck('texto')->implode(' ');

        if ($r->status === ResultadoAptidao::INCOMPLETO) {
            return "{$nome}: cadastro incompleto, não pode ser alocado. {$texto}";
        }

        if ($r->bloqueiaAlocacao) {
            $regra = collect($r->motivos)->where('nivel', ResultadoAptidao::APTO)->pluck('texto')->implode(' ');

            return "{$nome}: {$regra} Regularize o requisito (a liberação excepcional não vale para este bloqueio).";
        }

        if ($r->status === ResultadoAptidao::INAPTO && ! $r->liberacao) {
            return "{$nome} está Inapto e não pode ser alocado: {$texto} Regularize ou peça a liberação excepcional a um gestor (com justificativa).";
        }

        return null;
    }

    /** @throws ValidationException */
    public function exigir(Asset $ativo): void
    {
        if ($mensagem = $this->motivo($ativo)) {
            throw ValidationException::withMessages(['asset_id' => $mensagem]);
        }
    }

    public static function exigirPodeAlocar(?Asset $ativo): void
    {
        if ($ativo) {
            app(self::class)->exigir($ativo);
        }
    }
}
