<?php

namespace App\Observers;

use App\Models\AccountReceivable;
use App\Services\AsaasService;
use Illuminate\Support\Facades\Log;

class AccountReceivableObserver
{
    private AsaasService $asaasService;

    public function __construct(AsaasService $asaasService)
    {
        $this->asaasService = $asaasService;
    }

    /**
     * Quando AccountReceivable é criada, sincroniza com Asaas automaticamente
     * se o tenant tem Asaas configurado
     */
    public function created(AccountReceivable $receivable): void
    {
        if (!$receivable->tenant?->asaas_customer_id) {
            return;
        }

        try {
            $this->asaasService->createPayment($receivable);
        } catch (\Throwable $e) {
            Log::warning('AccountReceivableObserver: erro ao sincronizar com Asaas', [
                'receivable_id' => $receivable->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Quando status é atualizado manualmente, sincroniza mudanças com Asaas
     */
    public function updated(AccountReceivable $receivable): void
    {
        if (!$receivable->asaas_payment_id || !$receivable->isDirty('status')) {
            return;
        }

        try {
            $this->asaasService->syncPaymentStatus($receivable);
        } catch (\Throwable $e) {
            Log::warning('AccountReceivableObserver: erro ao sincronizar status', [
                'receivable_id' => $receivable->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Quando AccountReceivable é deletada, reembolsa no Asaas
     */
    public function deleting(AccountReceivable $receivable): void
    {
        if (!$receivable->asaas_payment_id) {
            return;
        }

        try {
            $this->asaasService->refundPayment($receivable);
        } catch (\Throwable $e) {
            Log::warning('AccountReceivableObserver: erro ao reembolsar', [
                'receivable_id' => $receivable->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
