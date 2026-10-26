<?php

namespace App\Support;

use Closure;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Schema;

/**
 * Regra de servidor para todo Select/CheckboxList do Filament que escolhe
 * registros por ->relationship(): o id que chega do navegador precisa existir
 * DENTRO do escopo do tenant do usuario.
 *
 * Antes (achado em 2026-10-22, varredura de 109 campos) o Filament so listava as
 * opcoes do tenant na tela e nao validava o valor recebido: uma requisicao forjada
 * vinculava registro de outro tenant (departamento, usuario, ativo, cliente...).
 *
 * O escopo vem do proprio model relacionado (global scope `tenant` do
 * BelongsToTenant). Model sem esse escopo mas com coluna tenant_id e' filtrado
 * explicitamente. Super admin e usuario sem tenant passam (mesmo bypass do escopo).
 * Na edicao, o valor que ja estava salvo no registro e' aceito mesmo que tenha
 * saido do escopo (ex.: item inativado), para nao travar edicoes alheias ao campo.
 *
 * Registrada em AppServiceProvider::boot() via configureUsing.
 */
class TenantScopedRelationshipRule
{
    public static function register(): void
    {
        foreach ([Select::class, CheckboxList::class] as $campo) {
            $campo::configureUsing(
                fn (Select|CheckboxList $componente) => $componente->rule(
                    fn ($component): Closure => self::regra($component)
                )
            );
        }
    }

    public static function regra(Select|CheckboxList $componente): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($componente): void {
            // hasRelationship() so existe no Select; CheckboxList::getRelationship() devolve null sem relacao.
            if ($componente instanceof Select && ! $componente->hasRelationship()) {
                return;
            }

            $user = auth()->user();
            if (! $user || $user->isSuperAdmin() || blank($user->tenant_id)) {
                return;
            }

            $relacao = $componente->getRelationship();
            if (! $relacao) {
                return;
            }

            $ids = collect(is_array($value) ? $value : [$value])->filter(fn ($id) => filled($id))->unique()->values();
            if ($ids->isEmpty()) {
                return;
            }

            $registro = $componente->getRecord();
            if ($registro && $relacao instanceof BelongsTo && $ids->count() === 1
                && (string) $registro->getAttribute($relacao->getForeignKeyName()) === (string) $ids->first()) {
                return;
            }

            $related = $relacao->getRelated();
            $consulta = $related->newQuery()->whereIn($related->getQualifiedKeyName(), $ids->all());

            if (! $related->hasGlobalScope('tenant') && Schema::hasColumn($related->getTable(), 'tenant_id')) {
                $consulta->where($related->qualifyColumn('tenant_id'), $user->tenant_id);
            }

            if ($consulta->count() !== $ids->count()) {
                $fail('O item selecionado não é válido.');
            }
        };
    }
}
