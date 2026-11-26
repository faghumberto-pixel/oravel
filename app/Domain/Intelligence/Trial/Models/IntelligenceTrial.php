<?php

namespace App\Domain\Intelligence\Trial\Models;

use App\Domain\Intelligence\Trial\Enums\StatusConta;
use App\Models\Concerns\BelongsToTenant;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Conta de teste grátis do Intelligence (1 por tenant, 1 por WhatsApp). `status` guarda o último estado persistido;
 * o que depende de data (trial_expiring, expired) sai sempre de statusAtual().
 */
class IntelligenceTrial extends Model
{
    use BelongsToTenant;
    use HasUuids;

    protected $table = 'intelligence_trials';

    protected $fillable = [
        'tenant_id', 'user_id', 'nome', 'empresa', 'whatsapp', 'access_token_hash', 'access_token_at', 'status',
        'trial_started_at', 'trial_ends_at', 'last_upload_at', 'extensions', 'plano_nome', 'plano_preco', 'plano_chave', 'plano_proximo_chave', 'plano_proximo_em', 'exemplo_oferecido_em', 'exemplo_suprimentos_oferecido_em', 'contracted_at',
        'billing_starts_on', 'canceled_at', 'access_until', 'overdue_since', 'asaas_customer_id', 'asaas_subscription_id',
        'terms_accepted_at', 'signup_ip', 'signup_user_agent', 'objetivo',
    ];

    /** Perguntas da etapa 1 do cadastro da landing (chave => o que a pessoa quer descobrir). */
    public const OBJETIVOS = [
        'geral' => 'Minha locadora está gerando o resultado que deveria?',
        'rentabilidade' => 'Onde posso melhorar minha rentabilidade?',
        'frota' => 'Tenho equipamentos parados ou improdutivos?',
        'custos' => 'Como posso reduzir custos?',
    ];

    public function objetivoRotulo(): ?string
    {
        return self::OBJETIVOS[$this->objetivo] ?? null;
    }

    protected $casts = [
        'status' => StatusConta::class,
        'access_token_at' => 'datetime',
        'trial_started_at' => 'datetime',
        'trial_ends_at' => 'datetime',
        'last_upload_at' => 'datetime',
        'extensions' => 'array',
        'plano_preco' => 'decimal:2',
        'contracted_at' => 'datetime',
        'billing_starts_on' => 'date',
        'plano_proximo_em' => 'date',
        'exemplo_oferecido_em' => 'datetime',
        'exemplo_suprimentos_oferecido_em' => 'datetime',
        'canceled_at' => 'datetime',
        'access_until' => 'date',
        'overdue_since' => 'datetime',
        'terms_accepted_at' => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class)->withoutGlobalScopes();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withoutGlobalScopes();
    }

    public function lembretes(): HasMany
    {
        return $this->hasMany(IntelligenceTrialLembrete::class, 'trial_id');
    }

    public function contratos(): HasMany
    {
        return $this->hasMany(IntelligenceContrato::class, 'trial_id');
    }

    public function contratou(): bool
    {
        return $this->contracted_at !== null;
    }

    public function diasRestantes(): ?int
    {
        if (! $this->trial_ends_at) {
            return null;
        }

        return max(0, (int) ceil(now()->diffInSeconds($this->trial_ends_at, false) / 86400));
    }

    public function statusAtual(): StatusConta
    {
        if ($this->canceled_at) {
            return StatusConta::Canceled;
        }
        if ($this->overdue_since) {
            return StatusConta::Overdue;
        }
        if ($this->contratou() && $this->status === StatusConta::Active) {
            return StatusConta::Active;
        }
        $fim = $this->trial_ends_at;
        if ($fim === null) {
            return StatusConta::Trial; // ainda não enviou a 1ª planilha
        }
        if ($fim->isPast()) {
            // Já contratou e a 1ª cobrança ainda não foi confirmada: segue usando; o atraso (webhook) é quem trava depois.
            return $this->contratou() ? StatusConta::Trial : StatusConta::Expired;
        }

        return $this->diasRestantes() <= (int) config('intelligence_trial.aviso_dias') ? StatusConta::TrialExpiring : StatusConta::Trial;
    }

    /** Histórico e painéis seguem visíveis; só o envio de planilha novo é bloqueado. */
    public function somenteLeitura(): bool
    {
        return match ($this->statusAtual()) {
            StatusConta::Expired => true,
            StatusConta::Overdue => $this->overdue_since->copy()->addDays((int) config('intelligence_trial.carencia_dias'))->isPast(),
            StatusConta::Canceled => $this->access_until === null || $this->access_until->lt(today()),
            default => false,
        };
    }

    /** Persiste o status atual (nos eventos e no comando diário), para filtros e relatórios. */
    public function sincronizarStatus(): StatusConta
    {
        $atual = $this->statusAtual();
        if ($this->status !== $atual) {
            $this->forceFill(['status' => $atual])->save();
        }

        return $atual;
    }
}
