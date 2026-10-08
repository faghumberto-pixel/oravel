<?php

namespace App\Support;

/**
 * Identificação do emissor nos relatórios/PDFs: o nome do cliente (tenant)
 * aparece em destaque no cabeçalho; o nome Oravel fica só no rodapé, sem destaque.
 */
class Relatorio
{
    public static function emissor(): string
    {
        $tenant = Tenancy::current();

        return $tenant?->nome_fantasia ?: ($tenant?->name ?: 'Oravel');
    }

    /** Linha de apoio sob o nome: CNPJ/CPF e cidade, quando cadastrados. */
    public static function detalhe(): string
    {
        $tenant = Tenancy::current();

        if (! $tenant) {
            return '';
        }

        return collect([
            $tenant->cpf_cnpj,
            collect([$tenant->cidade, $tenant->uf])->filter()->implode('/'),
            $tenant->telefone,
        ])->filter()->implode(' · ');
    }
}
