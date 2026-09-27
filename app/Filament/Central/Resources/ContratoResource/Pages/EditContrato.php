<?php

namespace App\Filament\Central\Resources\ContratoResource\Pages;

use App\Filament\Central\Resources\ContratoResource;
use App\Models\Plan;
use App\Models\Tenant;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditContrato extends EditRecord
{
    protected static string $resource = ContratoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Bloqueio real (2026-09-27, achado em PROD): o Contrato da
            // própria Oravel (PREMIUM, tenant "Oravel" sem plano nenhum
            // pra vender) foi apagado por aqui sem nenhum aviso -- Tenant
            // ficou com plan_id vazio e ZERO módulos até ser corrigido
            // manualmente. Apagar um Contrato que um Tenant real usa
            // sempre quebra o acesso dele por inteiro (Plan não tem
            // SoftDeletes, é definitivo). Agora bloqueia com aviso
            // explicando qual(is) tenant(s) seriam afetados, em vez de
            // deixar apagar silenciosamente.
            Actions\DeleteAction::make()
                ->before(function (Plan $record, Actions\DeleteAction $action) {
                    $tenants = Tenant::withoutGlobalScope('tenant')
                        ->where('plan_id', $record->id)
                        ->pluck('name');

                    if ($tenants->isNotEmpty()) {
                        Notification::make()
                            ->title('Não é possível apagar este Contrato')
                            ->body('Ele está em uso por: '.$tenants->implode(', ').'. Apagar removeria o acesso desses tenants por completo (sem como desfazer). Troque o Contrato deles pra outro antes, se realmente precisar apagar este.')
                            ->danger()
                            ->persistent()
                            ->send();

                        $action->cancel();
                    }
                }),
        ];
    }

    /**
     * Espelho de CreateContrato::mutateFormDataBeforeCreate() -- mescla os
     * campos por grupo de volta num único 'features' antes de salvar.
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['price'] = $data['base_price'] ?? 0;
        $data['features'] = CreateContrato::mergeGroupedFeatures($data);

        return array_filter($data, fn ($key) => ! str_starts_with($key, 'features_group_'), ARRAY_FILTER_USE_KEY);
    }

    /**
     * Sentido contrário: ao abrir o contrato pra editar, distribui o
     * 'features' salvo (mapa `key => true`) de volta pros campos
     * 'features_group_{grupo}' que o formulário realmente usa -- sem
     * isso, os checkboxes por grupo sempre abririam todos desmarcados,
     * mesmo com módulos já selecionados. Aceita também o formato antigo
     * (lista simples de chaves, sem valor booleano) só pra não quebrar um
     * Contrato salvo antes do fix de 2026-09-24 -- ver
     * CreateContrato::mergeGroupedFeatures().
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $raw = $data['features'] ?? [];
        if (! is_array($raw)) {
            $raw = [];
        }

        $selected = array_is_list($raw)
            ? collect($raw)
            : collect($raw)->filter(fn ($v) => $v === true)->keys();

        foreach (ContratoResource::groupedFeatureOptions() as $groupName => $options) {
            $keys = array_keys($options);
            $data[ContratoResource::groupFieldName($groupName)] = $selected->intersect($keys)->values()->all();
        }

        return $data;
    }
}
