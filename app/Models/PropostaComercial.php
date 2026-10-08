<?php

namespace App\Models;

use App\Mail\GenericPdfMail;
use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasSaaSMetadata;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Proposta comercial (equipamento e/ou serviço), criada pelo vendedor de
 * campo (wizard mobile, App\Livewire\PropostaComercialMobile) e revisada
 * pelo time Comercial -- distinta de Quote (orçamento de peça/avaria,
 * aprovado pelo CLIENTE final, vira conta a receber). Aqui quem aprova é
 * interno (role "Comercial", mesma já usada em EquipmentDamage), e o
 * resultado da aprovação é "acionar" o equipamento/serviço, criando uma
 * SolicitacaoLocacao real -- o fluxo comercial tradicional (escolha fina
 * de Ativo, fechamento de contrato) continua dali em diante, como já
 * funciona hoje.
 */
class PropostaComercial extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use HasSaaSMetadata;
    use HasUuids;
    use LogsActivity;

    protected $table = 'proposta_comerciais';

    public const STATUS_RASCUNHO = 'rascunho';

    public const STATUS_ENVIADA_PARA_COMERCIAL = 'enviada_para_comercial';

    public const STATUS_APROVADA_INTERNA = 'aprovada_interna';

    public const STATUS_ACEITA_PELO_CLIENTE = 'aceita_pelo_cliente';

    public const STATUS_RECUSADA_PELO_CLIENTE = 'recusada_pelo_cliente';

    public const STATUS_REJEITADA = 'rejeitada';

    protected static ?string $saasFeatureKey = 'tabela_proposta_comercial';

    protected static ?string $saasPermissionSlug = 'proposta_comercial';

    protected static ?string $saasModuleLabel = 'Propostas Comerciais';

    /**
     * Default também em PHP (não só na migration) -- mesma armadilha já
     * documentada em Quote/SalesLead: sem isso, status/total_value ficam
     * null no objeto em memória logo após create() até um refresh().
     */
    protected $attributes = [
        'status' => self::STATUS_RASCUNHO,
        'total_value' => 0,
    ];

    protected $fillable = [
        'tenant_id',
        'crm_lead_id',
        'client_id',
        'seller_user_id',
        'reviewed_by_user_id',
        'status',
        'valid_until',
        'terms',
        'rejection_reason',
        'total_value',
        'sent_at',
        'reviewed_at',
        'solicitacao_locacao_id',
        'approval_token',
        'client_viewed_at',
        'client_responded_at',
        'ai_evaluation',
        'ai_evaluated_at',
    ];

    protected $casts = [
        'valid_until' => 'date',
        'total_value' => 'decimal:2',
        'sent_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'client_viewed_at' => 'datetime',
        'client_responded_at' => 'datetime',
        'ai_evaluation' => 'array',
        'ai_evaluated_at' => 'datetime',
    ];

    /**
     * @return array<string, string>
     */
    public static function statusLabels(): array
    {
        return [
            self::STATUS_RASCUNHO => 'Rascunho',
            self::STATUS_ENVIADA_PARA_COMERCIAL => 'Enviada para o Comercial',
            self::STATUS_APROVADA_INTERNA => 'Aprovada — Aguardando Cliente',
            self::STATUS_ACEITA_PELO_CLIENTE => 'Aceita pelo Cliente',
            self::STATUS_RECUSADA_PELO_CLIENTE => 'Recusada pelo Cliente',
            self::STATUS_REJEITADA => 'Rejeitada',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnlyDirty();
    }

    public function activities()
    {
        return $this->activitiesAsSubject();
    }

    public function crmLead(): BelongsTo
    {
        return $this->belongsTo(CrmLead::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function sellerUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_user_id');
    }

    public function reviewedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }

    public function solicitacaoLocacao(): BelongsTo
    {
        return $this->belongsTo(SolicitacaoLocacao::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PropostaComercialItem::class);
    }

    public function interactions(): HasMany
    {
        return $this->hasMany(PropostaComercialInteraction::class)->latest('contact_date');
    }

    /**
     * Soma dos itens -- chamado pelo PropostaComercialItemObserver toda vez
     * que um item é criado/editado/removido, mesmo padrão de
     * Quote::recalculateTotal().
     */
    public function recalculateTotal(): void
    {
        $this->update(['total_value' => $this->items()->sum('subtotal')]);
    }

    /**
     * Copia o texto padrão do template escolhido (ou do is_default) pro
     * campo terms da proposta -- CÓPIA, não referência: editar o template
     * depois não altera esta proposta.
     */
    public function fillFromTemplate(?PropostaComercialTemplate $template = null): void
    {
        $template ??= PropostaComercialTemplate::where('tenant_id', $this->tenant_id)
            ->where('is_default', true)
            ->where('is_active', true)
            ->first();

        if (! $template) {
            return;
        }

        $this->terms = $template->default_terms;

        if ($template->default_valid_days && ! $this->valid_until) {
            $this->valid_until = now()->addDays($template->default_valid_days);
        }
    }

    /**
     * Vendedor confirma e envia pro Comercial revisar -- precisa ter
     * cliente definido (aqui sim vira obrigatório, diferente do create) e
     * pelo menos 1 item, senão não há o que o Comercial avaliar.
     */
    public function enviarParaComercial(): void
    {
        if ($this->status !== self::STATUS_RASCUNHO) {
            throw new \RuntimeException('Só é possível enviar uma proposta em rascunho.');
        }

        if (! $this->client_id) {
            throw new \RuntimeException('Defina o cliente antes de enviar a proposta.');
        }

        if ($this->items()->doesntExist()) {
            throw new \RuntimeException('Adicione pelo menos um item (equipamento ou serviço) antes de enviar.');
        }

        $this->update([
            'status' => self::STATUS_ENVIADA_PARA_COMERCIAL,
            'sent_at' => now(),
        ]);

        $comerciais = User::where('tenant_id', $this->tenant_id)
            ->whereHas('roles', fn ($q) => $q->where('name', EquipmentDamage::ROLE_COMERCIAL))
            ->get();

        foreach ($comerciais as $user) {
            Mail::to($user->email)->send(new GenericPdfMail(
                subjectLine: "Proposta comercial aguardando revisão — {$this->client?->name}",
                greeting: "Olá, {$user->name}",
                bodyText: "Uma proposta comercial foi enviada por {$this->sellerUser?->name} e aguarda sua revisão no painel.",
            ));
        }
    }

    /**
     * Comercial aprova: aciona o equipamento/serviço criando uma
     * SolicitacaoLocacao real JÁ AQUI -- antes só nascia quando o cliente
     * aceitava (aceitarPeloCliente()), o que deixava a janela entre
     * "aprovada" e "cliente respondeu" sem nenhum registro visível pra
     * Manutenção/Comercial de que o equipamento já tinha sido solicitado.
     * Pedido explícito do usuário 28/09/2026. Exceto quando a proposta é
     * 100%-serviço (sem nenhum item "equipamento") -- nesse caso aprova
     * normalmente, mas o acionamento fica bloqueado com aviso claro
     * (resolvido de verdade só na Fase 2, ver plano). category_id de
     * SolicitacaoLocacao é NOT NULL no banco, então não dá pra criar sem
     * pelo menos 1 item de equipamento definindo a categoria.
     *
     * O envio do PDF pro cliente passa pela Caixa de E-mail (EmailMessage)
     * em vez de Mail::send() direto -- fica registrado/rastreável e
     * vinculado (related) a esta proposta, em vez de poder se perder sem
     * deixar rastro (outro pedido explícito do usuário).
     */
    public function aprovar(User $revisor): void
    {
        if ($this->status !== self::STATUS_ENVIADA_PARA_COMERCIAL) {
            throw new \RuntimeException('Só é possível aprovar uma proposta enviada ao Comercial.');
        }

        if (blank($this->client?->email)) {
            throw new \RuntimeException('Defina o e-mail do cliente antes de aprovar.');
        }

        $this->update([
            'status' => self::STATUS_APROVADA_INTERNA,
            'reviewed_by_user_id' => $revisor->id,
            'reviewed_at' => now(),
            'approval_token' => $this->approval_token ?? Str::random(48),
        ]);

        $primeiroEquipamento = $this->items()->where('type', PropostaComercialItem::TYPE_EQUIPAMENTO)->first();

        if ($primeiroEquipamento && ! $this->solicitacao_locacao_id) {
            $solicitacao = $this->criarSolicitacaoLocacao($primeiroEquipamento);

            $this->update(['solicitacao_locacao_id' => $solicitacao->id]);
        }

        $this->enviarPdfAoCliente($revisor);
    }

    /**
     * Gera o PDF e envia ao cliente pela Caixa de E-mail (fica registrado e ligado a esta proposta).
     * Usado na aprovação e no reenvio.
     */
    private function enviarPdfAoCliente(User $por): void
    {
        $pdf = Pdf::loadView('pdf.proposta-comercial', [
            'proposta' => $this->load(['items', 'client', 'sellerUser']),
            'generatedAt' => now()->format('d/m/Y H:i'),
        ])->output();

        $email = EmailMessage::create([
            'tenant_id' => $this->tenant_id,
            'from_user_id' => $por->id,
            'to_external' => [$this->client->email],
            'subject' => "Proposta comercial — {$this->client->name}",
            'body' => 'Segue em anexo a proposta comercial. Para aceitar ou recusar, acesse: '
                .route('proposta-comercial.public-approval', $this->approval_token),
            'related_type' => self::class,
            'related_id' => $this->id,
        ]);

        $email->addMediaFromString($pdf)
            ->usingFileName("proposta-comercial-{$this->id}.pdf")
            ->toMediaCollection('anexos');

        $email->send();
    }

    /**
     * Reenvia o PDF e o link de aceite ao cliente (e-mail perdido, endereço corrigido, cliente pediu de novo).
     * Só enquanto a proposta aguarda a resposta do cliente.
     */
    public function reenviarAoCliente(User $por): void
    {
        if ($this->status !== self::STATUS_APROVADA_INTERNA) {
            throw new \RuntimeException('Só é possível reenviar uma proposta aprovada que aguarda o cliente.');
        }

        if (blank($this->client?->email)) {
            throw new \RuntimeException('Defina o e-mail do cliente antes de reenviar.');
        }

        $this->enviarPdfAoCliente($por);
    }

    /**
     * Link do WhatsApp (clicar para conversar) com a mensagem e o link de aceite já escritos. Sem integração: o vendedor
     * só aperta enviar no próprio WhatsApp. null quando a proposta não aguarda o cliente ou o cliente não tem número.
     */
    public function linkWhatsapp(): ?string
    {
        if ($this->status !== self::STATUS_APROVADA_INTERNA || blank($this->approval_token)) {
            return null;
        }

        $numero = preg_replace('/\D+/', '', (string) ($this->client?->whatsapp ?: $this->client?->phone));
        if (strlen($numero) < 10) {
            return null;
        }
        if (strlen($numero) <= 11) {
            $numero = '55'.$numero;   // número brasileiro sem o código do país
        }

        $texto = "Olá, {$this->client?->name}! Segue a nossa proposta comercial. Para ver e responder (aceitar ou recusar), acesse: "
            .route('proposta-comercial.public-approval', $this->approval_token);

        return 'https://wa.me/'.$numero.'?text='.rawurlencode($texto);
    }

    /**
     * Chamado quando o cliente abre o link público de aprovação -- só
     * registra a PRIMEIRA visualização, mesmo padrão de Quote::markViewedByClient().
     */
    public function markViewedByClient(): void
    {
        if ($this->client_viewed_at) {
            return;
        }

        $this->update(['client_viewed_at' => now()]);
    }

    /**
     * Cliente aceita pelo link público. A SolicitacaoLocacao já nasce em
     * aprovar() (28/09/2026) -- aqui só cobre o caso legado de uma proposta
     * aprovada antes dessa mudança (sem solicitacao_locacao_id ainda), pra
     * não deixar pra trás quem já estava no meio do fluxo.
     */
    public function aceitarPeloCliente(): void
    {
        if ($this->status !== self::STATUS_APROVADA_INTERNA) {
            throw new \RuntimeException('Só é possível aceitar uma proposta aprovada internamente.');
        }

        $this->update([
            'status' => self::STATUS_ACEITA_PELO_CLIENTE,
            'client_responded_at' => now(),
        ]);

        if ($this->solicitacao_locacao_id) {
            return;
        }

        $primeiroEquipamento = $this->items()->where('type', PropostaComercialItem::TYPE_EQUIPAMENTO)->first();

        if (! $primeiroEquipamento) {
            return;
        }

        $solicitacao = $this->criarSolicitacaoLocacao($primeiroEquipamento);

        $this->update(['solicitacao_locacao_id' => $solicitacao->id]);
    }

    /**
     * Se aprovar() já tinha criado a SolicitacaoLocacao (equipamento
     * "solicitado" antes da resposta), o cliente recusar precisa cancelar
     * essa reserva informativa -- senão ela fica pra sempre em
     * "proposta_em_andamento" e continua aparecendo pra Manutenção mesmo
     * depois de morta.
     */
    public function recusarPeloCliente(string $motivo): void
    {
        if ($this->status !== self::STATUS_APROVADA_INTERNA) {
            throw new \RuntimeException('Só é possível recusar uma proposta aprovada internamente.');
        }

        $this->update([
            'status' => self::STATUS_RECUSADA_PELO_CLIENTE,
            'client_responded_at' => now(),
            'rejection_reason' => $motivo,
        ]);

        if ($this->solicitacaoLocacao && $this->solicitacaoLocacao->status_comercial === 'proposta_em_andamento') {
            $this->solicitacaoLocacao->update(['status_comercial' => 'cancelado']);
        }
    }

    public function rejeitar(User $revisor, string $motivo): void
    {
        if ($this->status !== self::STATUS_ENVIADA_PARA_COMERCIAL) {
            throw new \RuntimeException('Só é possível rejeitar uma proposta enviada ao Comercial.');
        }

        $this->update([
            'status' => self::STATUS_REJEITADA,
            'rejection_reason' => $motivo,
            'reviewed_by_user_id' => $revisor->id,
            'reviewed_at' => now(),
        ]);
    }

    /**
     * Permite o vendedor corrigir e reenviar sem duplicar a proposta
     * inteira -- zera os campos de revisão anterior.
     */
    public function reabrirParaEdicao(): void
    {
        if (! in_array($this->status, [self::STATUS_REJEITADA, self::STATUS_RECUSADA_PELO_CLIENTE], true)) {
            throw new \RuntimeException('Só é possível reabrir uma proposta rejeitada ou recusada pelo cliente.');
        }

        $this->update([
            'status' => self::STATUS_RASCUNHO,
            'rejection_reason' => null,
            'reviewed_by_user_id' => null,
            'reviewed_at' => null,
        ]);
    }

    private function criarSolicitacaoLocacao(PropostaComercialItem $primeiroEquipamento): SolicitacaoLocacao
    {
        $dataSaidaPrevista = $this->items()
            ->whereNotNull('start_date')
            ->orderBy('start_date')
            ->value('start_date') ?? $this->valid_until ?? now()->addDays(7);

        $resumoItens = $this->items->map(function (PropostaComercialItem $item) {
            $tipo = PropostaComercialItem::typeLabels()[$item->type] ?? $item->type;

            return "{$tipo}: {$item->description} (qtd {$item->quantity})";
        })->implode(' | ');

        return SolicitacaoLocacao::create([
            'tenant_id' => $this->tenant_id,
            'user_id' => $this->seller_user_id,
            'customer_id' => $this->client_id,
            'category_id' => $primeiroEquipamento->asset_category_id,
            'purpose' => "Proposta Comercial #{$this->id}: {$resumoItens}",
            'data_saida_prevista' => $dataSaidaPrevista,
            'status_comercial' => 'proposta_em_andamento',
            'observations' => $this->terms,
        ]);
    }
}
