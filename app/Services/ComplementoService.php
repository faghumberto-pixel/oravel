<?php

namespace App\Services;

use App\Models\Plan;
use App\Models\Tenant;
use App\Models\TenantComplemento;
use App\Models\TenantEvent;
use App\Models\User;
use App\Support\Pacotes;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Complementos cobrados pela Oravel DENTRO da assinatura recorrente do cliente (nunca avulsos), com fidelidade de 12 meses:
 *  - contratar: contrato próprio (copia da vitrine, se compartilhado) + módulo ligado + valor somado na mensalidade;
 *  - cancelar: bloqueado dentro da fidelidade; depois dela, vale no próximo ciclo;
 *  - encerramento antecipado: só super admin, com motivo, sem cobrar o saldo.
 * tenants.mrr_value é o TOTAL mensal; tenants.mrr_base, a parte sem complementos.
 */
class ComplementoService
{
    public function __construct(private ?AsaasService $asaas = null) {}

    private function asaas(): AsaasService
    {
        return $this->asaas ??= app(AsaasService::class);
    }

    /** @return array<string, string> módulo => nome */
    public static function catalogo(): array
    {
        return (array) config('complementos.catalogo', []);
    }

    public static function fidelidadeMeses(): int
    {
        return (int) config('complementos.fidelidade_meses', 12);
    }

    // ------------------------------------------------------------------ contratar

    public function contratar(Tenant $tenant, string $feature, float $valor, ?User $por = null, ?CarbonInterface $inicio = null): TenantComplemento
    {
        if (! array_key_exists($feature, static::catalogo())) {
            throw new ComplementoException('Complemento desconhecido.');
        }

        if ($valor <= 0) {
            throw new ComplementoException('Informe o valor mensal do complemento (maior que zero).');
        }

        if (blank($tenant->asaas_subscription_id) && blank($tenant->asaas_checkout_id)) {
            throw new ComplementoException('O complemento é cobrado na assinatura recorrente do cliente, e este cliente ainda não tem assinatura mensal ativa.');
        }

        if (TenantComplemento::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('feature', $feature)->whereIn('status', [TenantComplemento::ATIVO, TenantComplemento::ENCERRANDO])->exists()) {
            throw new ComplementoException('Este cliente já tem este complemento contratado.');
        }

        $inicio = Carbon::parse($inicio ?? now())->startOfDay();

        $complemento = DB::transaction(function () use ($tenant, $feature, $valor, $inicio) {
            $plano = $this->contratoProprio($tenant);
            $plano->update(['features' => array_merge($plano->features, [$feature => true])]);

            return TenantComplemento::withoutGlobalScopes()->create([
                'tenant_id' => $tenant->id, 'feature' => $feature, 'valor_mensal' => round($valor, 2), 'contratado_em' => $inicio,
                'fidelidade_meses' => static::fidelidadeMeses(), 'fidelidade_ate' => $inicio->copy()->addMonthsNoOverflow(static::fidelidadeMeses()), 'status' => TenantComplemento::ATIVO,
            ]);
        });

        $this->recalcular($tenant->refresh());
        $this->enviarValor($tenant->refresh());

        TenantTimeline::record(
            $tenant, TenantEvent::COMPLEMENTO_CONTRATADO, 'Complemento contratado: '.$complemento->nome(),
            'R$ '.number_format($valor, 2, ',', '.').'/mês na assinatura recorrente, fidelidade até '.$complemento->fidelidade_ate->format('d/m/Y').'.',
            ['complemento_id' => $complemento->id, 'feature' => $feature, 'valor' => $valor], notify: true, level: 'success',
        );

        return $complemento;
    }

    /** Contrato exclusivo do cliente: a vitrine (ou qualquer contrato usado por mais de um cliente) é copiada antes de mexer nos módulos. */
    public function contratoProprio(Tenant $tenant): Plan
    {
        $plano = Plan::withoutGlobalScopes()->findOrFail($tenant->plan_id ?? throw new ComplementoException('O cliente não tem contrato.'));

        $compartilhado = $plano->campaign_tag === Pacotes::TAG_VITRINE
            || Tenant::withoutGlobalScopes()->where('plan_id', $plano->id)->where('id', '!=', $tenant->id)->exists();

        if (! $compartilhado) {
            return $plano;
        }

        $copia = $plano->replicate();
        $copia->name = 'Contrato '.$tenant->name;
        $copia->campaign_tag = null;
        $copia->is_active = true;
        $copia->save();

        $tenant->update(['plan_id' => $copia->id]);

        return $copia;
    }

    // ------------------------------------------------------------------ cancelar / encerrar

    /** Cancelamento pedido pelo cliente/operador: só depois da fidelidade; vale no próximo ciclo da assinatura. */
    public function cancelar(TenantComplemento $c, ?User $por = null): TenantComplemento
    {
        if ($c->status !== TenantComplemento::ATIVO) {
            throw new ComplementoException('Este complemento não está ativo.');
        }

        if ($c->emFidelidade()) {
            throw new ComplementoException('O complemento tem fidelidade de '.$c->fidelidade_meses.' meses e só pode ser cancelado a partir de '.$c->fidelidade_ate->format('d/m/Y').'.');
        }

        $quando = $this->proximoCiclo($c);
        $c->update(['status' => TenantComplemento::ENCERRANDO, 'encerra_em' => $quando, 'encerrado_por' => $por?->id]);

        TenantTimeline::record(
            $c->tenant_id, TenantEvent::COMPLEMENTO_CANCELAMENTO_AGENDADO, 'Cancelamento agendado: '.$c->nome(),
            'Vale a partir de '.$quando->format('d/m/Y').' (próximo ciclo); até lá segue cobrado.', ['complemento_id' => $c->id], notify: true, level: 'warning',
        );

        return $c;
    }

    /** Encerramento antes da fidelidade: só super admin, com motivo, e SEM cobrar o saldo. */
    public function encerrarAntecipado(TenantComplemento $c, User $por, string $motivo): TenantComplemento
    {
        if (! $por->isSuperAdmin()) {
            throw new ComplementoException('Só a Oravel pode encerrar um complemento antes da fidelidade.');
        }

        if (blank(trim($motivo))) {
            throw new ComplementoException('Informe o motivo do encerramento antecipado.');
        }

        if (! $c->vigente()) {
            throw new ComplementoException('Este complemento já está encerrado.');
        }

        $this->finalizar($c, $por, trim($motivo), antecipado: true);

        return $c;
    }

    /** Aplica os cancelamentos agendados que chegaram na data. @return int quantos foram encerrados */
    public function aplicarEncerramentos(?CarbonInterface $hoje = null): int
    {
        $hoje = Carbon::parse($hoje ?? now())->startOfDay();
        $total = 0;

        TenantComplemento::withoutGlobalScopes()->where('status', TenantComplemento::ENCERRANDO)->whereDate('encerra_em', '<=', $hoje->toDateString())->get()
            ->each(function (TenantComplemento $c) use (&$total) {
                $this->finalizar($c, null, 'Cancelamento pedido após a fidelidade.', antecipado: false);
                $total++;
            });

        return $total;
    }

    private function finalizar(TenantComplemento $c, ?User $por, string $motivo, bool $antecipado): void
    {
        $tenant = Tenant::withoutGlobalScopes()->findOrFail($c->tenant_id);

        DB::transaction(function () use ($c, $por, $motivo, $antecipado, $tenant) {
            $c->update(['status' => TenantComplemento::ENCERRADO, 'encerrado_em' => now(), 'encerrado_por' => $por?->id ?? $c->encerrado_por, 'motivo_encerramento' => $motivo, 'encerramento_antecipado' => $antecipado]);

            if ($plano = Plan::withoutGlobalScopes()->find($tenant->plan_id)) {
                $plano->update(['features' => array_merge($plano->features, [$c->feature => false])]);
            }
        });

        $this->recalcular($tenant->refresh());
        $this->enviarValor($tenant->refresh());

        TenantTimeline::record(
            $tenant, $antecipado ? TenantEvent::COMPLEMENTO_ENCERRADO_ANTECIPADO : TenantEvent::COMPLEMENTO_ENCERRADO,
            ($antecipado ? 'Complemento encerrado antes da fidelidade: ' : 'Complemento encerrado: ').$c->nome(), $motivo,
            ['complemento_id' => $c->id, 'antecipado' => $antecipado], notify: true, level: $antecipado ? 'danger' : 'info',
        );
    }

    /** Próximo "aniversário" mensal da contratação (ciclo da assinatura) a partir de hoje. */
    private function proximoCiclo(TenantComplemento $c): Carbon
    {
        $dia = $c->contratado_em->day;
        $candidato = now()->startOfDay()->day(1);
        $candidato = $candidato->copy()->day(min($dia, $candidato->daysInMonth));

        if ($candidato->lte(now()->startOfDay())) {
            $candidato = $candidato->copy()->addMonthNoOverflow()->day(1);
            $candidato->day(min($dia, $candidato->daysInMonth));
        }

        return $candidato;
    }

    // ------------------------------------------------------------------ valor da mensalidade

    /** mrr_value = base + complementos vigentes. Marca o valor como "a enviar ao Asaas". */
    public function recalcular(Tenant $tenant): void
    {
        $base = (float) ($tenant->mrr_base ?? $tenant->mrr_value);
        $total = round($base + Tenant::complementosVigentes($tenant), 2);

        Tenant::$ajusteInternoDoMrr = true;

        try {
            $tenant->forceFill(['mrr_base' => $base, 'mrr_value' => $total, 'mrr_pendente_sincronizar' => true, 'mrr_pendente_desde' => $tenant->mrr_pendente_desde ?? now()])->save();
        } finally {
            Tenant::$ajusteInternoDoMrr = false;
        }
    }

    /**
     * Envia o total à assinatura do Asaas da Oravel. Fica pendente (e o comando diário tenta de novo) se não houver assinatura
     * ainda, se a implantação somada estiver em curso (as parcelas dependem do valor de cada ciclo) ou se o Asaas falhar.
     */
    public function enviarValor(Tenant $tenant): bool
    {
        if (! $tenant->mrr_pendente_sincronizar) {
            return true;
        }

        if (blank($tenant->asaas_subscription_id) || ($tenant->isImplementationSummed() && blank($tenant->subscription_reverted_to_base_at))) {
            return false;
        }

        if (! $this->asaas()->updateSubscriptionValue($tenant->asaas_subscription_id, (float) $tenant->mrr_value)) {
            return false;
        }

        Tenant::$ajusteInternoDoMrr = true;

        try {
            $tenant->forceFill(['mrr_pendente_sincronizar' => false, 'mrr_pendente_desde' => null])->save();
        } finally {
            Tenant::$ajusteInternoDoMrr = false;
        }

        return true;
    }

    // ------------------------------------------------------------------ rotina diária e auditoria

    /** @return array{encerrados: int, enviados: int, pendentes: int, avisos: int} */
    public function rotinaDiaria(?CarbonInterface $hoje = null): array
    {
        $hoje = Carbon::parse($hoje ?? now())->startOfDay();
        $resultado = ['encerrados' => $this->aplicarEncerramentos($hoje), 'enviados' => 0, 'pendentes' => 0, 'avisos' => 0];

        Tenant::withoutGlobalScopes()->where('mrr_pendente_sincronizar', true)->get()->each(function (Tenant $t) use (&$resultado, $hoje) {
            if ($this->enviarValor($t)) {
                $resultado['enviados']++;

                return;
            }

            $resultado['pendentes']++;

            if ($t->mrr_pendente_desde && $t->mrr_pendente_desde->lt($hoje->copy()->subDays(3))) {
                TenantTimeline::record($t, TenantEvent::ANOTACAO, 'Valor da mensalidade pendente no Asaas', 'O novo valor (R$ '.number_format((float) $t->mrr_value, 2, ',', '.').') não foi enviado há mais de 3 dias. Confira a assinatura.',
                    dedupeKey: 'mrr-pendente:'.$t->id.':'.$hoje->toDateString(), notify: true, level: 'danger');
            }
        });

        TenantComplemento::withoutGlobalScopes()->where('status', TenantComplemento::ATIVO)->get()->each(function (TenantComplemento $c) use (&$resultado, $hoje) {
            if ($c->fidelidade_ate->lte($hoje)) {
                $feito = TenantTimeline::record($c->tenant_id, TenantEvent::FIDELIDADE_CONCLUIDA, 'Fidelidade concluída: '.$c->nome(), 'Segue cobrado mensalmente até o cancelamento.', dedupeKey: 'fidelidade-concluida:'.$c->id, notify: true, level: 'info');
            } elseif ($c->fidelidade_ate->lte($hoje->copy()->addDays(30))) {
                $feito = TenantTimeline::record($c->tenant_id, TenantEvent::FIDELIDADE_CONCLUINDO, 'Fidelidade termina em '.$c->fidelidade_ate->format('d/m/Y').': '.$c->nome(), 'A partir daí o cliente pode pedir o cancelamento.', dedupeKey: 'fidelidade-concluindo:'.$c->id, notify: true, level: 'warning');
            } else {
                return;
            }

            $resultado['avisos'] += $feito ? 1 : 0;
        });

        return $resultado;
    }

    /**
     * Contratos com módulo de complemento ligado sem complemento vigente (ligado "na mão").
     *
     * @return list<array{tenant: string, feature: string}>
     */
    public function auditar(): array
    {
        $problemas = [];

        Tenant::withoutGlobalScopes()->whereNotNull('plan_id')->with('plan')->get()->each(function (Tenant $t) use (&$problemas) {
            foreach (array_keys(static::catalogo()) as $feature) {
                if (($t->plan?->features[$feature] ?? false) !== true) {
                    continue;
                }

                $tem = TenantComplemento::withoutGlobalScopes()->where('tenant_id', $t->id)->where('feature', $feature)->whereIn('status', [TenantComplemento::ATIVO, TenantComplemento::ENCERRANDO])->exists();

                if (! $tem) {
                    $problemas[] = ['tenant' => $t->name, 'feature' => $feature];
                }
            }
        });

        return $problemas;
    }
}
