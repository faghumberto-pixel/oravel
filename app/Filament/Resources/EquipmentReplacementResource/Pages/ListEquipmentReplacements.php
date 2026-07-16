<?php

namespace App\Filament\Resources\EquipmentReplacementResource\Pages;

use App\Filament\Resources\EquipmentReplacementResource;
use App\Models\Asset;
use App\Models\EquipmentReplacement;
use App\Support\Tenancy;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListEquipmentReplacements extends ListRecords
{
    protected static string $resource = EquipmentReplacementResource::class;

    /**
     * Terceira entrada da Troca (alem de OS e Avaria, ver docblock do
     * Resource) -- caso comercial/gerente direto, sem gatilho previo.
     */
    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('nova_requisicao')
                ->label('Nova Requisição de Troca')
                ->icon('heroicon-o-plus')
                ->form([
                    Forms\Components\Select::make('original_asset_id')
                        ->label('Ativo Original')
                        ->relationship('originalAsset', 'name', fn (Builder $query) => $query->where('tenant_id', Tenancy::current()?->id))
                        ->getOptionLabelFromRecordUsing(fn (Asset $record) => "{$record->patrimonio} — {$record->name}")
                        ->searchable(['name', 'patrimonio'])
                        ->preload()
                        ->required(),
                    Forms\Components\Select::make('urgency')
                        ->label('Urgência')
                        ->options([
                            EquipmentReplacement::URGENCY_NORMAL => 'Normal',
                            EquipmentReplacement::URGENCY_URGENTE => 'Urgente',
                            EquipmentReplacement::URGENCY_CRITICO => 'Crítico',
                        ])
                        ->default(EquipmentReplacement::URGENCY_NORMAL)
                        ->required()
                        ->native(false),
                    Forms\Components\Textarea::make('reason')
                        ->label('Motivo')
                        ->required()
                        ->columnSpanFull(),
                ])
                ->action(function (array $data) {
                    $replacement = EquipmentReplacement::create([
                        'tenant_id' => Tenancy::current()?->id,
                        'original_asset_id' => $data['original_asset_id'],
                        'urgency' => $data['urgency'],
                        'reason' => $data['reason'],
                        'requested_by_user_id' => auth()->id(),
                    ]);

                    Notification::make()->title('Requisição de troca criada')->success()->send();

                    $this->redirect(EquipmentReplacementResource::getUrl('view', ['record' => $replacement]));
                }),
        ];
    }
}
