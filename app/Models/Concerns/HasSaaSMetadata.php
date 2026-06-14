<?php

namespace App\Models\Concerns;

/**
 * Fonte Única de Verdade dos metadados SaaS de um Model.
 *
 * Cada Model que vira um módulo/menu declara:
 *   - $saasFeatureKey:     chave da feature no plano (trava comercial). Ex.: 'tabela_clients'
 *   - $saasPermissionSlug: slug usado nas permissões "{acao}_{slug}". Ex.: 'cliente'
 *   - $saasModuleLabel:    rótulo exibido no formulário de Perfis de Acesso. Ex.: 'Clientes'
 *
 * Models sem esses valores (null) são ignorados pela automação (tabelas-filhas, logs, etc.).
 */
trait HasSaaSMetadata
{
    public static function saasFeatureKey(): ?string
    {
        return static::$saasFeatureKey ?? null;
    }

    public static function saasPermissionSlug(): ?string
    {
        return static::$saasPermissionSlug ?? null;
    }

    public static function saasModuleLabel(): ?string
    {
        return static::$saasModuleLabel ?? null;
    }

    /**
     * Indica se este Model participa da automação SaaS (tem slug de permissão).
     */
    public static function isSaaSModule(): bool
    {
        return static::saasPermissionSlug() !== null;
    }
}
