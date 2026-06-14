<?php

namespace App\Traits;

use Filament\Facades\Filament;

trait HasPlanFeatures
{
    /**
     * 🛡️ SEGURANÇA DE URL (403): Bloqueia o acesso direto caso o inquilino force o link
     */
    public static function canViewAny(): bool
    {
        return static::verificarPermissaoSaaS();
    }

    /**
     * 🛡️ VISIBILIDADE DA SIDEBAR: Some com o menu da barra lateral esquerda se não for contratado
     */
    public static function shouldRegisterNavigation(): bool
    {
        return static::verificarPermissaoSaaS();
    }

    /**
     * Motor centralizador de validação do SaaS com tipagem estrita
     */
    protected static function verificarPermissaoSaaS(): bool
    {
        $tenant = Filament::getTenant();
        
        // Se estiver fora do contexto de inquilino (ex: painel central), exibe por padrão
        if (! $tenant) {
            return true;
        }

        $requiredFeature = static::getRequiredFeature();
        
        // Se o recurso não declarar uma feature restritiva, ele é livre no sistema
        if (! $requiredFeature) {
            return true;
        }

        $tenant->loadMissing('plan');
        $featuresOriginal = $tenant->plan->features ?? [];

        // 🎯 HIGIENIZADOR CONTRA BUG DE TIPAGEM DO PHP (Mata falsos zeros ou booleanos do banco)
        $featuresPermitidas = [];
        foreach ($featuresOriginal as $chave => $valor) {
            if (is_string($chave)) {
                if ($valor === true || $valor === 1 || $valor === '1' || $valor === 'true') {
                    $featuresPermitidas[] = $chave;
                }
            } else {
                if ($valor !== false && $valor !== 0 && $valor !== '0' && $valor !== 'false') {
                    $featuresPermitidas[] = $valor;
                }
            }
        }

        // Retorna se a feature declarada está explicitamente contratada
        return in_array($requiredFeature, $featuresPermitidas, true);
    }

    /**
     * Retorna a chave da feature necessária definida na propriedade estática do Resource/Page.
     */
    public static function getRequiredFeature(): ?string
    {
        return static::$requiredFeature ?? null;
    }
}
