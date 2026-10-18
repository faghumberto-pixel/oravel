<?php

namespace App\Support\Onboarding;

use App\Models\OnboardingInicio;
use App\Models\OnboardingProgresso;
use App\Models\Tenant;
use App\Support\Pacotes;
use Illuminate\Support\Carbon;

/**
 * Estado do onboarding de uma empresa. A conclusão de cada passo vem de um Detector (dados reais) e, uma vez registrada,
 * nunca volta atrás: apagar o cliente depois não "desfaz" o passo. Tudo isolado por empresa.
 */
class OnboardingService
{
    /** Código da solução da empresa que tem onboarding configurado (null = contrato personalizado ou solução sem onboarding). */
    public function solucaoDe(?Tenant $tenant): ?string
    {
        $codigo = $tenant ? Pacotes::doTenant($tenant) : null;

        return $codigo && config("onboarding.solucoes.{$codigo}") ? $codigo : null;
    }

    /** O início conta a partir da criação da empresa (a assinatura). */
    public function iniciar(Tenant $tenant, string $solucao): OnboardingInicio
    {
        return OnboardingInicio::withoutGlobalScopes()->firstOrCreate(
            ['tenant_id' => $tenant->id, 'solution' => $solucao],
            ['iniciado_em' => $tenant->created_at ?? now()],
        );
    }

    /** Roda os detectores dos passos ainda pendentes e grava a primeira conclusão de cada um. */
    public function sincronizar(Tenant $tenant, ?string $solucao = null): void
    {
        $solucao ??= $this->solucaoDe($tenant);

        if (! $solucao) {
            return;
        }

        $inicio = $this->iniciar($tenant, $solucao);
        $passos = config("onboarding.solucoes.{$solucao}.passos", []);
        $feitos = OnboardingProgresso::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('solution', $solucao)->whereNotNull('completed_at')->pluck('step_key')->all();

        foreach ($passos as $passo) {
            if (in_array($passo['chave'], $feitos, true)) {
                continue;
            }

            if ($this->detector($passo['detector'])->concluido($tenant)) {
                OnboardingProgresso::withoutGlobalScopes()->updateOrCreate(
                    ['tenant_id' => $tenant->id, 'solution' => $solucao, 'step_key' => $passo['chave']],
                    ['completed_at' => now()],
                );
                $feitos[] = $passo['chave'];
            }
        }

        if ($inicio->concluido_em === null && count(array_intersect($feitos, array_column($passos, 'chave'))) === count($passos)) {
            $inicio->forceFill(['concluido_em' => now()])->save();
        }
    }

    /**
     * @return array{solucao: string, titulo: string, meta: string, passos: list<array<string, mixed>>, total: int, concluidos: int, percentual: int, atual: ?int,
     *     minutos_restantes: int, completo: bool, dispensado: bool, iniciado_em: Carbon, primeiro_acesso_em: mixed}|null
     */
    public function estado(Tenant $tenant): ?array
    {
        $solucao = $this->solucaoDe($tenant);

        if (! $solucao) {
            return null;
        }

        $this->sincronizar($tenant, $solucao);

        $cfg = config("onboarding.solucoes.{$solucao}");
        $feitos = OnboardingProgresso::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('solution', $solucao)->whereNotNull('completed_at')->pluck('completed_at', 'step_key');
        $inicio = $this->iniciar($tenant, $solucao);

        $passos = [];
        $atual = null;
        $minutos = 0;

        foreach ($cfg['passos'] as $i => $passo) {
            $concluido = $feitos->has($passo['chave']);

            if (! $concluido) {
                $atual ??= $i;
                $minutos += (int) $passo['minutos'];
            }

            $passos[] = $passo + ['numero' => $i + 1, 'concluido' => $concluido, 'concluido_em' => $feitos->get($passo['chave'])];
        }

        $total = count($passos);
        $concluidos = $feitos->count();

        return [
            'solucao' => $solucao, 'titulo' => $cfg['titulo'], 'meta' => $cfg['meta'], 'passos' => $passos, 'total' => $total, 'concluidos' => $concluidos,
            'percentual' => $total ? (int) round($concluidos / $total * 100) : 0, 'atual' => $atual, 'minutos_restantes' => $minutos, 'completo' => $concluidos >= $total,
            'dispensado' => $inicio->dispensado_em !== null, 'iniciado_em' => $inicio->iniciado_em, 'primeiro_acesso_em' => $inicio->primeiro_acesso_em,
        ];
    }

    /** Há onboarding para mostrar: configurado, incompleto e não dispensado. */
    public function pendente(?Tenant $tenant): bool
    {
        $estado = $tenant ? $this->estado($tenant) : null;

        return $estado !== null && ! $estado['completo'] && ! $estado['dispensado'];
    }

    public function registrarPrimeiroAcesso(Tenant $tenant): void
    {
        if ($solucao = $this->solucaoDe($tenant)) {
            $inicio = $this->iniciar($tenant, $solucao);

            if ($inicio->primeiro_acesso_em === null) {
                $inicio->forceFill(['primeiro_acesso_em' => now()])->save();
            }
        }
    }

    public function dispensar(Tenant $tenant): void
    {
        if ($solucao = $this->solucaoDe($tenant)) {
            $this->iniciar($tenant, $solucao)->forceFill(['dispensado_em' => now()])->save();
        }
    }

    private function detector(string $chave): Detector
    {
        $classe = config("onboarding.detectores.{$chave}") ?? throw new \InvalidArgumentException("Detector de onboarding desconhecido: {$chave}");

        return app($classe);
    }
}
