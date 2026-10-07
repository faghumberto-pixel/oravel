<?php

namespace App\Filament\Pages;

use App\Models\Asset;
use App\Models\MaintenanceOrder;
use App\Services\Frota\PendenciasFrotaService;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
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
        $usuario = auth()->user();

        return (bool) ($usuario?->can('viewAny', Asset::class) && app(PendenciasFrotaService::class)->temModuloFrota($usuario));
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
        [$n] = static::contagem();

        return $n > 0 ? (string) $n : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return (static::contagem()[1] ?? 0) > 0 ? 'danger' : 'warning';
    }

    /**
     * Total e críticas do contador do menu, guardados por 60 s por usuário (o cálculo percorre todos os veículos
     * e roda em toda tela do painel).
     *
     * @return array{0: int, 1: int}
     */
    protected static function contagem(): array
    {
        $usuario = auth()->user();
        if (! $usuario) {
            return [0, 0];
        }

        return Cache::remember('frota-pendencias-contagem:'.$usuario->id, 60, function () use ($usuario) {
            $lista = app(PendenciasFrotaService::class)->todasPermitidas($usuario);

            return [$lista->count(), $lista->where('gravidade', PendenciasFrotaService::CRITICA)->count()];
        });
    }

    /** @return Collection<int, array<string, mixed>> */
    public function pendencias(): Collection
    {
        return app(PendenciasFrotaService::class)->todasPermitidas(auth()->user())
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
        $pendencia = app(PendenciasFrotaService::class)->todasPermitidas(auth()->user())->firstWhere('chave', $chave);

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
