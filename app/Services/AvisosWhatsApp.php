<?php

namespace App\Services;

use App\Models\AccountReceivable;
use App\Models\Client;
use App\Models\MaintenanceOrder;
use App\Models\TenantWhatsappSetting;
use App\Models\WhatsappMensagem;
use App\Models\WhatsappNumero;
use Illuminate\Database\Eloquent\Model;

/**
 * Avisos automáticos de WhatsApp aos CLIENTES da empresa (cobrança e ordem de serviço), pelo número
 * da empresa e só com modelo aprovado pela Meta. Regras de segurança:
 *  - a empresa liga cada tipo de aviso (começam desligados) e informa o nome do modelo;
 *  - só vai para cliente que aceitou receber avisos por WhatsApp;
 *  - cada aviso é enviado uma única vez por cobrança/OS.
 */
class AvisosWhatsApp
{
    /** Lembretes de vencimento (X dias antes) e de atraso (1 dia depois) de uma empresa. Devolve quantos foram enviados. */
    public static function cobrancas(TenantWhatsappSetting $config, ?\Illuminate\Support\Carbon $hoje = null): int
    {
        if (! $config->enabled || ! $config->aviso_cobranca || blank($config->template_cobranca)) {
            return 0;
        }

        $hoje = ($hoje ?? now())->copy()->startOfDay();
        $enviados = 0;

        $contas = AccountReceivable::withoutGlobalScopes()
            ->where('tenant_id', $config->tenant_id)
            ->whereIn('status', ['pendente', 'atrasado'])
            ->whereNotNull('client_id')
            ->where(fn ($q) => $q
                ->whereDate('due_date', $hoje->copy()->addDays((int) $config->aviso_cobranca_dias_antes))
                ->orWhereDate('due_date', $hoje->copy()->subDay()))
            ->with('client')
            ->get();

        foreach ($contas as $conta) {
            $atrasada = $conta->due_date->lt($hoje);

            $enviou = static::enviar(
                $config, $conta->client, $atrasada ? 'cobranca_atrasada' : 'cobranca_vencimento', $config->template_cobranca,
                [
                    (string) $conta->client->name,
                    (string) ($conta->description ?: 'Fatura'),
                    'R$ '.number_format((float) $conta->amount, 2, ',', '.'),
                    $conta->due_date->format('d/m/Y'),
                ],
                $conta,
            );

            $enviados += $enviou ? 1 : 0;
        }

        return $enviados;
    }

    /** Aviso de OS concluída ao cliente. Devolve true se enviou. */
    public static function osConcluida(MaintenanceOrder $ordem): bool
    {
        $config = TenantWhatsappSetting::withoutGlobalScopes()->where('tenant_id', $ordem->tenant_id)->first();

        if (! $config || ! $config->enabled || ! $config->aviso_os || blank($config->template_os_concluida) || ! $ordem->client_id) {
            return false;
        }

        return static::enviar(
            $config, $ordem->client, 'os_concluida', $config->template_os_concluida,
            [(string) $ordem->client?->name, (string) $ordem->os_number, (string) ($ordem->asset?->name ?? 'equipamento')],
            $ordem,
        );
    }

    private static function enviar(TenantWhatsappSetting $config, ?Client $cliente, string $evento, string $modelo, array $variaveis, Model $relacionado): bool
    {
        $telefone = (string) ($cliente?->whatsapp ?: $cliente?->phone);

        if (! $cliente || ! $cliente->aceita_avisos_whatsapp || strlen(preg_replace('/\D+/', '', $telefone)) < 10) {
            return false;
        }

        $jaEnviado = WhatsappMensagem::withoutGlobalScopes()
            ->where('tenant_id', $config->tenant_id)->where('evento', $evento)->where('related_id', $relacionado->getKey())->exists();

        if ($jaEnviado) {
            return false;
        }

        $numero = WhatsappNumero::withoutGlobalScopes()->where('tenant_id', $config->tenant_id)->whereNull('user_id')->where('enabled', true)->first();
        $servico = WhatsAppEmpresaService::paraNumero($numero);

        if (! $servico) {
            return false; // aviso automático sai pelo número da empresa; sem ele não há de onde enviar
        }

        $conversa = $servico->conversa($telefone, $cliente->name);
        $mensagem = $servico->enviarModelo($conversa, $modelo, $variaveis, null, $relacionado);
        $mensagem->update(['evento' => $evento]);

        return $mensagem->status !== 'falhou';
    }
}
