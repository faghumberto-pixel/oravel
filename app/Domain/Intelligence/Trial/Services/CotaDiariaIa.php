<?php

namespace App\Domain\Intelligence\Trial\Services;

use App\Domain\Intelligence\Trial\Models\IntelligenceTrial;
use App\Domain\Intelligence\Trial\Support\Planos;
use Illuminate\Support\Facades\Cache;

/**
 * Cota diária do agente de IA para quem está no TESTE grátis do Intelligence (custo de IA sem receita). A cota é do cliente
 * (tenant), não do usuário: criar outro usuário não renova. O dia vira à meia-noite de São Paulo. Quem contrata o plano básico
 * passa a ter a cota do seu degrau (Planos::iaPorDia), também diária.
 */
class CotaDiariaIa
{
    /** Consultas por dia deste cliente: a cota do teste ou a do degrau de plano em vigor. */
    public function limite(string $tenantId): int
    {
        $trial = IntelligenceTrial::withoutGlobalScopes()->where('tenant_id', $tenantId)->first();

        return $trial ? Planos::iaPorDia($trial) : 0;
    }

    private function contratou(string $tenantId): bool
    {
        return (bool) IntelligenceTrial::withoutGlobalScopes()->where('tenant_id', $tenantId)->whereNotNull('contracted_at')->exists();
    }

    /** A cota vale para este cliente? Para toda conta do pacote (teste ou plano básico); cliente do ERP comum fica fora. */
    public function aplica(?string $tenantId): bool
    {
        return $tenantId && IntelligenceTrial::withoutGlobalScopes()->where('tenant_id', $tenantId)->exists();
    }

    /** Perguntas já feitas hoje por este cliente (só leitura, para exibir no perfil). */
    public function usadasHoje(string $tenantId): int
    {
        return min($this->limite($tenantId), max(0, (int) Cache::get($this->chave($tenantId), 0)));
    }

    /** Reserva uma consulta de hoje. false = cota esgotada (nada é consumido). */
    public function reservar(string $tenantId): bool
    {
        $chave = $this->chave($tenantId);
        Cache::add($chave, 0, now()->addDays(2));
        $usadas = (int) Cache::increment($chave);
        if ($usadas > $this->limite($tenantId)) {
            Cache::decrement($chave);

            return false;
        }

        return true;
    }

    /** Devolve a consulta quando a IA falhou (o cliente não recebeu resposta). */
    public function devolver(string $tenantId): void
    {
        $chave = $this->chave($tenantId);
        if ((int) Cache::get($chave, 0) > 0) {
            Cache::decrement($chave);
        }
    }

    /** Texto quando a cota do dia acabou. No teste, convida a assinar o plano básico (que tem mais consultas). */
    public function mensagemEsgotada(string $tenantId): string
    {
        $n = $this->limite($tenantId);
        $plural = fn (int $q) => $q === 1 ? '1 pergunta' : $q.' perguntas';

        if ($this->contratou($tenantId)) {
            return 'Você usou as '.$plural($n).' de hoje do seu plano. O limite renova amanhã.';
        }

        $plano = Planos::achar('basico')['ia_por_dia'] ?? 10;

        return 'No teste grátis o assistente responde '.$plural($n).' por dia, e a de hoje já foi usada. '
            .'Quer mais? **[Assine o Plano Básico]('.route('intelligence.trial.plano').')** e tenha '.$plano.' consultas por dia, sem esperar o fim dos 30 dias do teste.';
    }

    private function chave(string $tenantId): string
    {
        return 'intelligence-trial-ia:'.$tenantId.':'.now('America/Sao_Paulo')->toDateString();
    }
}
