<?php

namespace App\Support\Ia;

use App\Models\IaConsumo;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Registro do consumo de tokens da IA (alimenta o painel da Central). Chamado pelo AnthropicApiClient, por onde
 * passam TODAS as chamadas de IA do sistema. Nunca pode quebrar a chamada: qualquer falha aqui so' vai pro log.
 */
class ConsumoIa
{
    /** Aplicativos que aparecem no painel (chave => rotulo). Ordem = ordem de exibicao. */
    public const AREAS = [
        'suprimentos' => 'Suprimentos',
        'almoxarifado' => 'Almoxarifado',
        'comercial' => 'Comercial',
        'financeiro' => 'Financeiro',
        'manutencao' => 'Manutenção',
        'logistica' => 'Logística',
        'prospects' => 'Prospecção (Central)',
        'whatsapp' => 'Atendente WhatsApp',
        'outros' => 'Outros recursos de IA',
    ];

    /**
     * Servicos que ainda nao informam a area: a area sai do nome da classe que chamou o cliente de IA.
     * Servico novo? Prefira passar ['area' => ...] no contexto da chamada em vez de crescer esta lista.
     *
     * @var array<string, string>
     */
    private const CHAMADORES = [
        'ProspectChatService' => 'prospects',
        'SuprimentosChatService' => 'suprimentos',
        'ProspectAnalysisService' => 'comercial',
        'CommercialLeadAnalysisService' => 'comercial',
        'PropostaComercialAiEvaluator' => 'comercial',
        'PropostaTemplateImportador' => 'comercial',
        'StockAnalysisService' => 'almoxarifado',
        'PreventiveMaintenanceAnalysisService' => 'manutencao',
        'CorrectiveReworkAnalysisService' => 'manutencao',
        'EquipmentDamageDiagnosisService' => 'manutencao',
        'OsPhotoOcrService' => 'manutencao',
        'ChecklistFolhaIA' => 'manutencao',
        'LogisticsRouteAnalysisService' => 'logistica',
        'ProcessWhatsAppMessageJob' => 'whatsapp',
    ];

    public static function rotulo(string $area): string
    {
        return self::AREAS[$area] ?? ucfirst($area);
    }

    /** @return array{area: string} */
    public static function contextoDoChamador(): array
    {
        foreach (debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 8) as $frame) {
            $classe = $frame['class'] ?? null;

            if ($classe === null || $classe === self::class || str_ends_with($classe, 'AnthropicApiClient')) {
                continue;
            }

            return ['area' => self::CHAMADORES[class_basename($classe)] ?? 'outros'];
        }

        return ['area' => 'outros'];
    }

    /**
     * @param  array<string, mixed>|null  $usage  campo "usage" da resposta da API
     * @param  array{area?: string, etapa?: string, tenant_id?: ?string}  $contexto
     */
    public static function registrar(string $modelo, ?array $usage, array $contexto = []): void
    {
        if (! is_array($usage)) {
            return;
        }

        try {
            $user = Auth::user();

            // tenant_id informado (mesmo nulo) manda; senao, o cliente do usuario logado (super admin nao tem cliente).
            $tenantId = array_key_exists('tenant_id', $contexto)
                ? $contexto['tenant_id']
                : ($user && ! $user->isSuperAdmin() ? $user->tenant_id : null);

            $entrada = (int) ($usage['input_tokens'] ?? 0);
            $saida = (int) ($usage['output_tokens'] ?? 0);
            $cacheLeitura = (int) ($usage['cache_read_input_tokens'] ?? 0);
            $cacheEscrita = (int) ($usage['cache_creation_input_tokens'] ?? 0);

            IaConsumo::create([
                'tenant_id' => $tenantId,
                'user_id' => $user?->getAuthIdentifier(),
                'area' => $contexto['area'] ?? 'outros',
                'etapa' => $contexto['etapa'] ?? 'ask',
                'model' => $modelo,
                'input_tokens' => $entrada,
                'output_tokens' => $saida,
                'cache_read_tokens' => $cacheLeitura,
                'cache_write_tokens' => $cacheEscrita,
                'custo_usd' => Precos::custoUsd($modelo, $entrada, $saida, $cacheLeitura, $cacheEscrita),
            ]);
        } catch (\Throwable $e) {
            Log::warning('ia_consumo_falha_ao_registrar', ['erro' => $e->getMessage()]);
        }
    }
}
