<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Conversa de WhatsApp de uma empresa com um número (cliente, lead ou desconhecido). */
class WhatsappConversa extends Model
{
    use BelongsToTenant;
    use HasUuids;

    protected $table = 'whatsapp_conversas';

    protected $fillable = [
        'tenant_id', 'numero_id', 'telefone', 'nome', 'client_id', 'crm_lead_id', 'responsavel_user_id',
        'ultima_mensagem_em', 'ultima_recebida_em', 'nao_lidas', 'atribuida_em',
    ];

    protected $casts = ['ultima_mensagem_em' => 'datetime', 'ultima_recebida_em' => 'datetime', 'atribuida_em' => 'datetime', 'nao_lidas' => 'integer'];

    public function mensagens(): HasMany
    {
        return $this->hasMany(WhatsappMensagem::class, 'conversa_id')->oldest();
    }

    public function numero(): BelongsTo
    {
        return $this->belongsTo(WhatsappNumero::class, 'numero_id');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(CrmLead::class, 'crm_lead_id');
    }

    public function responsavel(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsavel_user_id');
    }

    /** O WhatsApp só deixa responder com texto livre até 24 h depois da última mensagem do cliente. */
    public function janelaAberta(): bool
    {
        return $this->ultima_recebida_em && $this->ultima_recebida_em->gt(now()->subHours(24));
    }

    public function titulo(): string
    {
        return $this->nome ?: $this->client?->name ?: $this->lead?->name ?: '+'.$this->telefone;
    }
}
