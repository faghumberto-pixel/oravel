<?php

namespace App\Filament\Central\Resources\TenantResource\Pages;

use App\Filament\Central\Resources\TenantResource;
use App\Services\AsaasService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditTenant extends EditRecord
{
    protected static string $resource = TenantResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('sync_asaas')
                ->label('Sincronizar com Asaas')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->action(function () {
                    app(AsaasService::class)->syncTenantCustomer($this->record);
                    $this->record->refresh();

                    match ($this->record->asaas_status) {
                        'synced' => Notification::make()->title('Sincronizado com a Asaas.')->success()->send(),
                        'pending' => Notification::make()->title('Sincronização adiada')->body('Preencha o CPF/CNPJ do tenant antes de sincronizar.')->warning()->send(),
                        default => Notification::make()->title('Falha ao sincronizar com a Asaas')->body('Veja os logs da aplicação para detalhes.')->danger()->send(),
                    };
                }),
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (isset($data['features']) && ! is_array($data['features'])) {
            $data['features'] = [];
        }

        return $data;
    }

    /**
     * Mesma correcao aplicada em PlanResource\Pages\EditPlan -- tenants com
     * override aditivo gravado no formato antigo (dicionario {chave: true})
     * fariam o CheckboxList marcar todas as opcoes e o toggle de uma afetar
     * todas (in_array() com comparacao frouxa contra puros `true`).
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        if (isset($data['features']) && is_array($data['features']) && ! array_is_list($data['features'])) {
            $data['features'] = collect($data['features'])
                ->filter(fn ($value) => $value === true || $value === 1 || $value === '1' || $value === 'true')
                ->keys()
                ->values()
                ->all();
        }

        return $data;
    }
}
