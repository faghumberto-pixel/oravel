<?php

namespace App\Filament\Resources\MaintenancePlanResource\Pages;

use App\Filament\Concerns\HasPrintAction;
use App\Filament\Exports\MaintenancePlanExporter;
use App\Filament\Resources\MaintenancePlanResource;
use App\Models\ChecklistGroup;
use App\Models\MaintenancePlan;
use App\Models\PmpEquipmentFamily;
use App\Support\Tenancy;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListMaintenancePlans extends ListRecords
{
    use HasPrintAction;

    protected static string $resource = MaintenancePlanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
            $this->importFamilyTemplateAction(),
            $this->printAction(),
            Actions\ExportAction::make()->exporter(MaintenancePlanExporter::class),
        ];
    }

    /**
     * Pedido do usuário 2026-08-26/27: catálogo global de templates de PMP
     * por família de equipamento (App\Models\PmpEquipmentFamily, painel
     * central), importável e editável por qualquer tenant. Import é cópia
     * pontual (MaintenancePlan::importFromFamilyTemplate()), não link vivo
     * -- depois de importado, o tenant edita/exclui pela tela normal.
     */
    private function importFamilyTemplateAction(): Actions\Action
    {
        return Actions\Action::make('importFamilyTemplate')
            ->label('Importar Template PMP')
            ->icon('heroicon-o-arrow-down-tray')
            ->color('gray')
            ->form([
                Forms\Components\Select::make('pmp_equipment_family_id')
                    ->label('Família de Equipamento')
                    ->options(fn () => PmpEquipmentFamily::query()->pluck('name', 'id'))
                    ->helperText(fn (?string $state) => $state
                        ? PmpEquipmentFamily::find($state)?->templateItems()->count().' itens de manutenção prontos nesta família.'
                        : 'Catálogo mantido pela Oravel -- itens de manutenção prontos por família de equipamento.')
                    ->required()
                    ->live(),

                Forms\Components\Select::make('checklist_group_id')
                    ->label('Grupo de Ativo (destino)')
                    ->options(fn () => ChecklistGroup::where('tenant_id', Tenancy::current()?->id)->pluck('name', 'id'))
                    ->createOptionForm([
                        Forms\Components\TextInput::make('name')->label('Nome do Grupo')->required(),
                    ])
                    ->createOptionUsing(fn (array $data) => ChecklistGroup::create([
                        'tenant_id' => Tenancy::current()?->id,
                        'name' => $data['name'],
                    ])->id)
                    ->required()
                    ->helperText('Os itens importados valem para todo Ativo deste grupo.'),
            ])
            ->action(function (array $data) {
                $family = PmpEquipmentFamily::with(['templateItems', 'checklistItems'])->findOrFail($data['pmp_equipment_family_id']);
                $group = ChecklistGroup::where('tenant_id', Tenancy::current()?->id)
                    ->findOrFail($data['checklist_group_id']);

                $imported = MaintenancePlan::importFromFamilyTemplate($family, $group);
                $checklist = MaintenancePlan::importChecklistFromFamilyTemplate($family, $group);

                Notification::make()
                    ->title('Template importado')
                    ->body($imported->count().' itens de manutenção e '.$checklist->count().' itens de checklist disponíveis no grupo "'.$group->name.'".')
                    ->success()
                    ->send();
            });
    }

    protected function getHeaderWidgets(): array
    {
        return [
            MaintenancePlanResource\Widgets\MaintenancePlanStats::class,
        ];
    }

    public function getHeaderWidgetsColumns(): int|string|array
    {
        return 4;
    }
}
