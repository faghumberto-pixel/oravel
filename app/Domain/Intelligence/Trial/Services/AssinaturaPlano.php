<?php

namespace App\Domain\Intelligence\Trial\Services;

use App\Domain\Intelligence\Trial\Enums\StatusConta;
use App\Domain\Intelligence\Trial\Models\IntelligenceContrato;
use App\Domain\Intelligence\Trial\Models\IntelligenceTrial;
use App\Models\TenantEvent;
use App\Services\AsaasService;
use App\Services\TenantTimeline;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/** Contratação do plano básico: cliente + assinatura mensal no Asaas (1ª cobrança só no fim do teste) e cancelamento. */
class AssinaturaPlano
{
    public function __construct(private AsaasService $asaas, private ContratoPlano $contratos) {}

    /**
     * @param  array{razao_social: string, cnpj: string, email: string, responsavel: string, forma_pagamento: string}  $d
     *
     * @throws \DomainException mensagem pronta para o usuário
     */
    public function contratar(IntelligenceTrial $trial, array $d, ?string $ip, ?string $ua): IntelligenceContrato
    {
        if ($trial->contratou() && ! $trial->canceled_at) {
            throw new \DomainException('O plano já está contratado.');
        }
        if (blank(config('services.asaas.api_key'))) {
            Log::error('Intelligence trial: ASAAS_API_KEY não configurada.');
            throw new \DomainException('Não foi possível concluir a contratação agora. Tente novamente em alguns minutos.');
        }

        $inicio = ContratoPlano::inicioDaCobranca($trial);
        $contrato = $this->contratos->renderizar($d, $inicio);

        try {
            $customerId = $this->customer($trial, $d);
            $assinatura = $this->asaas->createSubscription([
                'customer' => $customerId,
                'billingType' => 'UNDEFINED', // o cliente escolhe Pix/boleto/cartão ao receber a cobrança
                'value' => (float) config('intelligence_trial.plano.preco'),
                'nextDueDate' => $inicio->toDateString(),
                'cycle' => config('intelligence_trial.plano.ciclo'),
                'description' => config('intelligence_trial.plano.nome'),
                'externalReference' => (string) $trial->tenant_id,
            ]);
        } catch (Throwable $e) {
            // A mensagem do Asaas pode citar dados do cliente: vai só para o log, nunca para a tela.
            Log::warning('Intelligence trial: falha ao criar cliente/assinatura no Asaas.', ['trial_id' => $trial->id, 'erro' => $e->getMessage()]);
            throw new \DomainException('Não foi possível concluir a contratação agora. Confira o CNPJ e o e-mail e tente novamente.');
        }

        return DB::transaction(function () use ($trial, $d, $customerId, $assinatura, $contrato, $inicio, $ip, $ua) {
            $registro = $this->contratos->registrarAceite($trial, $d, $contrato, $inicio, $ip, $ua);

            $trial->forceFill([
                'plano_nome' => config('intelligence_trial.plano.nome'),
                'plano_preco' => config('intelligence_trial.plano.preco'),
                'contracted_at' => now(),
                'billing_starts_on' => $inicio->toDateString(),
                'canceled_at' => null,
                'access_until' => null,
                'asaas_customer_id' => $customerId,
                'asaas_subscription_id' => $assinatura['id'] ?? null,
            ])->save();

            $trial->tenant->forceFill([
                'cpf_cnpj' => $d['cnpj'], 'razao_social' => $d['razao_social'], 'email_contato' => $d['email'],
                'asaas_customer_id' => $customerId, 'asaas_subscription_id' => $assinatura['id'] ?? null, 'asaas_status' => 'synced',
            ])->save();
            $trial->user?->forceFill(['email' => $d['email']])->save();

            $trial->sincronizarStatus();
            TenantTimeline::record($trial->tenant_id, TenantEvent::ASSINATURA_CRIADA, 'Plano básico contratado', 'Primeira cobrança em '.$inicio->format('d/m/Y').'.', dedupeKey: 'intelligence-contratou:'.($assinatura['id'] ?? $trial->id));
            Log::info('Intelligence trial: plano contratado.', ['trial_id' => $trial->id, 'primeira_cobranca' => $inicio->toDateString()]);

            return $registro;
        });
    }

    /** Cancela a assinatura no Asaas e mantém o acesso até o fim do período já pago. */
    public function cancelar(IntelligenceTrial $trial): void
    {
        if ($trial->canceled_at) {
            return;
        }

        $ate = null;
        if ($trial->asaas_subscription_id) {
            try {
                $ate = $this->asaas->getSubscription($trial->asaas_subscription_id)['nextDueDate'] ?? null;
                $this->asaas->cancelSubscription($trial->asaas_subscription_id);
            } catch (Throwable $e) {
                Log::warning('Intelligence trial: falha ao cancelar a assinatura no Asaas.', ['trial_id' => $trial->id, 'erro' => $e->getMessage()]);
                throw new \DomainException('Não foi possível cancelar agora. Tente novamente em alguns minutos.');
            }
        }

        $this->marcarCancelada($trial, $ate);
    }

    /** Estado local do cancelamento (também usado pelo webhook). Antes da 1ª cobrança, o acesso vai até o fim do teste. */
    public function marcarCancelada(IntelligenceTrial $trial, ?string $ateData = null): void
    {
        $naoCobrou = $trial->billing_starts_on && $trial->billing_starts_on->gt(today());
        $ate = $naoCobrou && $trial->trial_ends_at ? $trial->trial_ends_at->toDateString() : ($ateData ?: today()->toDateString());

        $trial->forceFill(['canceled_at' => now(), 'access_until' => $ate, 'overdue_since' => null])->save();
        $trial->forceFill(['status' => StatusConta::Canceled])->save();
        TenantTimeline::record($trial->tenant_id, TenantEvent::ANOTACAO, 'Plano cancelado', 'Acesso até '.date('d/m/Y', strtotime($ate)).'.', dedupeKey: 'intelligence-cancelou:'.$trial->id);
        Log::info('Intelligence trial: plano cancelado.', ['trial_id' => $trial->id, 'acesso_ate' => $ate]);
    }

    /** Reaproveita o cliente do Asaas pelo CNPJ; cria só se não existir. */
    private function customer(IntelligenceTrial $trial, array $d): string
    {
        $cnpj = preg_replace('/\D/', '', $d['cnpj']);
        $existente = $this->asaas->findCustomerByCpfCnpj($cnpj);
        if ($existente && filled($existente['id'] ?? null)) {
            return $existente['id'];
        }

        return $this->asaas->createCustomer(['name' => $d['razao_social'], 'cpfCnpj' => $cnpj, 'email' => $d['email'], 'mobilePhone' => preg_replace('/\D/', '', substr($trial->whatsapp, 3)), 'externalReference' => (string) $trial->tenant_id])['id'];
    }
}
