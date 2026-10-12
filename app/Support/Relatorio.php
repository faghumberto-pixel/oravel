<?php

namespace App\Support;

use App\Models\Tenant;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

/**
 * Identificação do emissor nos relatórios/PDFs: o nome do cliente (tenant)
 * aparece em destaque no cabeçalho; o nome Oravel fica só no rodapé, sem destaque.
 */
class Relatorio
{
    /** Tenant do relatório: o do usuário do painel ou, no portal do cliente (outro guard), o do cliente logado. */
    private static function tenant(): ?Tenant
    {
        return Tenancy::current() ?? Auth::guard('client')->user()?->tenant;
    }

    public static function emissor(): string
    {
        $tenant = self::tenant();

        return $tenant?->nome_fantasia ?: ($tenant?->name ?: 'Oravel');
    }

    /** Linha de apoio sob o nome: CNPJ/CPF e cidade, quando cadastrados. */
    public static function detalhe(): string
    {
        $tenant = self::tenant();

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
        $path = self::tenant()?->logo_path;

        if (! $path || ! Storage::disk('public')->exists($path)) {
            return null;
        }

        $mime = Storage::disk('public')->mimeType($path) ?: 'image/png';

        return 'data:'.$mime.';base64,'.base64_encode(Storage::disk('public')->get($path));
    }

    public static function usuario(): string
    {
        return (string) (auth()->user()?->name ?? Auth::guard('client')->user()?->name ?? '');
    }

    /** Aceita array ou texto; devolve só os filtros preenchidos. @return array<int, string> */
    public static function filtros(array|string|null $filtros): array
    {
        return collect(is_array($filtros) ? $filtros : [$filtros])->filter(fn ($f) => filled($f))->map(fn ($f) => (string) $f)->values()->all();
    }
}
