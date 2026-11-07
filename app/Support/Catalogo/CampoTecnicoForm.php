<?php

namespace App\Support\Catalogo;

use App\Models\AssetCategory;
use App\Models\CatalogoAtributo;
use App\Services\Catalogo\Aptidao\AptidaoGate;
use App\Support\Tenancy;
use Filament\Forms;
use Filament\Forms\Get;
use Illuminate\Support\Collection;

/**
 * G1 fase 4 -- campos tecnicos do GRUPO no cadastro do ativo. O formulario mostra SO os campos do grupo escolhido
 * (kVA no gerador, tabela de carga no guindaste...), vindos do catalogo do tenant, e grava em assets.atributos_tecnicos.
 * Campo obrigatorio incompleto = aviso na aptidao, nunca bloqueia o cadastro. Inerte com a chave do contrato desligada.
 */
class CampoTecnicoForm
{
    public static function secao(): Forms\Components\Section
    {
        return Forms\Components\Section::make('Campos técnicos do grupo')
            ->description('Aparecem só os campos do grupo escolhido. Campo obrigatório vazio gera aviso no cadastro, mas não impede de salvar.')
            ->visible(fn (Get $get) => self::deveMostrar($get('asset_category_id')))
            ->schema(fn (Get $get) => self::campos($get('asset_category_id')))
            ->columns(2);
    }

    public static function deveMostrar(?string $grupoId): bool
    {
        return filled($grupoId)
            && AptidaoGate::ligada(Tenancy::current()?->id ?? auth()->user()?->tenant_id)
            && self::atributos($grupoId)->isNotEmpty();
    }

    /** @return Collection<int, CatalogoAtributo> */
    public static function atributos(?string $grupoId)
    {
        if (blank($grupoId)) {
            return collect();
        }

        $grupo = AssetCategory::withoutGlobalScopes()->where('tenant_id', Tenancy::current()?->id ?? auth()->user()?->tenant_id)->find($grupoId);

        return $grupo
            ? CatalogoAtributo::withoutGlobalScopes()->where('tenant_id', $grupo->tenant_id)->where('grupo_id', $grupo->id)
                ->where('ativo', true)->whereNull('deleted_at')->orderBy('ordem')->get()
            : collect();
    }

    /** @return array<int, Forms\Components\Component> */
    public static function campos(?string $grupoId): array
    {
        return self::atributos($grupoId)->map(fn (CatalogoAtributo $a) => self::campo($a))->all();
    }

    public static function campo(CatalogoAtributo $a): Forms\Components\Component
    {
        $nome = 'atributos_tecnicos.'.$a->chave;
        $ajuda = $a->obrigatorio ? 'Obrigatório para o cadastro ficar completo (não bloqueia o salvamento).' : null;

        $campo = match ($a->tipo) {
            'numero' => Forms\Components\TextInput::make($nome)->numeric()->suffix($a->unidade),
            'selecao' => Forms\Components\Select::make($nome)->native(false)->options(collect((array) $a->opcoes)->mapWithKeys(fn ($o) => [$o => ucfirst((string) $o)])->all()),
            'booleano' => Forms\Components\Toggle::make($nome),
            'data' => Forms\Components\DatePicker::make($nome),
            default => Forms\Components\TextInput::make($nome)->maxLength(255),
        };

        return $campo->label($a->rotulo)->helperText($ajuda);
    }
}
