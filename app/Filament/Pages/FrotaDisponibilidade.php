<?php

namespace App\Filament\Pages;

use App\Models\Asset;
use App\Models\AssetDowntimeEvent;
use App\Services\Frota\DisponibilidadeFrotaService;
use Filament\Pages\Page;
use Illuminate\Support\Collection;

/** Disponibilidade da frota: % do tempo em que cada veículo esteve disponível (sem parada por quebra, manutenção ou sinistro). */
class FrotaDisponibilidade extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-signal';

    protected static ?string $navigationGroup = 'Gestão de Frota';

    protected static ?string $navigationLabel = 'Disponibilidade';

    protected static ?int $navigationSort = 17;

    protected static ?string $title = 'Disponibilidade da frota';

    protected static ?string $slug = 'frota-disponibilidade';

    protected static string $view = 'filament.pages.frota-disponibilidade';

    public int $meses = 3;

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can('viewAny', AssetDowntimeEvent::class);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    /** @return Collection<int, array<string, mixed>> do menos disponível para o mais disponível */
    public function linhas(): Collection
    {
        $meses = in_array($this->meses, [1, 3, 6, 12], true) ? $this->meses : 3;
        $servico = new DisponibilidadeFrotaService;

        return Asset::query()->where('grupo', Asset::GRUPO_VEICULO)->orderBy('placa')->get()
            ->map(fn (Asset $v) => ['veiculo' => $v] + $servico->veiculo($v, $meses))
            ->sortBy('disponibilidade')->values();
    }

    public function mediaDaFrota(Collection $linhas): ?float
    {
        return $linhas->isEmpty() ? null : round((float) $linhas->avg('disponibilidade'), 1);
    }
}
