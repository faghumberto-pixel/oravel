<?php

namespace App\Filament\Pages;

use App\Models\FrotaSaidaVeiculo;
use App\Services\Frota\KmRodadoService;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Gate;

/** Km rodado da frota: total por veículo (histórico de odômetro) e o que cada percurso recebeu das viagens. */
class KmRodadoFrota extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';

    protected static ?string $navigationGroup = 'Logística';

    protected static ?string $navigationParentItem = 'Frota';

    protected static ?string $navigationLabel = 'Km rodado';

    protected static ?int $navigationSort = 7;

    protected static ?string $title = 'Km rodado da frota';

    protected static ?string $slug = 'km-rodado-frota';

    protected static string $view = 'filament.pages.km-rodado-frota';

    public string $periodo = 'mes';

    public static function canAccess(): bool
    {
        return Gate::allows('viewAny', FrotaSaidaVeiculo::class);
    }

    public function dados(): array
    {
        [$de, $ate, $rotulo] = KmRodadoService::periodo($this->periodo);
        $servico = app(KmRodadoService::class);

        return [
            'rotulo' => $rotulo,
            'veiculos' => $servico->frota($de, $ate),
            'percursos' => $servico->porPercurso($de, $ate),
            'periodos' => KmRodadoService::periodos(),
        ];
    }
}
