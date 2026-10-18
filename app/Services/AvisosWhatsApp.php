<?php

namespace App\Services;

use App\Models\AccountReceivable;
use App\Models\Client;
use App\Models\MaintenanceOrder;
use App\Models\NotaFiscal;
use App\Models\TenantAsaasSetting;
use App\Models\TenantWhatsappSetting;
use App\Models\WhatsappMensagem;
use App\Models\WhatsappNumero;
use App\Services\Cobranca\CobrancaService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

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
    public static function cobrancas(TenantWhatsappSetting $config, ?Carbon $hoje = null): int
    {
        if (! $config->enabled || ! $config->aviso_cobranca || blank($config->template_cobranca) || ! WhatsAppEmpresaService::moduloLiberado($config->tenant_id)) {
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

            $variaveis = static::variaveisCobranca($config, $conta);

            // Modelo com link (5 variáveis): sem link de pagamento não há o que avisar ainda.
            if ($variaveis === null) {
                continue;
            }

            $enviou = static::enviar(
                $config, $conta->client, $atrasada ? 'cobranca_atrasada' : 'cobranca_vencimento', $config->template_cobranca, $variaveis, $conta,
            );

            $enviados += $enviou ? 1 : 0;
        }

        return $enviados;
    }

    /**
     * Variáveis do modelo de cobrança: nome, descrição, valor e vencimento (+ link de pagamento quando a empresa usa o modelo
     * de 5 variáveis). Devolve null se o modelo exige link e a conta ainda não tem cobrança.
     *
     * @return list<string>|null
     */
    private static function variaveisCobranca(TenantWhatsappSetting $config, AccountReceivable $conta): ?array
    {
        $variaveis = [
            (string) $conta->client->name,
            (string) ($conta->description ?: 'Fatura'),
            'R$ '.number_format((float) $conta->amount, 2, ',', '.'),
            $conta->due_date->format('d/m/Y'),
        ];

        if ($config->cobranca_com_link) {
            $link = static::linkDaCobranca($conta);

            if (! $link) {
                return null;
            }

            $variaveis[] = $link;
        }

        return $variaveis;
    }

    /** Link de pagamento da conta; se ainda não existe e a cobrança automática está pronta, gera agora. */
    private static function linkDaCobranca(AccountReceivable $conta): ?string
    {
        if (filled($conta->asaas_invoice_url)) {
            return $conta->asaas_invoice_url;
        }

        if (! (TenantAsaasSetting::daEmpresa($conta->tenant_id)?->pronta() ?? false)) {
            return null;
        }

        try {
            return app(CobrancaService::class)->gerar($conta)->asaas_invoice_url ?: null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Reenvio manual do aviso de cobrança (painel de cobranças): ignora o "já enviado". Lança com o motivo quando não dá para enviar.
     *
     * @throws \RuntimeException
     */
    public static function reenviarCobranca(AccountReceivable $conta): void
    {
        $config = TenantWhatsappSetting::withoutGlobalScopes()->where('tenant_id', $conta->tenant_id)->first();

        if (! $config || ! $config->enabled || blank($config->template_cobranca) || ! WhatsAppEmpresaService::moduloLiberado($conta->tenant_id)) {
            throw new \RuntimeException('O WhatsApp da empresa não está ligado ou falta o modelo de cobrança.');
        }

        $cliente = $conta->client ?? throw new \RuntimeException('A conta não tem cliente.');

        if (! $cliente->aceita_avisos_whatsapp) {
            throw new \RuntimeException('O cliente não aceitou receber avisos por WhatsApp.');
        }

        if (strlen(preg_replace('/\D+/', '', (string) ($cliente->whatsapp ?: $cliente->phone))) < 10) {
            throw new \RuntimeException('O cliente não tem um WhatsApp válido cadastrado.');
        }

        $variaveis = static::variaveisCobranca($config, $conta) ?? throw new \RuntimeException('Esta conta ainda não tem link de pagamento. Gere a cobrança primeiro.');

        if (! static::enviar($config, $cliente, $conta->due_date->isPast() ? 'cobranca_atrasada' : 'cobranca_vencimento', $config->template_cobranca, $variaveis, $conta, forcar: true)) {
            throw new \RuntimeException('O WhatsApp não aceitou a mensagem (sem número da empresa ou modelo recusado).');
        }
    }

    /**
     * Envia a NFS-e ao cliente por WhatsApp (botão "Enviar ao cliente"). Lança com o motivo quando não dá para enviar.
     *
     * @throws \RuntimeException
     */
    public static function notaFiscal(NotaFiscal $nota): void
    {
        $config = TenantWhatsappSetting::withoutGlobalScopes()->where('tenant_id', $nota->tenant_id)->first();

        if (! $config || ! $config->enabled || blank($config->template_nfse) || ! WhatsAppEmpresaService::moduloLiberado($nota->tenant_id)) {
            throw new \RuntimeException('O WhatsApp da empresa não está ligado ou falta o modelo de NFS-e (Configurações → WhatsApp da Empresa).');
        }

        $cliente = $nota->cliente ?? throw new \RuntimeException('A nota não tem cliente.');

        if (! $cliente->aceita_avisos_whatsapp) {
            throw new \RuntimeException('O cliente não aceitou receber avisos por WhatsApp.');
        }

        if (blank($nota->pdf_url)) {
            throw new \RuntimeException('A nota ainda não foi autorizada pela prefeitura.');
        }

        if (! static::enviar($config, $cliente, 'nfse', $config->template_nfse, [(string) $cliente->name, (string) $nota->numero, (string) $nota->pdf_url], $nota, forcar: true)) {
            throw new \RuntimeException('O WhatsApp não aceitou a mensagem (cliente sem WhatsApp válido, sem número da empresa ou modelo recusado).');
        }
    }

    /** Aviso de OS concluída ao cliente. Devolve true se enviou. */
    public static function osConcluida(MaintenanceOrder $ordem): bool
    {
        $config = TenantWhatsappSetting::withoutGlobalScopes()->where('tenant_id', $ordem->tenant_id)->first();

        if (! $config || ! $config->enabled || ! $config->aviso_os || blank($config->template_os_concluida) || ! $ordem->client_id || ! WhatsAppEmpresaService::moduloLiberado($ordem->tenant_id)) {
            return false;
        }

        return static::enviar(
            $config, $ordem->client, 'os_concluida', $config->template_os_concluida,
            [(string) $ordem->client?->name, (string) $ordem->os_number, (string) ($ordem->asset?->name ?? 'equipamento')],
            $ordem,
        );
    }

    private static function enviar(TenantWhatsappSetting $config, ?Client $cliente, string $evento, string $modelo, array $variaveis, Model $relacionado, bool $forcar = false): bool
    {
        $telefone = (string) ($cliente?->whatsapp ?: $cliente?->phone);

        if (! $cliente || ! $cliente->aceita_avisos_whatsapp || strlen(preg_replace('/\D+/', '', $telefone)) < 10) {
            return false;
        }

        $jaEnviado = WhatsappMensagem::withoutGlobalScopes()
            ->where('tenant_id', $config->tenant_id)->where('evento', $evento)->where('related_id', $relacionado->getKey())->exists();

        if ($jaEnviado && ! $forcar) {
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
