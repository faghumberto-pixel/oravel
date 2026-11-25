<?php

namespace App\Domain\Intelligence\Trial\Services;

use App\Domain\Intelligence\Trial\Enums\StatusConta;
use App\Domain\Intelligence\Trial\Models\IntelligenceContrato;
use App\Domain\Intelligence\Trial\Models\IntelligenceTrial;
use App\Domain\Intelligence\Trial\Support\Planos;
use App\Models\TenantEvent;
use App\Services\AsaasService;
use App\Services\TenantTimeline;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/** Contratação de um degrau de plano (basico, standard, premium), troca entre degraus e cancelamento: cliente + assinatura mensal no Asaas (1ª cobrança só no fim do teste) e cancelamento. */
class AssinaturaPlano
{
    public function __construct(private AsaasService $asaas, private ContratoPlano $contratos) {}

    /**
     * @param  array{razao_social: string, cnpj: string, email: string, responsavel: string, forma_pagamento: string}  $d
     *
     * @throws \DomainException mensagem pronta para o usuário
     */
    public function contratar(IntelligenceTrial $trial, array $d, ?string $ip, ?string $ua, string $planoChave = 'basico'): IntelligenceContrato
    {
        $plano = Planos::achar($planoChave) ?? throw new \DomainException('Plano inválido.');
        if (! $plano['contratavel']) {
            throw new \DomainException('Este plano ainda não está disponível para contratação.');
        }
        if ($trial->contratou() && ! $trial->canceled_at) {
            throw new \DomainException('O plano já está contratado.');
        }
        if (blank(config('services.asaas.api_key'))) {
            Log::error('Intelligence trial: ASAAS_API_KEY não configurada.');
            throw new \DomainException('Não foi possível concluir a contratação agora. Tente novamente em alguns minutos.');
        }

        $inicio = ContratoPlano::inicioDaCobranca($trial);
        $contrato = $this->contratos->renderizar($d, $inicio, $plano);

        try {
            $customerId = $this->customer($trial, $d);
            $assinatura = $this->asaas->createSubscription([
                'customer' => $customerId,
                'billingType' => 'UNDEFINED', // o cliente escolhe Pix/boleto/cartão ao receber a cobrança
                'value' => $plano['preco'],
                'nextDueDate' => $inicio->toDateString(),
                'cycle' => $plano['ciclo'],
                'description' => $plano['nome'],
                'externalReference' => (string) $trial->tenant_id,
            ]);
        } catch (Throwable $e) {
            // A mensagem do Asaas pode citar dados do cliente: vai só para o log, nunca para a tela.
            Log::warning('Intelligence trial: falha ao criar cliente/assinatura no Asaas.', ['trial_id' => $trial->id, 'erro' => $e->getMessage()]);
            throw new \DomainException('Não foi possível concluir a contratação agora. Confira o CNPJ e o e-mail e tente novamente.');
        }

        return DB::transaction(function () use ($trial, $d, $customerId, $assinatura, $contrato, $inicio, $ip, $ua, $plano) {
            $registro = $this->contratos->registrarAceite($trial, $d, $contrato, $inicio, $ip, $ua, $plano['preco']);

            $trial->forceFill([
                'plano_nome' => $plano['nome'],
                'plano_preco' => $plano['preco'],
                'plano_chave' => $plano['chave'],
                'plano_proximo_chave' => null,
                'plano_proximo_em' => null,
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

            app(TrialCiclo::class)->iniciarContagem($trial); // contratou sem ter enviado planilha: o teste começa a contar agora
            $trial->sincronizarStatus();
            TenantTimeline::record($trial->tenant_id, TenantEvent::ASSINATURA_CRIADA, 'Plano '.$plano['nome'].' contratado', 'Primeira cobrança em '.$inicio->format('d/m/Y').'.', dedupeKey: 'intelligence-contratou:'.($assinatura['id'] ?? $trial->id));
            Log::info('Intelligence trial: plano contratado.', ['trial_id' => $trial->id, 'primeira_cobranca' => $inicio->toDateString()]);

            return $registro;
        });
    }

    /**
     * Troca de degrau. Subir vale na hora (cota e módulos novos já hoje). Descer vale a partir da próxima cobrança, para o cliente
     * não perder o que já pagou; se a cobrança ainda não começou, vale na hora. Em ambos os casos o valor da assinatura no Asaas
     * muda sem cobrar diferença no meio do ciclo (updatePendingPayments=false: só vale para cobranças ainda não geradas).
     *
     * @return string mensagem pronta para o cliente
     *
     * @throws \DomainException
     */
    public function trocarPlano(IntelligenceTrial $trial, string $chaveNova): string
    {
        $novo = Planos::achar($chaveNova) ?? throw new \DomainException('Plano inválido.');
        if (! $trial->contratou() || $trial->canceled_at) {
            throw new \DomainException('Contrate um plano antes de trocar.');
        }
        if (! $novo['contratavel']) {
            throw new \DomainException('Este plano ainda não está disponível para contratação.');
        }
        $atual = Planos::efetivo($trial);
        if ($atual['chave'] === $novo['chave']) {
            throw new \DomainException('Você já está neste plano.');
        }

        $subindo = $novo['nivel'] > $atual['nivel'];
        $naoCobrou = $trial->billing_starts_on && $trial->billing_starts_on->gt(today());
        $vigoraAgora = $subindo || $naoCobrou;
        $vencimento = $naoCobrou ? $trial->billing_starts_on->toDateString() : null;

        if ($trial->asaas_subscription_id) {
            try {
                if (! $vigoraAgora) {
                    $vencimento = $this->asaas->getSubscription($trial->asaas_subscription_id)['nextDueDate'] ?? null;
                }
                $this->asaas->updateSubscription($trial->asaas_subscription_id, ['value' => $novo['preco'], 'description' => $novo['nome'], 'updatePendingPayments' => false]);
            } catch (Throwable $e) {
                Log::warning('Intelligence trial: falha ao trocar o valor da assinatura no Asaas.', ['trial_id' => $trial->id, 'erro' => $e->getMessage()]);
                throw new \DomainException('Não foi possível trocar de plano agora. Tente novamente em alguns minutos.');
            }
        }

        if ($vigoraAgora) {
            $trial->forceFill(['plano_chave' => $novo['chave'], 'plano_nome' => $novo['nome'], 'plano_preco' => $novo['preco'], 'plano_proximo_chave' => null, 'plano_proximo_em' => null])->save();
            TenantTimeline::record($trial->tenant_id, TenantEvent::ANOTACAO, 'Plano alterado para '.$novo['nome'], ($subindo ? 'Troca para um plano maior' : 'Troca antes da primeira cobrança').', vale desde já.', dedupeKey: 'intelligence-troca:'.$trial->id.':'.now()->timestamp);
            Log::info('Intelligence trial: plano trocado.', ['trial_id' => $trial->id, 'de' => $atual['chave'], 'para' => $novo['chave']]);

            return 'Plano alterado para '.$novo['nome'].'. Já vale desde agora.';
        }

        $em = $vencimento ?: today()->addMonth()->toDateString();
        $trial->forceFill(['plano_proximo_chave' => $novo['chave'], 'plano_proximo_em' => $em])->save();
        TenantTimeline::record($trial->tenant_id, TenantEvent::ANOTACAO, 'Troca agendada para '.$novo['nome'], 'Vale a partir de '.date('d/m/Y', strtotime($em)).'.', dedupeKey: 'intelligence-troca:'.$trial->id.':'.now()->timestamp);
        Log::info('Intelligence trial: troca de plano agendada.', ['trial_id' => $trial->id, 'para' => $novo['chave'], 'em' => $em]);

        return 'Troca para '.$novo['nome'].' agendada para '.date('d/m/Y', strtotime($em)).'. Até lá você mantém o plano atual.';
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
