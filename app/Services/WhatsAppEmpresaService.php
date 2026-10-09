<?php

namespace App\Services;

use App\Models\Client;
use App\Models\CrmLead;
use App\Models\TenantWhatsappSetting;
use App\Models\WhatsappNumero;
use App\Models\User;
use App\Models\WhatsappConversa;
use App\Models\WhatsappMensagem;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Http;

/**
 * WhatsApp oficial (API da Meta) pelo número de cada usuário da empresa (ou o número
 * da empresa). A empresa cadastra os números na tela WhatsApp da Empresa; aqui só se fala com a Meta
 * e se registra cada mensagem como Enviando/Enviada/Entregue/Lida/Falhou.
 *
 * Regra do WhatsApp: texto livre só até 24 h depois da última mensagem do
 * cliente. Fora disso, só modelo (template) aprovado pela Meta.
 */
class WhatsAppEmpresaService
{
    public function __construct(private TenantWhatsappSetting $config, private WhatsappNumero $numero) {}

    /** Serviço do número informado, ou null se a empresa não ligou o WhatsApp. */
    public static function paraNumero(?WhatsappNumero $numero): ?self
    {
        if (! $numero || ! $numero->enabled) {
            return null;
        }

        $config = TenantWhatsappSetting::withoutGlobalScopes()->where('tenant_id', $numero->tenant_id)->where('enabled', true)->first();

        return $config ? new self($config, $numero) : null;
    }

    /** Número do usuário; se ele não tiver, o número da empresa; se não houver, null (use o link wa.me). */
    public static function paraUsuario(User $usuario): ?self
    {
        $numeros = WhatsappNumero::withoutGlobalScopes()->where('tenant_id', $usuario->tenant_id)->where('enabled', true);

        $numero = (clone $numeros)->where('user_id', $usuario->id)->first() ?? (clone $numeros)->whereNull('user_id')->first();

        return static::paraNumero($numero);
    }

    public function numero(): WhatsappNumero
    {
        return $this->numero;
    }

    /** Só dígitos, com o DDI do Brasil quando vier sem. */
    public static function normalizarTelefone(?string $telefone): string
    {
        $digitos = preg_replace('/\D+/', '', (string) $telefone);

        return strlen($digitos) <= 11 && $digitos !== '' ? '55'.$digitos : $digitos;
    }

    public function conversa(string $telefone, ?string $nome = null): WhatsappConversa
    {
        $telefone = static::normalizarTelefone($telefone);
        $tenantId = $this->config->tenant_id;

        $conversa = WhatsappConversa::withoutGlobalScopes()->firstOrCreate(
            ['tenant_id' => $tenantId, 'numero_id' => $this->numero->id, 'telefone' => $telefone],
            ['nome' => $nome, 'responsavel_user_id' => $this->numero->user_id],
        );

        if ($nome && ! $conversa->nome) {
            $conversa->update(['nome' => $nome]);
        }

        $this->vincular($conversa);

        return $conversa;
    }

    /** Liga a conversa a um cliente ou lead da empresa pelo número (ignora o 9 extra e o DDI). */
    private function vincular(WhatsappConversa $conversa): void
    {
        if ($conversa->client_id || $conversa->crm_lead_id) {
            return;
        }

        $fim = substr($conversa->telefone, -8);

        $cliente = Client::withoutGlobalScopes()->where('tenant_id', $conversa->tenant_id)
            ->get(['id', 'name', 'phone', 'whatsapp'])
            ->first(fn ($c) => collect([$c->phone, $c->whatsapp])->contains(fn ($t) => $t && str_ends_with(preg_replace('/\D+/', '', $t), $fim)));

        if ($cliente) {
            $conversa->update(['client_id' => $cliente->id, 'nome' => $conversa->nome ?: $cliente->name]);

            return;
        }

        $lead = CrmLead::withoutGlobalScopes()->where('tenant_id', $conversa->tenant_id)
            ->get(['id', 'name', 'phone', 'whatsapp'])
            ->first(fn ($l) => collect([$l->phone, $l->whatsapp])->contains(fn ($t) => $t && str_ends_with(preg_replace('/\D+/', '', $t), $fim)));

        if ($lead) {
            $conversa->update(['crm_lead_id' => $lead->id, 'nome' => $conversa->nome ?: $lead->name]);
        }
    }

    /** Texto livre: só com a janela de 24 h aberta. */
    public function enviarTexto(WhatsappConversa $conversa, string $texto, ?User $por = null, ?Model $relacionado = null): WhatsappMensagem
    {
        $mensagem = $this->registrarSaida($conversa, 'texto', $texto, $por, $relacionado);

        if (! $conversa->janelaAberta()) {
            return $this->falhar($mensagem, 'Passaram mais de 24 horas desde a última mensagem do cliente. O WhatsApp só permite enviar um modelo aprovado.');
        }

        return $this->enviar($mensagem, [
            'messaging_product' => 'whatsapp', 'to' => $conversa->telefone, 'type' => 'text',
            'text' => ['body' => $texto, 'preview_url' => true],
        ]);
    }

    /**
     * Modelo aprovado pela Meta (pode abrir conversa a qualquer hora).
     *
     * @param  array<int, string>  $variaveis  valores de {{1}}, {{2}}...
     */
    public function enviarModelo(WhatsappConversa $conversa, string $modelo, array $variaveis, ?User $por = null, ?Model $relacionado = null, ?string $textoParaRegistro = null): WhatsappMensagem
    {
        $mensagem = $this->registrarSaida($conversa, 'modelo', $textoParaRegistro ?? '['.$modelo.'] '.implode(' | ', $variaveis), $por, $relacionado);

        $template = ['name' => $modelo, 'language' => ['code' => $this->config->template_language ?: 'pt_BR']];

        if ($variaveis !== []) {
            $template['components'] = [['type' => 'body', 'parameters' => array_map(fn ($v) => ['type' => 'text', 'text' => (string) $v], array_values($variaveis))]];
        }

        return $this->enviar($mensagem, ['messaging_product' => 'whatsapp', 'to' => $conversa->telefone, 'type' => 'template', 'template' => $template]);
    }

    private function token(): string
    {
        return (string) ($this->numero->access_token ?: $this->config->access_token);
    }

    /** Confere o token e o número na Meta, sem enviar nada. */
    public function testarConexao(): array
    {
        $resposta = Http::withToken($this->token())->timeout(20)
            ->get($this->url($this->numero->phone_number_id), ['fields' => 'display_phone_number,verified_name']);

        if (! $resposta->successful()) {
            throw new \RuntimeException($resposta->json('error.message') ?? 'A Meta recusou o acesso ('.$resposta->status().').');
        }

        return $resposta->json();
    }

    private function registrarSaida(WhatsappConversa $conversa, string $tipo, string $corpo, ?User $por, ?Model $relacionado): WhatsappMensagem
    {
        $mensagem = WhatsappMensagem::withoutGlobalScopes()->create([
            'tenant_id' => $conversa->tenant_id, 'conversa_id' => $conversa->id, 'direcao' => WhatsappMensagem::SAIDA,
            'tipo' => $tipo, 'corpo' => $corpo, 'status' => 'enviando', 'enviada_por_user_id' => $por?->id,
            'related_type' => $relacionado ? $relacionado->getMorphClass() : null, 'related_id' => $relacionado?->getKey(),
        ]);

        $conversa->update(['ultima_mensagem_em' => now()]);

        return $mensagem;
    }

    private function enviar(WhatsappMensagem $mensagem, array $corpo): WhatsappMensagem
    {
        try {
            $resposta = Http::withToken($this->token())->timeout(20)
                ->post($this->url($this->numero->phone_number_id.'/messages'), $corpo);
        } catch (\Throwable $e) {
            return $this->falhar($mensagem, 'Não foi possível falar com o WhatsApp: '.$e->getMessage());
        }

        if (! $resposta->successful()) {
            return $this->falhar($mensagem, (string) ($resposta->json('error.message') ?? 'O WhatsApp recusou a mensagem ('.$resposta->status().').'));
        }

        $mensagem->update(['status' => 'enviada', 'wa_id' => $resposta->json('messages.0.id'), 'erro' => null]);

        return $mensagem->refresh();
    }

    private function falhar(WhatsappMensagem $mensagem, string $erro): WhatsappMensagem
    {
        $mensagem->update(['status' => 'falhou', 'erro' => mb_substr($erro, 0, 500)]);

        return $mensagem->refresh();
    }

    private function url(string $caminho): string
    {
        return rtrim((string) config('services.whatsapp.base_url', 'https://graph.facebook.com/v20.0'), '/').'/'.$caminho;
    }
}
