<?php

namespace App\Domain\Intelligence\Trial\Services;

use App\Domain\Intelligence\Trial\Enums\StatusConta;
use App\Domain\Intelligence\Trial\Models\IntelligenceTrial;
use App\Models\Tenant;
use App\Models\TenantEvent;
use App\Services\TenantTimeline;
use Illuminate\Support\Facades\Log;

/** Contagem dos dias, status e bloqueio de envio (somente leitura). */
class TrialCiclo
{
    public static function doTenant(Tenant|string|null $tenant): ?IntelligenceTrial
    {
        $id = $tenant instanceof Tenant ? $tenant->id : $tenant;

        return $id ? IntelligenceTrial::withoutGlobalScopes()->where('tenant_id', $id)->first() : null;
    }

    /** Começa a contar os dias (idempotente: nunca reinicia). */
    public function iniciarContagem(IntelligenceTrial $trial): void
    {
        if ($trial->trial_started_at) {
            return;
        }
        $inicio = now();
        $trial->forceFill([
            'trial_started_at' => $inicio,
            'trial_ends_at' => $inicio->copy()->addDays((int) config('intelligence_trial.dias')),
        ])->save();
        $trial->sincronizarStatus();

        TenantTimeline::record($trial->tenant_id, TenantEvent::ANOTACAO, 'Teste grátis começou a contar', 'Termina em '.$trial->trial_ends_at->format('d/m/Y').'.', dedupeKey: 'intelligence-trial-inicio');
        Log::info('Intelligence trial: contagem iniciada.', ['trial_id' => $trial->id, 'termina' => $trial->trial_ends_at->toDateString()]);
    }

    /** Chamado pelo importador após uma importação REAL com sucesso. */
    public function aoEnviarPlanilha(Tenant $tenant): void
    {
        $trial = self::doTenant($tenant);
        if (! $trial) {
            return;
        }
        $trial->forceFill(['last_upload_at' => now()])->save();
        $this->iniciarContagem($trial);
        Log::info('Intelligence trial: planilha enviada.', ['trial_id' => $trial->id]);
    }

    /** Mensagem de bloqueio para o importador, ou null se pode enviar. */
    public function motivoDoBloqueio(Tenant $tenant): ?string
    {
        $trial = self::doTenant($tenant);
        if (! $trial || ! $trial->somenteLeitura()) {
            return null;
        }

        return match ($trial->statusAtual()) {
            StatusConta::Overdue => 'Há uma cobrança em atraso. Regularize o pagamento para voltar a enviar planilhas. Seu histórico e painéis continuam disponíveis.',
            StatusConta::Canceled => 'Seu plano foi cancelado. Contrate de novo para voltar a enviar planilhas. Seu histórico e painéis continuam disponíveis.',
            default => 'Seu teste grátis terminou. Contrate o plano básico para enviar novas planilhas. Seu histórico e painéis continuam disponíveis.',
        };
    }

    /** Estende o teste (Painel interno). Registra quem, quanto e por quê. */
    public function estender(IntelligenceTrial $trial, int $dias, string $motivo, ?string $por = null): void
    {
        $base = $trial->trial_ends_at && $trial->trial_ends_at->isFuture() ? $trial->trial_ends_at : now();
        $log = $trial->extensions ?? [];
        $log[] = ['dias' => $dias, 'motivo' => $motivo, 'por' => $por, 'em' => now()->toIso8601String()];

        $trial->forceFill([
            'trial_started_at' => $trial->trial_started_at ?? now(),
            'trial_ends_at' => $base->copy()->addDays($dias),
            'extensions' => $log,
        ])->save();
        $trial->sincronizarStatus();

        TenantTimeline::record($trial->tenant_id, TenantEvent::ANOTACAO, "Teste estendido em {$dias} dias", $motivo, dedupeKey: 'intelligence-trial-ext:'.count($log));
        Log::info('Intelligence trial: teste estendido.', ['trial_id' => $trial->id, 'dias' => $dias]);
    }
}
