<?php

namespace App\Filament\Resources\AssetResource\RelationManagers;

use App\Models\Asset;
use App\Models\MaintenancePlan;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * Planos de manutenção ESPECÍFICOS deste Ativo -- os herdados do Grupo de
 * Checklist (template) não aparecem aqui como linha própria (não têm
 * asset_id), só depois de "personalizados" via ação "Copiar do Grupo",
 * que cria uma cópia editável (source=template) sem alterar o item
 * original nem afetar os outros Ativos do mesmo Grupo. Ver
 * MaintenancePlan::applicableFor() pra como as duas fontes se combinam
 * nos alertas (Painel de Criticidade, Eventos e Falhas etc).
 */
class MaintenancePlansRelationManager extends RelationManager
{
    protected static string $relationship = 'maintenancePlans';

    protected static ?string $title = 'Planos de Manutenção (deste ativo)';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')
                ->label('Item (Ex: Troca de óleo do motor)')
                ->required(),
            Forms\Components\TextInput::make('interval_hours')
                ->label('Intervalo (Horas)')
                ->numeric(),
            Forms\Components\TextInput::make('interval_days')
                ->label('Intervalo (Dias)')
                ->numeric(),
            Forms\Components\Toggle::make('is_critical')
                ->label('Item crítico')
                ->default(false),
            Forms\Components\Textarea::make('notes')
                ->label('Observação')
                ->rows(2),
            Forms\Components\Toggle::make('is_active')
                ->label('Plano ativo')
                ->default(true),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('Item'),
                Tables\Columns\IconColumn::make('is_critical')->boolean()->label('Crítico')->trueColor('danger'),
                Tables\Columns\TextInputColumn::make('interval_hours')->label('Intervalo (h)')->placeholder('—'),
                Tables\Columns\TextInputColumn::make('interval_days')->label('Intervalo (dias)')->placeholder('—'),
                Tables\Columns\TextColumn::make('source')
                    ->label('Origem')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => $state === 'template' ? 'Personalizado do Grupo' : 'Manual')
                    ->color(fn (string $state) => $state === 'template' ? 'info' : 'gray'),
                Tables\Columns\IconColumn::make('is_active')->boolean()->label('Ativo'),
            ])
            ->headerActions([
                Tables\Actions\Action::make('copiar_do_grupo')
                    ->label('Personalizar Item do Grupo')
                    ->icon('heroicon-o-document-duplicate')
                    ->color('info')
                    ->visible(fn () => (bool) $this->getOwnerRecord()->checklist_group_id)
                    ->form(function () {
                        /** @var Asset $asset */
                        $asset = $this->getOwnerRecord();

                        $jaPersonalizados = $asset->maintenancePlans()->pluck('name');

                        $itensDisponiveis = MaintenancePlan::where('checklist_group_id', $asset->checklist_group_id)
                            ->whereNotIn('name', $jaPersonalizados)
                            ->pluck('name', 'id');

                        return [
                            Forms\Components\Select::make('template_item_id')
                                ->label('Item do template do Grupo')
                                ->options($itensDisponiveis)
                                ->helperText($itensDisponiveis->isEmpty() ? 'Todos os itens do grupo já foram personalizados pra este ativo.' : null)
                                ->required(),
                        ];
                    })
                    ->action(function (array $data): void {
                        /** @var Asset $asset */
                        $asset = $this->getOwnerRecord();

                        $templateItem = MaintenancePlan::find($data['template_item_id']);

                        if ($templateItem) {
                            $asset->copyMaintenancePlanTemplateItem($templateItem);

                            Notification::make()
                                ->title('Item copiado -- edite os valores abaixo pra personalizar só pra este ativo.')
                                ->success()
                                ->send();
                        }
                    }),
                Tables\Actions\CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['source'] = MaintenancePlan::SOURCE_MANUAL;

                        return $data;
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }
}
