<?php

namespace App\Filament\Central\Resources\TenantResource\Pages;

use App\Filament\Central\Resources\ContratoResource;
use App\Filament\Central\Resources\ContratoResource\Pages\CreateContrato;
use App\Filament\Central\Resources\TenantResource;
use App\Models\Tenant;
use App\Services\AsaasService;
use App\Services\ComplementoService;
use Filament\Actions;
use Filament\Forms;
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
            Actions\Action::make('charge_implementation')
                ->label('Cobrar implantação')
                ->icon('heroicon-o-banknotes')
                ->color('warning')
                ->visible(fn () => $this->record->implementationAmount() > 0 && $this->record->implementationBillingMode() === 'separada' && ! $this->record->hasActiveImplementationCharge())
                ->requiresConfirmation()
                ->modalDescription(fn () => 'Gera '.($this->record->implementationInstallments() === 1 ? 'uma cobrança única' : $this->record->implementationInstallments().' cobranças (parcelas)').' de implantação na Asaas para '.$this->record->name.', totalizando R$ '.number_format($this->record->implementationAmount(), 2, ',', '.').'.')
                ->action(function () {
                    $urls = app(AsaasService::class)->chargeTenantImplementation($this->record);
                    $this->record->refresh();

                    $urls
                        ? Notification::make()->title('Cobrança de implantação gerada')->body(implode("\n", $urls))->success()->persistent()->send()
                        : Notification::make()->title('Não foi possível gerar a cobrança')->body('Confira se o tenant está sincronizado com a Asaas (CPF/CNPJ) e se há valor de implantação. Veja os logs para detalhes.')->danger()->send();
                }),
            $this->modulosDoContratoAction(),
            Actions\DeleteAction::make(),
        ];
    }

    /**
     * Mesma lista de módulos da tela de Contratos, editada daqui: lê e grava o
     * PRÓPRIO contrato (Plan) da empresa, sem cópia -- o que se muda aqui aparece
     * em Contratos e vice-versa. Complementos ficam como estão (vendidos à parte).
     */
    protected function modulosDoContratoAction(): Actions\Action
    {
        return Actions\Action::make('modulos_do_contrato')
            ->label('Módulos do contrato')
            ->icon('heroicon-o-squares-plus')
            ->color('info')
            ->visible(fn () => $this->record->plan !== null)
            ->modalWidth('7xl')
            ->modalDescription(function () {
                $outros = Tenant::withoutGlobalScope('tenant')
                    ->where('plan_id', $this->record->plan_id)
                    ->whereKeyNot($this->record->getKey())
                    ->pluck('name');

                return 'Contrato: '.$this->record->plan->name.'. É o mesmo contrato da tela Contratos: a mudança vale nos dois lugares.'
                    .($outros->isNotEmpty() ? ' ATENÇÃO: este contrato também é usado por '.$outros->implode(', ').', que recebem a mudança juntas.' : '');
            })
            ->fillForm(function () {
                $raw = $this->record->plan->features ?? [];
                $selected = array_is_list($raw)
                    ? collect($raw)
                    : collect($raw)->filter(fn ($v) => $v === true)->keys();

                $data = [];
                foreach (ContratoResource::groupedFeatureOptions() as $grupo => $options) {
                    $data[ContratoResource::groupFieldName($grupo)] = $selected->intersect(array_keys($options))->values()->all();
                }

                return $data;
            })
            ->form(function () {
                $secoes = [];
                foreach (ContratoResource::groupedFeatureOptions() as $grupo => $options) {
                    if ($grupo === 'Complementos') {
                        continue;
                    }
                    $secoes[] = Forms\Components\Section::make($grupo)->compact()->collapsible()->schema([
                        Forms\Components\CheckboxList::make(ContratoResource::groupFieldName($grupo))
                            ->label('')->options($options)->bulkToggleable()->columns(1),
                    ]);
                }

                return [Forms\Components\Grid::make(3)->schema($secoes)];
            })
            ->action(function (array $data) {
                $plan = $this->record->plan;
                $features = CreateContrato::mergeGroupedFeatures($data);

                // Complementos não são editados aqui: mantém o que o contrato já tem.
                foreach (array_keys(ComplementoService::catalogo()) as $complemento) {
                    $features[$complemento] = (bool) ($plan->features[$complemento] ?? false);
                }

                $plan->update(['features' => $features]);

                Notification::make()->title('Módulos do contrato atualizados')->success()->send();
            });
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
