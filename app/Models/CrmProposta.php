<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasSaaSMetadata;
use App\Services\CrmFunilService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Proposta comercial do CRM: Conta/Lead + itens (catálogo ou livres), PDF, envio e aceite online. Independente
 * das propostas do ERP (PropostaComercial), que exigem Cliente e Ativos.
 */
class CrmProposta extends Model
{
    use BelongsToTenant;
    use HasSaaSMetadata;
    use HasUuids;

    public const STATUS_RASCUNHO = 'rascunho';

    public const STATUS_ENVIADA = 'enviada';

    public const STATUS_ACEITA = 'aceita';

    public const STATUS_RECUSADA = 'recusada';

    public const STATUS_EXPIRADA = 'expirada';

    protected $table = 'crm_propostas';

    protected static ?string $saasFeatureKey = 'tabela_crm_propostas';

    protected static ?string $saasPermissionSlug = 'crm_proposta';

    protected static ?string $saasModuleLabel = 'CRM - Propostas';

    protected $attributes = ['status' => self::STATUS_RASCUNHO, 'desconto' => 0, 'total' => 0];

    protected $fillable = [
        'tenant_id', 'numero', 'crm_lead_id', 'crm_conta_id', 'vendedor_user_id', 'titulo', 'validade_ate', 'condicoes', 'observacoes',
        'desconto', 'total', 'status', 'token', 'enviada_em', 'visualizada_em', 'respondida_em', 'motivo_recusa', 'nome_aceite', 'ip_aceite',
    ];

    protected $casts = [
        'validade_ate' => 'date', 'desconto' => 'decimal:2', 'total' => 'decimal:2', 'numero' => 'integer',
        'enviada_em' => 'datetime', 'visualizada_em' => 'datetime', 'respondida_em' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (CrmProposta $p) {
            $p->token ??= Str::random(48);
            $p->numero ??= ((int) static::withoutGlobalScopes()->where('tenant_id', $p->tenant_id)->max('numero')) + 1;
            $p->vendedor_user_id ??= auth()->id();
        });

        // Mudou o desconto: o total acompanha.
        static::saved(function (CrmProposta $p) {
            if ($p->wasChanged('desconto')) {
                $p->recalcularTotal();
            }
        });
    }

    public static function statusLabels(): array
    {
        return [
            self::STATUS_RASCUNHO => 'Rascunho', self::STATUS_ENVIADA => 'Enviada', self::STATUS_ACEITA => 'Aceita',
            self::STATUS_RECUSADA => 'Recusada', self::STATUS_EXPIRADA => 'Expirada',
        ];
    }

    public function itens(): HasMany
    {
        return $this->hasMany(CrmPropostaItem::class, 'crm_proposta_id');
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(CrmLead::class, 'crm_lead_id');
    }

    public function conta(): BelongsTo
    {
        return $this->belongsTo(CrmConta::class, 'crm_conta_id');
    }

    public function vendedor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'vendedor_user_id');
    }

    public function titulo(): string
    {
        return "Proposta nº {$this->numero}".($this->titulo ? " — {$this->titulo}" : '');
    }

    public function destinatarioNome(): string
    {
        return $this->conta?->nome ?? $this->lead?->company_name ?? $this->lead?->name ?? 'Cliente';
    }

    public function destinatarioEmail(): ?string
    {
        return $this->conta?->email ?: $this->lead?->email;
    }

    public function destinatarioTelefone(): ?string
    {
        return $this->conta?->whatsapp ?: $this->conta?->telefone ?: $this->lead?->whatsapp ?: $this->lead?->phone;
    }

    public function urlPublica(): string
    {
        return route('crm.proposta.publica', $this->token);
    }

    public function estaVencida(): bool
    {
        return $this->validade_ate !== null && $this->validade_ate->endOfDay()->isPast();
    }

    public function editavel(): bool
    {
        return $this->status === self::STATUS_RASCUNHO;
    }

    /** Soma dos itens menos o desconto (nunca negativo). */
    public function recalcularTotal(): void
    {
        $soma = (float) $this->itens()->sum('subtotal');
        $this->forceFill(['total' => max(0, round($soma - (float) $this->desconto, 2))])->saveQuietly();
    }

    /** Copia conta/lead e os itens de interesse do lead para uma proposta nova. */
    public static function doLead(CrmLead $lead, ?User $vendedor = null): self
    {
        $proposta = static::create([
            'tenant_id' => $lead->tenant_id,
            'crm_lead_id' => $lead->id,
            'crm_conta_id' => $lead->crm_conta_id,
            'vendedor_user_id' => $vendedor?->id ?? $lead->assigned_user_id ?? auth()->id(),
            'titulo' => $lead->company_name ?: $lead->name,
            'validade_ate' => now()->addDays(15),
        ]);

        foreach ($lead->itens as $item) {
            $proposta->itens()->create([
                'tenant_id' => $lead->tenant_id, 'crm_produto_id' => $item->crm_produto_id, 'descricao' => $item->descricao,
                'quantidade' => $item->quantidade, 'preco_unitario' => $item->preco_unitario,
            ]);
        }

        $proposta->recalcularTotal();

        return $proposta->refresh();
    }

    /** Duplica como rascunho novo (itens, condições e destinatário), com número e link próprios. */
    public function duplicar(?User $por = null): self
    {
        $copia = static::create([
            'tenant_id' => $this->tenant_id, 'crm_lead_id' => $this->crm_lead_id, 'crm_conta_id' => $this->crm_conta_id,
            'vendedor_user_id' => $por?->id ?? $this->vendedor_user_id, 'titulo' => $this->titulo, 'condicoes' => $this->condicoes,
            'observacoes' => $this->observacoes, 'desconto' => $this->desconto, 'validade_ate' => now()->addDays(15),
        ]);

        foreach ($this->itens as $item) {
            $copia->itens()->create($item->only(['tenant_id', 'crm_produto_id', 'descricao', 'quantidade', 'preco_unitario']));
        }

        $copia->recalcularTotal();

        return $copia->refresh();
    }

    public function gerarPdf(): string
    {
        @ini_set('memory_limit', '256M');

        return Pdf::loadView('pdf.crm-proposta', ['proposta' => $this->load(['itens', 'conta', 'lead', 'vendedor', 'tenant'])])->setPaper('a4')->output();
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /** @throws \RuntimeException sem itens, sem destinatário ou fora do rascunho/enviada */
    private function garantirEnviavel(): void
    {
        if (! in_array($this->status, [self::STATUS_RASCUNHO, self::STATUS_ENVIADA], true)) {
            throw new \RuntimeException('Só é possível enviar uma proposta em rascunho ou já enviada.');
        }

        if (! $this->itens()->exists()) {
            throw new \RuntimeException('Inclua ao menos um item antes de enviar.');
        }

        if ($this->estaVencida()) {
            throw new \RuntimeException('A validade desta proposta já passou: ajuste a data antes de enviar.');
        }
    }

    /** Envia o PDF e o link pela Caixa de E-mail (fica registrado) e marca como enviada. */
    public function enviarPorEmail(User $por): EmailMessage
    {
        $this->garantirEnviavel();

        if (blank($this->destinatarioEmail())) {
            throw new \RuntimeException('Defina o e-mail da conta (ou do lead) antes de enviar.');
        }

        $email = EmailMessage::create([
            'tenant_id' => $this->tenant_id,
            'from_user_id' => $por->id,
            'to_external' => [$this->destinatarioEmail()],
            'subject' => $this->titulo().' — '.$this->destinatarioNome(),
            'body' => "Segue em anexo a nossa proposta. Para ver e responder (aceitar ou recusar), acesse: {$this->urlPublica()}",
            'related_type' => self::class,
            'related_id' => $this->id,
        ]);

        $email->addMediaFromString($this->gerarPdf())->usingFileName("proposta-{$this->numero}.pdf")->toMediaCollection('anexos');
        $email->send();

        $this->marcarEnviada();

        return $email;
    }

    /** Link do WhatsApp (clicar para conversar) com a mensagem e o link já escritos; marca como enviada. Null sem número. */
    public function linkWhatsapp(): ?string
    {
        $numero = preg_replace('/\D+/', '', (string) $this->destinatarioTelefone());

        if (strlen($numero) < 10 || ! in_array($this->status, [self::STATUS_RASCUNHO, self::STATUS_ENVIADA], true)) {
            return null;
        }

        strlen($numero) <= 11 && $numero = '55'.$numero;

        $texto = "Olá, {$this->destinatarioNome()}! Segue a nossa proposta nº {$this->numero}. Para ver e responder (aceitar ou recusar), acesse: {$this->urlPublica()}";

        return 'https://wa.me/'.$numero.'?text='.rawurlencode($texto);
    }

    public function marcarEnviada(): void
    {
        if ($this->status === self::STATUS_RASCUNHO) {
            $this->update(['status' => self::STATUS_ENVIADA, 'enviada_em' => now()]);
        }
    }

    public function marcarVisualizada(): void
    {
        if (! $this->visualizada_em) {
            $this->forceFill(['visualizada_em' => now()])->saveQuietly();
        }
    }

    /** @throws \RuntimeException fora do estado "enviada" ou vencida */
    private function garantirRespondivel(): void
    {
        if ($this->status !== self::STATUS_ENVIADA) {
            throw new \RuntimeException('Esta proposta não está aguardando resposta.');
        }

        if ($this->estaVencida()) {
            $this->update(['status' => self::STATUS_EXPIRADA]);

            throw new \RuntimeException('Esta proposta expirou. Peça uma nova ao vendedor.');
        }
    }

    /** Aceite: registra quem aceitou, fecha o lead como ganho (vira conta pelo fluxo do CRM) e deixa o histórico. */
    public function aceitar(string $nome, ?string $ip = null): void
    {
        $this->garantirRespondivel();

        $this->update(['status' => self::STATUS_ACEITA, 'respondida_em' => now(), 'nome_aceite' => $nome, 'ip_aceite' => $ip]);

        $lead = $this->lead;

        if ($lead) {
            $ganho = $lead->etapa ? CrmEtapa::withoutGlobalScopes()->where('crm_funil_id', $lead->etapa->crm_funil_id)->where('tipo', CrmEtapa::TIPO_GANHO)->orderBy('ordem')->first() : null;

            // A venda fechada vale o total da proposta aceita.
            $lead->update(['estimated_value' => $this->total]);

            if ($ganho && ! $lead->isWon()) {
                app(CrmFunilService::class)->moverLead($lead, $ganho);
            }

            $this->registrarInteracao("Proposta nº {$this->numero} aceita por {$nome} (R$ ".number_format((float) $this->total, 2, ',', '.').').');
        }
    }

    public function recusar(string $motivo, ?string $ip = null): void
    {
        $this->garantirRespondivel();

        $this->update(['status' => self::STATUS_RECUSADA, 'respondida_em' => now(), 'motivo_recusa' => $motivo, 'ip_aceite' => $ip]);

        $this->lead && $this->registrarInteracao("Proposta nº {$this->numero} recusada pelo cliente. Motivo: {$motivo}");
    }

    /** Reabre uma proposta recusada ou expirada como rascunho (para ajustar e reenviar). */
    public function reabrir(?\DateTimeInterface $novaValidade = null): void
    {
        if (! in_array($this->status, [self::STATUS_RECUSADA, self::STATUS_EXPIRADA], true)) {
            throw new \RuntimeException('Só é possível reabrir uma proposta recusada ou expirada.');
        }

        $this->update([
            'status' => self::STATUS_RASCUNHO, 'respondida_em' => null, 'motivo_recusa' => null, 'nome_aceite' => null, 'ip_aceite' => null,
            'validade_ate' => $novaValidade ?? now()->addDays(15),
        ]);
    }

    /** Aceite combinado fora do sistema (telefone/assinatura em papel): registra manualmente. */
    public function marcarAceitaManualmente(string $nome): void
    {
        $this->marcarEnviada();
        $this->aceitar($nome);
    }

    private function registrarInteracao(string $resumo): void
    {
        $lead = $this->lead;

        if (! $lead || ! ($autor = $this->vendedor_user_id ?? $lead->assigned_user_id)) {
            return;
        }

        CrmLeadInteraction::create([
            'tenant_id' => $this->tenant_id, 'crm_lead_id' => $lead->id, 'user_id' => $autor,
            'channel' => CrmLeadInteraction::CHANNEL_OUTRO, 'contact_date' => now(), 'summary' => $resumo, 'stage_at_time' => $lead->fresh()->stage,
        ]);
    }
}
