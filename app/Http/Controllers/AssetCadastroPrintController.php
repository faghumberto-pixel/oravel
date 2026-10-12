<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/** Ficha de cadastro do ativo, pronta para Ctrl+P (mesmo padrão das outras impressões; sem PDF). */
class AssetCadastroPrintController extends Controller
{
    public function show(Request $request, string $asset)
    {
        // BelongsToTenant já restringe ao tenant do usuário; outro tenant vira 404.
        $asset = Asset::with(['category', 'internalUnit', 'storageLocation'])->findOrFail($asset);

        abort_unless($request->user()?->can('view', $asset), 403);

        $data = fn ($v) => $v ? Carbon::parse($v)->format('d/m/Y') : null;
        $money = fn ($v) => filled($v) ? 'R$ '.number_format((float) $v, 2, ',', '.') : null;

        $secoes = [
            'Identificação' => [
                'Descrição' => $asset->name,
                'Nº Patrimônio' => $asset->patrimonio,
                'TAG/Código' => $asset->tag,
                'Nº de série' => $asset->serial_number,
                'Categoria' => $asset->category?->name ?? $asset->asset_category,
                'Fabricante' => $asset->fabricante,
                'Modelo' => $asset->modelo,
                'Ano de fabricação' => $asset->manufacturing_year,
                'Status' => $asset->status,
                'Criticidade' => $asset->criticality_level ?? $asset->criticality,
            ],
            'Dados técnicos' => [
                'Capacidade' => filled($asset->capacity_value) ? trim($asset->capacity_value.' '.$asset->capacity_unit) : null,
                'Especificação' => $asset->specification,
                'Horímetro inicial' => $asset->horimetro_inicial,
                'Horímetro atual' => $asset->last_horimetro,
                'Odômetro atual' => $asset->odometro_atual,
            ],
            'Aquisição' => [
                'Data de aquisição' => $data($asset->acquisition_date),
                'Valor de aquisição' => $money($asset->acquisition_value),
                'Valor residual' => $money($asset->residual_value),
                'Vida útil (anos)' => $asset->useful_life_years,
            ],
            'Veículo' => [
                'Placa' => $asset->placa,
                'Chassi' => $asset->chassi,
                'RENAVAM' => $asset->renavam,
                'Cor' => $asset->cor,
                'Ano do modelo' => $asset->ano_modelo,
                'Carroceria' => trim(($asset->carroceria_tipo ?? '').' '.($asset->carroceria_detalhe ?? '')) ?: null,
                'Quantidade de eixos' => $asset->quantidade_eixos,
                'Vencimento do licenciamento' => $data($asset->licenciamento_vencimento),
                'Vencimento do IPVA' => $data($asset->ipva_vencimento),
                'Nº do tacógrafo' => $asset->tacografo_numero,
                'Vencimento do tacógrafo' => $data($asset->tacografo_vencimento),
            ],
            'Seguro' => [
                'Seguradora' => $asset->seguro_seguradora,
                'Nº da apólice' => $asset->seguro_apolice,
                'Vencimento' => $data($asset->seguro_vencimento),
                'Valor de cobertura' => $money($asset->seguro_valor_cobertura),
                'Cobertura de terceiros' => $money($asset->seguro_cobertura_terceiros),
                'Franquia de colisão' => $money($asset->seguro_franquia_colisao),
                'Franquia de vidros' => $money($asset->seguro_franquia_vidros),
            ],
            'Localização' => [
                'Unidade' => $asset->internalUnit?->name,
                'Local de armazenagem' => $asset->storageLocation?->name,
                'Endereço' => $asset->endereco,
                'CEP' => $asset->cep,
            ],
        ];

        // Só mostra linhas preenchidas e seções que sobraram com algo.
        $secoes = collect($secoes)
            ->map(fn ($linhas) => collect($linhas)->filter(fn ($v) => filled($v))->all())
            ->filter()
            ->all();

        return view('print.asset-cadastro', [
            'asset' => $asset,
            'secoes' => $secoes,
            'descricao' => $asset->description,
        ]);
    }
}
