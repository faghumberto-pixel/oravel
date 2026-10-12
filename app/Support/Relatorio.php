<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;

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

    /** Logotipo do tenant embutido (data URI): funciona em tela, impressão e PDF sem depender de URL/storage:link. */
    public static function logo(): ?string
    {
        $path = Tenancy::current()?->logo_path;

        if (! $path || ! Storage::disk('public')->exists($path)) {
            return null;
        }

        $mime = Storage::disk('public')->mimeType($path) ?: 'image/png';

        return 'data:'.$mime.';base64,'.base64_encode(Storage::disk('public')->get($path));
    }

    public static function usuario(): string
    {
        return (string) (auth()->user()?->name ?? '');
    }

    /** Aceita array ou texto; devolve só os filtros preenchidos. @return array<int, string> */
    public static function filtros(array|string|null $filtros): array
    {
        return collect(is_array($filtros) ? $filtros : [$filtros])->filter(fn ($f) => filled($f))->map(fn ($f) => (string) $f)->values()->all();
    }
}
