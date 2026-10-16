<?php

namespace App\Support;

use App\Models\CrmCampoCustom;
use Filament\Forms;
use Filament\Tables;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Única fonte dos campos personalizados do CRM: monta os componentes dos
 * formulários Filament, as colunas das tabelas e normaliza os valores (o
 * importador e a API das próximas fases usam o mesmo normalizar()).
 */
class CrmCamposCustom
{
    /** @var array<string, Collection<int, CrmCampoCustom>> */
    private static array $memo = [];

    public static function esquecer(): void
    {
        self::$memo = [];
    }

    /** Campos ativos do tenant atual para a entidade ('lead' | 'cliente'), na ordem definida. */
    public static function campos(string $entidade, bool $apenasAtivos = true, ?string $tenantId = null): Collection
    {
        $tenantId ??= Tenancy::current()?->id ?? auth()->user()?->tenant_id;

        // Sem tenant conhecido não há campos (nunca listar os de todos os tenants).
        if (! $tenantId) {
            return collect();
        }

        $chave = $tenantId.'|'.$entidade.'|'.(int) $apenasAtivos;

        return self::$memo[$chave] ??= CrmCampoCustom::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->paraEntidade($entidade)
            ->when($apenasAtivos, fn ($q) => $q->where('ativo', true))
            ->get();
    }

    /** @return array<int, Forms\Components\Component> */
    public static function componentes(string $entidade): array
    {
        return self::campos($entidade)->map(fn (CrmCampoCustom $campo) => self::componente($campo))->all();
    }

    public static function secao(string $entidade): ?Forms\Components\Section
    {
        $componentes = self::componentes($entidade);

        if ($componentes === []) {
            return null;
        }

        return Forms\Components\Section::make('Campos Personalizados')
            ->description('Campos criados pela sua empresa em Comercial → Campos Personalizados.')
            ->collapsible()
            ->columns(2)
            ->schema($componentes);
    }

    private static function componente(CrmCampoCustom $campo): Forms\Components\Component
    {
        $nome = 'custom.'.$campo->chave;

        $componente = match ($campo->tipo) {
            CrmCampoCustom::TIPO_TEXTO_LONGO => Forms\Components\Textarea::make($nome)->rows(3)->columnSpanFull(),
            CrmCampoCustom::TIPO_NUMERO => Forms\Components\TextInput::make($nome)->numeric(),
            CrmCampoCustom::TIPO_MOEDA => Forms\Components\TextInput::make($nome)->numeric()->prefix('R$'),
            CrmCampoCustom::TIPO_DATA => Forms\Components\DatePicker::make($nome),
            CrmCampoCustom::TIPO_SIM_NAO => Forms\Components\Toggle::make($nome),
            CrmCampoCustom::TIPO_LISTA => Forms\Components\Select::make($nome)->options($campo->opcoesLabels()),
            CrmCampoCustom::TIPO_LISTA_MULTIPLA => Forms\Components\Select::make($nome)->options($campo->opcoesLabels())->multiple(),
            default => Forms\Components\TextInput::make($nome)->maxLength(255),
        };

        $componente->label($campo->nome);

        // Sim/Não nunca é "vazio": o obrigatório só vale para os demais tipos.
        if ($campo->obrigatorio && $campo->tipo !== CrmCampoCustom::TIPO_SIM_NAO) {
            $componente->required();
        }

        return $componente;
    }

    /** @return array<int, Tables\Columns\Column> colunas ocultas por padrão, ativáveis no seletor de colunas. */
    public static function colunas(string $entidade): array
    {
        return self::campos($entidade)->map(function (CrmCampoCustom $campo) {
            $coluna = Tables\Columns\TextColumn::make('custom.'.$campo->chave)
                ->label($campo->nome)
                ->toggleable(isToggledHiddenByDefault: true)
                ->placeholder('—');

            return match ($campo->tipo) {
                CrmCampoCustom::TIPO_DATA => $coluna->date('d/m/Y'),
                CrmCampoCustom::TIPO_MOEDA => $coluna->money('BRL'),
                CrmCampoCustom::TIPO_SIM_NAO => $coluna->formatStateUsing(fn ($state) => $state === null || $state === '' ? null : ($state ? 'Sim' : 'Não')),
                CrmCampoCustom::TIPO_LISTA_MULTIPLA => $coluna->formatStateUsing(fn ($state) => is_array($state) ? implode(', ', $state) : $state),
                default => $coluna,
            };
        })->all();
    }

    /**
     * Mantém só chaves conhecidas (de qualquer campo do tenant, ativo ou não,
     * para não apagar valor de campo desativado) e converte pelo tipo. Valor
     * inválido para o tipo é descartado.
     *
     * @return array<string, mixed>
     */
    public static function normalizar(string $entidade, ?array $dados, ?string $tenantId = null): array
    {
        $campos = self::campos($entidade, false, $tenantId)->keyBy('chave');
        $saida = [];

        foreach ((array) $dados as $chave => $valor) {
            if (! $campos->has($chave)) {
                continue;
            }

            $convertido = self::converter($campos[$chave], $valor);

            if ($convertido !== null) {
                $saida[$chave] = $convertido;
            }
        }

        return $saida;
    }

    private static function converter(CrmCampoCustom $campo, mixed $valor): mixed
    {
        if ($valor === null || $valor === '' || $valor === []) {
            return null;
        }

        return match ($campo->tipo) {
            CrmCampoCustom::TIPO_NUMERO, CrmCampoCustom::TIPO_MOEDA => is_numeric($valor) ? $valor + 0 : null,
            CrmCampoCustom::TIPO_SIM_NAO => filter_var($valor, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE),
            CrmCampoCustom::TIPO_DATA => self::data($valor),
            CrmCampoCustom::TIPO_LISTA => array_key_exists((string) $valor, $campo->opcoesLabels()) ? (string) $valor : null,
            CrmCampoCustom::TIPO_LISTA_MULTIPLA => ($v = array_values(array_intersect((array) $valor, array_keys($campo->opcoesLabels())))) ? $v : null,
            default => is_scalar($valor) ? trim((string) $valor) : null,
        };
    }

    private static function data(mixed $valor): ?string
    {
        try {
            return Carbon::parse((string) $valor)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }
}
