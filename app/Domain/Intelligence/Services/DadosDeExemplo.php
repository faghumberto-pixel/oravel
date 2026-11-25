<?php

namespace App\Domain\Intelligence\Services;

use App\Domain\Intelligence\Models\IntelligenceImport;
use App\Domain\Intelligence\Trial\Models\IntelligenceTrial;
use App\Models\Tenant;
use App\Services\Demo\IntelligenceDemoService;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Dados de EXEMPLO (demonstração) do Intelligence para o cliente do teste ver como as telas ficam com dados.
 *
 * - Entram pelo importador de verdade, marcados com origem 'demonstracao' (nada é calculado à parte, e nada do exemplo conta como envio
 *   real: não inicia os dias do teste).
 * - Aparecem sozinhos na 1ª abertura, se o cliente ainda não tem dado próprio (uma vez só).
 * - O cliente limpa quando quiser e pode pedir de volta, desde que ainda não tenha dado próprio e o prazo não tenha acabado.
 * - Somem de vez N dias depois da 1ª abertura (config intelligence_trial.exemplo_dias): limpa e nunca mais gera.
 * - Apagar a importação apaga o resto em cascata (snapshot, indicadores, diagnóstico, equipamentos).
 */
class DadosDeExemplo
{
    public const ORIGEM = 'demonstracao';

    public static function existe(string $tenantId): bool
    {
        return IntelligenceImport::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('origem', self::ORIGEM)->exists();
    }

    public static function temDadoReal(string $tenantId): bool
    {
        return IntelligenceImport::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('origem', '!=', self::ORIGEM)->where('status', 'concluida')->exists();
    }

    /** Só exemplo na conta: não há nenhum dado próprio (usado pela IA para avisar que os números são fictícios). */
    public static function soExemplo(string $tenantId): bool
    {
        return self::existe($tenantId) && ! self::temDadoReal($tenantId);
    }

    /** O prazo do exemplo acabou? (contado da 1ª abertura). */
    public static function expirou(IntelligenceTrial $trial): bool
    {
        return $trial->exemplo_oferecido_em !== null
            && $trial->exemplo_oferecido_em->copy()->addDays((int) config('intelligence_trial.exemplo_dias', 30))->isPast();
    }

    /** Dias que faltam para o exemplo sumir (null = não se aplica). */
    public static function diasRestantes(IntelligenceTrial $trial): ?int
    {
        if ($trial->exemplo_oferecido_em === null) {
            return null;
        }
        $fim = $trial->exemplo_oferecido_em->copy()->addDays((int) config('intelligence_trial.exemplo_dias', 30));

        return max(0, (int) ceil(now()->diffInSeconds($fim, false) / 86400));
    }

    /** O cliente pode pedir o exemplo de volta agora? */
    public static function podeGerar(IntelligenceTrial $trial): bool
    {
        return ! self::expirou($trial) && ! self::existe((string) $trial->tenant_id) && ! self::temDadoReal((string) $trial->tenant_id);
    }

    /**
     * Gera as 3 fotografias mensais de exemplo (últimos 3 fins de mês) pelo importador. Idempotente: se já existe, não faz nada.
     *
     * @return int fotografias criadas
     *
     * @throws RuntimeException importação de exemplo falhou
     */
    public function gerar(Tenant $tenant): int
    {
        if (self::existe((string) $tenant->id)) {
            return 0;
        }

        $demo = app(IntelligenceDemoService::class);
        $importador = app(ImportadorExcel::class);
        $valores = array_values(IntelligenceDemoService::MESES);
        $criadas = 0;

        foreach ([3, 2, 1] as $i => $atras) {
            $data = now()->subMonthsNoOverflow($atras)->endOfMonth()->toDateString();
            $arquivo = $demo->planilha($data, $valores[$i], $demo->frota($i, $data));
            try {
                $r = $importador->importar($arquivo, $tenant, null, false, 'exemplo-'.$data.'.xlsx', self::ORIGEM);
            } finally {
                @unlink($arquivo);
            }
            if (! $r['ok']) {
                $this->limpar($tenant); // não deixa meia demonstração
                throw new RuntimeException("Exemplo de {$data} falhou: ".implode(' | ', $r['erros']));
            }
            $criadas++;
        }

        return $criadas;
    }

    /** Apaga só o exemplo; dado real nunca é tocado. @return int importações apagadas */
    public function limpar(Tenant|string $tenant): int
    {
        $id = $tenant instanceof Tenant ? (string) $tenant->id : $tenant;

        return IntelligenceImport::withoutGlobalScopes()->where('tenant_id', $id)->where('origem', self::ORIGEM)->get()->each->delete()->count();
    }

    /**
     * Chamado quando o cliente entra: na 1ª vez gera o exemplo (se ele ainda não tem dado próprio) e marca que já foi oferecido.
     * Depois do prazo, apaga o que sobrou. Nunca derruba o acesso: qualquer falha vai só para o log.
     */
    public function aoAbrir(IntelligenceTrial $trial): void
    {
        try {
            $tenant = $trial->tenant;
            if (! $tenant) {
                return;
            }
            if ($trial->exemplo_oferecido_em === null) {
                $trial->forceFill(['exemplo_oferecido_em' => now()])->save();
                if (! self::temDadoReal((string) $tenant->id)) {
                    $this->gerar($tenant);
                }

                return;
            }
            if (self::expirou($trial)) {
                $this->limpar($tenant);
            }
        } catch (Throwable $e) {
            Log::warning('Intelligence: exemplo não gerado/limpo.', ['trial_id' => $trial->id, 'erro' => $e->getMessage()]);
        }
    }
}
