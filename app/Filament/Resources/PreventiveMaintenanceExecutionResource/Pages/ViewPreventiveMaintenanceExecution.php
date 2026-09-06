<?php

namespace App\Filament\Resources\PreventiveMaintenanceExecutionResource\Pages;

use App\Filament\Concerns\HasRecordPrintAction;
use App\Filament\Resources\PreventiveMaintenanceExecutionResource;
use Filament\Actions;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;

class ViewPreventiveMaintenanceExecution extends ViewRecord
{
    use HasRecordPrintAction;

    protected static string $resource = PreventiveMaintenanceExecutionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->printAction(),
            Actions\EditAction::make(),
        ];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\Section::make('Execução')->schema([
                Infolists\Components\TextEntry::make('asset.name')->label('Ativo'),
                Infolists\Components\TextEntry::make('asset.checklistGroup.name')->label('Grupo'),
                Infolists\Components\TextEntry::make('maintenancePlan.name')->label('Item de Preventiva'),
                Infolists\Components\TextEntry::make('maintenanceOrder.os_number')->label('OS Vinculada'),
                Infolists\Components\TextEntry::make('technician.name')->label('Técnico Responsável'),
                Infolists\Components\TextEntry::make('horimetro_at_execution')->label('Horímetro na Execução')->numeric(2),
                Infolists\Components\TextEntry::make('next_due_horimetro')->label('Próxima Previsão')->numeric(2),
                Infolists\Components\TextEntry::make('created_at')->label('Registrado em')->dateTime('d/m/Y H:i'),
            ])->columns(2),

            Infolists\Components\Section::make('Estado da Máquina')->schema([
                Infolists\Components\TextEntry::make('observacao')->label('Observações')->columnSpanFull(),
            ]),

            Infolists\Components\Section::make('Evidências Fotográficas')->schema([
                Infolists\Components\ViewEntry::make('photos')
                    ->label('')
                    ->view('filament.infolists.equipment-damage-photos'),
            ]),
        ]);
    }
}
