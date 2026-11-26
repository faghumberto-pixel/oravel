<?php

namespace App\Domain\Intelligence\Trial\Support;

use App\Domain\Intelligence\Trial\Models\IntelligenceTrial;
use App\Domain\Intelligence\Trial\Services\TrialCiclo;

/**
 * Degraus de plano do Intelligence (config intelligence_trial.planos), cumulativos: cada um inclui os módulos do anterior.
 * O teste grátis não é um degrau: é "sem plano" (chave null) com a cota de teste.
 */
class Planos
{
    /**
     * @return array<string, array{chave: string, nome: string, preco: float, ciclo: string, ia_por_dia: int, modulos_novos: list<string>, modulos: list<string>, relatorios: string, nivel: int, contratavel: bool}>
     */
    public static function todos(): array
    {
        $cfg = (array) config('intelligence_trial.planos');
        $prontos = (array) config('intelligence_trial.modulos_prontos');
        $acumulado = [];
        $saida = [];
        $nivel = 0;

        foreach ($cfg as $chave => $p) {
            // O Básico lê nome, preço e ciclo de 'plano' (fonte única, já usada pelo contrato e pelo site).
            $base = $chave === 'basico' ? (array) config('intelligence_trial.plano') : [];
            $novos = array_values((array) ($p['modulos'] ?? []));
            $acumulado = array_values(array_unique(array_merge($acumulado, $novos)));

            $saida[$chave] = [
                'chave' => $chave,
                'nome' => (string) ($base['nome'] ?? $p['nome'] ?? $chave),
                'preco' => (float) ($base['preco'] ?? $p['preco'] ?? 0),
                'ciclo' => (string) ($base['ciclo'] ?? $p['ciclo'] ?? 'MONTHLY'),
                'ia_por_dia' => max(0, (int) ($p['ia_por_dia'] ?? 0)),
                'modulos_novos' => $novos,
                'modulos' => $acumulado,
                'relatorios' => (string) ($p['relatorios'] ?? ''),
                'nivel' => $nivel++,
                'contratavel' => array_diff($acumulado, $prontos) === [],
            ];
        }

        return $saida;
    }

    public static function achar(?string $chave): ?array
    {
        return $chave ? (self::todos()[$chave] ?? null) : null;
    }

    /**
     * Degrau em vigor agora. null = teste grátis (ou nunca contratou). Troca para degrau menor agendada vale a partir da data marcada
     * (resolvido na leitura, sem rotina agendada).
     */
    public static function efetivo(IntelligenceTrial $trial): ?array
    {
        if (! $trial->contratou()) {
            return null;
        }
        $proximo = $trial->plano_proximo_chave;
        if ($proximo && $trial->plano_proximo_em && ! $trial->plano_proximo_em->isFuture() && self::achar($proximo)) {
            return self::achar($proximo);
        }

        return self::achar($trial->plano_chave) ?? self::achar('basico');
    }

    public static function iaPorDia(IntelligenceTrial $trial): int
    {
        return self::efetivo($trial)['ia_por_dia'] ?? max(0, (int) config('intelligence_trial.ia_consultas_por_dia', 1));
    }

    public static function temModulo(IntelligenceTrial $trial, string $modulo): bool
    {
        // Teste grátis (sem degrau): só os módulos de config intelligence_trial.modulos_no_teste (padrão: o básico).
        return in_array($modulo, self::efetivo($trial)['modulos'] ?? (array) config('intelligence_trial.modulos_no_teste', ['base']), true);
    }

    /**
     * O cliente pode usar este módulo? Quem tem conta do Intelligence segue o degrau do plano; empresa sem conta de teste (contrato
     * feito pela Central) segue só as funcionalidades do contrato.
     */
    public static function moduloLiberado(?string $tenantId, string $modulo): bool
    {
        $trial = $tenantId ? TrialCiclo::doTenant($tenantId) : null;

        return $trial === null || self::temModulo($trial, $modulo);
    }

    public static function rotuloModulo(string $modulo): string
    {
        return (string) (config('intelligence_trial.modulos_rotulos')[$modulo] ?? ucfirst($modulo));
    }
}
