<?php

namespace App\Services;

use App\Models\TenantWhatsappSetting;
use App\Models\User;
use App\Models\WhatsappConversa;

/**
 * Quem atende as conversas que chegam no número da EMPRESA (o que não é de um usuário).
 * Ordem de decisão ao chegar uma conversa sem responsável:
 *  1. continuidade: o responsável do lead ligado à conversa, ou quem já atendeu esse mesmo telefone;
 *  2. rodízio: o próximo dos atendentes escolhidos (se a empresa ligou o rodízio);
 *  3. fila: fica sem dono e quem atende assume.
 */
class DistribuicaoWhatsApp
{
    public static function atribuir(WhatsappConversa $conversa, TenantWhatsappSetting $config): ?User
    {
        if ($conversa->responsavel_user_id || $conversa->numero?->user_id) {
            return null; // já tem dono, ou o número é de uma pessoa
        }

        $usuario = static::continuidade($conversa) ?? ($config->distribuicao === 'rodizio' ? static::proximoDoRodizio($config) : null);

        if ($usuario) {
            $conversa->update(['responsavel_user_id' => $usuario->id, 'atribuida_em' => now()]);
        }

        return $usuario;
    }

    private static function continuidade(WhatsappConversa $conversa): ?User
    {
        $candidatos = collect();

        if ($conversa->lead?->assigned_user_id) {
            $candidatos->push($conversa->lead->assigned_user_id);
        }

        $anterior = WhatsappConversa::withoutGlobalScopes()
            ->where('tenant_id', $conversa->tenant_id)->where('telefone', $conversa->telefone)
            ->where('id', '!=', $conversa->id)->whereNotNull('responsavel_user_id')
            ->latest('ultima_mensagem_em')->value('responsavel_user_id');

        if ($anterior) {
            $candidatos->push($anterior);
        }

        foreach ($candidatos as $id) {
            $usuario = static::usuario($conversa->tenant_id, $id);
            if ($usuario) {
                return $usuario;
            }
        }

        return null;
    }

    private static function proximoDoRodizio(TenantWhatsappSetting $config): ?User
    {
        $ids = collect((array) $config->atendentes)
            ->filter(fn ($id) => static::usuario($config->tenant_id, $id))
            ->values();

        if ($ids->isEmpty()) {
            return null;
        }

        $posicao = $ids->search($config->ultimo_atendente_id);
        $proximo = $ids[$posicao === false ? 0 : ($posicao + 1) % $ids->count()];

        $config->update(['ultimo_atendente_id' => $proximo]);

        return static::usuario($config->tenant_id, $proximo);
    }

    private static function usuario(string $tenantId, string $id): ?User
    {
        return User::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('is_approved', true)->find($id);
    }
}
