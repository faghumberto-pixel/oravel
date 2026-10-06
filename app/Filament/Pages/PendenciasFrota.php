<?php

namespace App\Filament\Pages;

use App\Models\Asset;
use App\Models\MaintenanceOrder;
use App\Services\Frota\PendenciasFrotaService;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/** Tudo o que a frota precisa de atenção, numa lista só, com o botão de abrir a OS. */
class PendenciasFrota extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-exclamation-triangle';

    protected static ?string $navigationGroup = 'Gestão de Frota';

    protected static ?string $navigationLabel = 'Pendências da Frota';

    protected static ?int $navigationSort = 1;

    protected static ?string $title = 'Pendências da Frota';

    protected static ?string $slug = 'frota-pendencias';

    protected static string $view = 'filament.pages.pendencias-frota';

    public ?string $filtroCategoria = null;

    public ?string $filtroGravidade = null;

    public ?string $filtroVeiculo = null;

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can('viewAny', Asset::class);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public static function getNavigationBadge(): ?string
    {
        if (! static::canAccess()) {
            return null;
        }
        $n = app(PendenciasFrotaService::class)->contar();

        return $n > 0 ? (string) $n : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return app(PendenciasFrotaService::class)->criticas() > 0 ? 'danger' : 'warning';
    }

    /** @return Collection<int, array<string, mixed>> */
    public function pendencias(): Collection
    {
        return app(PendenciasFrotaService::class)->todas()
            ->when($this->filtroCategoria, fn ($c) => $c->where('categoria', $this->filtroCategoria))
            ->when($this->filtroGravidade, fn ($c) => $c->where('gravidade', $this->filtroGravidade))
            ->when($this->filtroVeiculo, fn ($c) => $c->where('ativo_id', $this->filtroVeiculo))
            ->values();
    }

    /** @return array<string, string> */
    public function veiculos(): array
    {
        return Asset::opcoesVeiculos();
    }

    public function gerarOs(string $chave): void
    {
        Gate::authorize('create', MaintenanceOrder::class);
        $pendencia = app(PendenciasFrotaService::class)->todas()->firstWhere('chave', $chave);

        if (! $pendencia) {
            Notification::make()->title('Esta pendência já foi resolvida.')->warning()->send();

            return;
        }

        try {
            $os = app(PendenciasFrotaService::class)->gerarOs($pendencia);
            Notification::make()->title('OS '.$os->os_number.' aberta')->success()->send();
        } catch (ValidationException $e) {
            Notification::make()->title(collect($e->errors())->flatten()->first())->danger()->send();
        }
    }
}
