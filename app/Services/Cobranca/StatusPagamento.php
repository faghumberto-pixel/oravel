<?php

namespace App\Services\Cobranca;

use App\Models\AccountReceivable;

/** Traduz o status/evento do Asaas para a conta a receber. Compartilhado entre o webhook e a consulta direta. */
class StatusPagamento
{
    private const PAGOS = ['RECEIVED', 'CONFIRMED', 'RECEIVED_IN_CASH'];

    /** Aplica o retorno de uma cobrança do Asaas (objeto "payment") à conta. */
    public static function aplicar(AccountReceivable $conta, array $payment, ?string $evento = null): AccountReceivable
    {
        $status = strtoupper((string) ($payment['status'] ?? ''));
        $evento = strtoupper((string) $evento);

        $dados = ['asaas_status' => $status ?: $conta->asaas_status];

        if (filled($payment['invoiceUrl'] ?? null)) {
            $dados['asaas_invoice_url'] = $payment['invoiceUrl'];
        }
        if (filled($payment['bankSlipUrl'] ?? null)) {
            $dados['asaas_boleto_url'] = $payment['bankSlipUrl'];
        }

        if ($evento === 'PAYMENT_DELETED') {
            // Cobrança apagada no Asaas: libera para gerar outra, sem mexer no status financeiro.
            $dados += ['asaas_payment_id' => null, 'asaas_invoice_url' => null, 'asaas_boleto_url' => null, 'asaas_status' => 'DELETED'];
        } elseif (in_array($evento, ['PAYMENT_REFUNDED', 'PAYMENT_CHARGEBACK_REQUESTED'], true) || in_array($status, ['REFUNDED', 'CHARGEBACK_REQUESTED', 'CHARGEBACK_DISPUTE'], true)) {
            $dados += ['status' => $conta->due_date && $conta->due_date->isPast() ? 'atrasado' : 'pendente', 'payment_date' => null];
        } elseif (in_array($status, self::PAGOS, true) || in_array($evento, ['PAYMENT_RECEIVED', 'PAYMENT_CONFIRMED'], true)) {
            $dados += ['status' => 'pago', 'payment_date' => $payment['clientPaymentDate'] ?? $payment['paymentDate'] ?? $payment['confirmedDate'] ?? now()->toDateString()];
        } elseif ($status === 'OVERDUE' || $evento === 'PAYMENT_OVERDUE') {
            if ($conta->status !== 'pago') {
                $dados['status'] = 'atrasado';
            }
        }

        $conta->forceFill($dados)->save();

        return $conta;
    }
}
