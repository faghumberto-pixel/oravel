<?php

namespace App\Services\Cobranca;

use App\Models\AccountReceivable;
use App\Models\BillingPlan;
use App\Models\TenantAsaasSetting;
use App\Models\User;
use Carbon\CarbonInterface;
use Filament\Notifications\Notification;
use Illuminate\Support\Carbon;

/**
 * Automação da cobrança de UMA empresa, chamada pelo agendador diário (cobranca:processar):
 *  1. gera a conta a receber de cada Plano de Cobrança ativo cujo vencimento cai na janela de antecedência (sem duplicar);
 *  2. gera no Asaas a cobrança das contas ainda sem cobrança (planos, medições faturadas, lançamentos manuais).
 * Falha de gateway nunca perde a conta: ela fica com o erro e é tentada de novo, até o limite.
 */
class GeradorDeCobrancas
{
    public const MAX_TENTATIVAS = 5;

    public function __construct(private ?CobrancaService $cobranca = null) {}

    private function cobranca(): CobrancaService
    {
        return $this->cobranca ??= app(CobrancaService::class);
    }

    private const PASSO_MESES = ['monthly' => 1, 'quarterly' => 3, 'annual' => 12];

    private const ROTULO = ['monthly' => 'Mensalidade', 'quarterly' => 'Cobrança trimestral', 'annual' => 'Cobrança anual'];

    /** @return int contas criadas */
    public function gerarRecorrentes(TenantAsaasSetting $config, ?CarbonInterface $hoje = null): int
    {
        $hoje = Carbon::parse($hoje ?? now())->startOfDay();
        $limite = $hoje->copy()->addDays((int) $config->dias_antecedencia);
        $criadas = 0;

        BillingPlan::withoutGlobalScopes()->where('tenant_id', $config->tenant_id)->where('active', true)->whereNotNull('client_id')
            ->with('contract')->get()
            ->each(function (BillingPlan $plano) use ($hoje, $limite, &$criadas) {
                foreach ($this->vencimentosNaJanela($plano, $hoje, $limite) as $vencimento) {
                    if ($this->jaGerada($plano, $vencimento)) {
                        continue;
                    }

                    AccountReceivable::withoutGlobalScopes()->create([
                        'tenant_id' => $plano->tenant_id, 'client_id' => $plano->client_id, 'contract_id' => $plano->contract_id,
                        'billing_plan_id' => $plano->id, 'status' => 'pendente', 'amount' => $plano->amount, 'due_date' => $vencimento,
                        'description' => (self::ROTULO[$plano->frequency] ?? 'Cobrança').' '.($plano->contract?->contract_number ? '#'.$plano->contract->contract_number.' ' : '').'— '.$vencimento->format('m/Y'),
                    ]);
                    $criadas++;
                }
            });

        return $criadas;
    }

    /** @return list<Carbon> vencimentos do plano dentro de [hoje, limite] */
    private function vencimentosNaJanela(BillingPlan $plano, Carbon $hoje, Carbon $limite): array
    {
        $contrato = $plano->contract;

        // Contrato que ainda não vale (rascunho) ou já acabou não gera cobrança nova.
        if ($contrato && in_array($contrato->status, ['Draft', 'Encerrado'], true)) {
            return [];
        }

        $passo = self::PASSO_MESES[$plano->frequency] ?? 1;
        $ancora = Carbon::parse($contrato?->start_date ?? $plano->created_at)->startOfMonth();
        $vencimentos = [];

        for ($mes = $hoje->copy()->startOfMonth(); $mes->lte($limite); $mes->addMonthNoOverflow()) {
            $dia = min(max((int) $plano->due_day, 1), $mes->daysInMonth);
            $vencimento = $mes->copy()->day($dia)->startOfDay();

            if ($vencimento->lt($hoje) || $vencimento->gt($limite) || $vencimento->lt($ancora)) {
                continue;
            }

            if ($contrato?->end_date && $vencimento->gt(Carbon::parse($contrato->end_date))) {
                continue;
            }

            if (((int) round(abs($ancora->diffInMonths($vencimento->copy()->startOfMonth()))) % $passo) === 0) {
                $vencimentos[] = $vencimento;
            }
        }

        return $vencimentos;
    }

    private function jaGerada(BillingPlan $plano, Carbon $vencimento): bool
    {
        return AccountReceivable::withoutGlobalScopes()->where('billing_plan_id', $plano->id)
            ->whereBetween('due_date', [$vencimento->copy()->startOfMonth()->toDateString(), $vencimento->copy()->endOfMonth()->toDateString()])->exists();
    }

    /** @return array{enviadas: int, falhas: int} */
    public function enviarPendentes(TenantAsaasSetting $config, ?CarbonInterface $hoje = null, int $limite = 200): array
    {
        $hoje = Carbon::parse($hoje ?? now())->startOfDay();
        $resultado = ['enviadas' => 0, 'falhas' => 0];

        AccountReceivable::withoutGlobalScopes()->where('tenant_id', $config->tenant_id)
            ->whereIn('status', ['pendente', 'atrasado'])->whereNull('asaas_payment_id')->whereNotNull('client_id')
            ->where('amount', '>', 0)->where('cobranca_tentativas', '<', self::MAX_TENTATIVAS)
            ->whereDate('due_date', '<=', $hoje->copy()->addDays((int) $config->dias_antecedencia)->toDateString())
            // Não insiste na mesma conta mais de uma vez por hora (execuções manuais seguidas).
            ->where(fn ($q) => $q->whereNull('cobranca_ultima_tentativa_em')->orWhere('cobranca_ultima_tentativa_em', '<', now()->subHour()))
            ->orderBy('due_date')->limit($limite)->get()
            ->each(function (AccountReceivable $conta) use (&$resultado) {
                try {
                    $this->cobranca()->gerar($conta);
                    $resultado['enviadas']++;
                } catch (\Throwable $e) {
                    $this->registrarFalha($conta, $e);
                    $resultado['falhas']++;
                }
            });

        return $resultado;
    }

    private function registrarFalha(AccountReceivable $conta, \Throwable $e): void
    {
        if (! $e instanceof CobrancaException) {
            report($e);
        }

        $tentativas = (int) $conta->cobranca_tentativas + 1;

        $conta->forceFill([
            'cobranca_tentativas' => $tentativas,
            'cobranca_erro' => mb_substr($e instanceof CobrancaException ? $e->getMessage() : 'Falha ao falar com o Asaas.', 0, 500),
            'cobranca_ultima_tentativa_em' => now(),
        ])->save();

        if ($tentativas === self::MAX_TENTATIVAS) {
            $this->avisarAdministradores($conta);
        }
    }

    private function avisarAdministradores(AccountReceivable $conta): void
    {
        $admins = User::withoutGlobalScopes()->where('tenant_id', $conta->tenant_id)->where('is_approved', true)->get()->filter(fn (User $u) => $u->isAdmin());

        if ($admins->isNotEmpty()) {
            Notification::make()
                ->title('Cobrança automática falhou')
                ->body("Não foi possível gerar a cobrança de \"{$conta->description}\": {$conta->cobranca_erro} Corrija e use \"Gerar cobrança\" na conta a receber.")
                ->danger()->sendToDatabase($admins);
        }
    }
}
