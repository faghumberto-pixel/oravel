<?php

namespace App\Filament\Pages;

use App\Filament\Resources\MaterialRequestResource;
use App\Models\InternalUnit;
use App\Models\MaterialLocationStock;
use App\Models\MaterialRequest;
use Filament\Notifications\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Gap real: MaterialRequest ja suportava requisicao sem OS
 * (maintenance_order_id nullable + requested_for_location_id), mas so'
 * existia reativamente -- uma notificacao por material abaixo do minimo
 * (MaterialStockService::notifyIfLowStock()). Esta pagina e' o jeito
 * proativo: almoxarifado escolhe uma filial, ve tudo que esta abaixo do
 * minimo de uma vez, ajusta quantidade, e gera 1 unica Requisicao com N
 * itens (origin=reposicao_estoque).
 */
class RequisicaoReposicaoEstoque extends Page
{
    // Navegacao manual dentro do submenu "Almoxarifado" (AdminPanelProvider),
    // nao auto-registrada -- mesmo padrao de MaterialRequestResource etc.
    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-path-rounded-square';

    protected static ?string $navigationGroup = 'Ativos e Materiais';

    protected static ?string $navigationLabel = 'Reposição de Estoque';

    protected static ?string $title = 'Reposição de Estoque';

    protected static ?string $slug = 'requisicao-reposicao-estoque';

    protected static string $view = 'filament.pages.requisicao-reposicao-estoque';

    public ?string $internalUnitId = null;

    /** @var array<string, array{selected: bool, quantity: int}> */
    public array $selections = [];

    public function mount(): void
    {
        $this->internalUnitId = InternalUnit::query()->orderBy('name')->value('id');
        $this->syncSelections();
    }

    public static function canAccess(): bool
    {
        return MaterialRequestResource::canCreate();
    }

    public function getInternalUnitsProperty(): Collection
    {
        return InternalUnit::query()->orderBy('name')->get();
    }

    /**
     * @return Collection<int, MaterialLocationStock>
     */
    public function getLowStockRowsProperty(): Collection
    {
        if (! $this->internalUnitId) {
            return collect();
        }

        return MaterialLocationStock::query()
            ->where('internal_unit_id', $this->internalUnitId)
            ->whereColumn('current_quantity', '<=', 'minimum_threshold')
            ->with('material')
            ->get();
    }

    public function updatedInternalUnitId(): void
    {
        $this->syncSelections();
    }

    private function syncSelections(): void
    {
        $this->selections = $this->lowStockRows->mapWithKeys(function (MaterialLocationStock $stock) {
            $suggested = $stock->maximum_threshold
                ? max($stock->maximum_threshold - $stock->current_quantity, 1)
                : max(($stock->minimum_threshold * 2) - $stock->current_quantity, 1);

            return [$stock->material_id => ['selected' => true, 'quantity' => $suggested]];
        })->all();
    }

    public function gerarRequisicao(): void
    {
        $unit = InternalUnit::find($this->internalUnitId);

        if (! $unit) {
            Notification::make()->title('Selecione uma unidade.')->warning()->send();

            return;
        }

        $itens = collect($this->selections)->filter(fn (array $row) => $row['selected'] && $row['quantity'] > 0);

        if ($itens->isEmpty()) {
            Notification::make()->title('Selecione ao menos um material.')->warning()->send();

            return;
        }

        $materialRequest = DB::transaction(function () use ($unit, $itens) {
            $materialRequest = MaterialRequest::create([
                'tenant_id' => $unit->tenant_id,
                'user_id' => auth()->id(),
                'origin' => MaterialRequest::ORIGIN_REPOSICAO_ESTOQUE,
                'requested_for_location_id' => $unit->id,
                'status' => MaterialRequest::STATUS_RASCUNHO,
                'requested_at' => now(),
                'notes' => "Reposição de estoque gerada pelo almoxarifado para {$unit->name}.",
            ]);

            foreach ($itens as $materialId => $row) {
                $materialRequest->items()->create([
                    'material_id' => $materialId,
                    'quantity' => $row['quantity'],
                ]);
            }

            return $materialRequest;
        });

        $this->syncSelections();

        Notification::make()
            ->title('Requisição de reposição criada')
            ->success()
            ->actions([
                Action::make('ver')
                    ->button()
                    ->url(MaterialRequestResource::getUrl('edit', ['record' => $materialRequest])),
            ])
            ->send();
    }
}
