<?php

namespace App\Filament\Central\Resources\RoleResource\Pages;

use App\Filament\Central\Resources\RoleResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditRole extends EditRecord
{
    protected static string $resource = RoleResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        return RoleResource::mutateFormDataBeforeFill($data);
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $record->update([
            'name' => $data['name'],
        ]);

        // getRawState() inclui campos dehydrated(false) — $data nao inclui.
        $state = $this->form->getRawState();
        $names = [];
        foreach (RoleResource::existingPermissionNames() as $permName) {
            if (! empty($state["perm_{$permName}"])) {
                $names[] = $permName;
            }
        }
        $record->syncPermissions($names);

        return $record;
    }
}
