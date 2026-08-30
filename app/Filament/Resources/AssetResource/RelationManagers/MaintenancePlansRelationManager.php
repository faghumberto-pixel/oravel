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
use Illuminate\Database\Eloquent\Builder;

/**
 * Lista TODOS os planos aplicáveis a este Ativo -- os herdados do Grupo de
 * Checklist (template, sem asset_id) JUNTO com os próprios (personalizados
 * ou manuais, asset_id preenchido) -- pedido do usuário 2026-08-30: o
 * cadastro do Ativo deve mostrar o PMP completo (origem do Grupo), não só
 * o que foi manualmente incluído. Um item herdado com override por nome
 * (mesmo name já personalizado/manual pra este Ativo) é ocultado -- mesma
 * regra de MaintenancePlan::applicableFor(), aqui feita na query da tabela
 * em vez de numa Collection em memória, pra manter os recursos nativos do
 * Filament (paginação, ordenação por coluna) funcionando.
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

    /**
     * RelationManager::getTableQuery() (não sobrescrito) parte de
     * $this->getRelationship() -- já vem restrito a asset_id=este Ativo
     * antes de qualquer modifyQueryUsing(), então dar orWhere() lá dentro
     * não alcança os itens do Grupo (sem asset_id). Sobrescrever aqui,
     * partindo direto de MaintenancePlan::query(), é o jeito de escapar
     * dessa restrição implícita.
     */
    public function getTableQuery(): ?Builder
    {
        /** @var Asset $asset */
        $asset = $this->getOwnerRecord();

        $overriddenNames = $asset->maintenancePlans()->pluck('name');

        return MaintenancePlan::query()
            ->where(function (Builder $query) use ($asset) {
                $query->where('asset_id', $asset->id);

                if ($asset->checklist_group_id) {
                    $query->orWhere('checklist_group_id', $asset->checklist_group_id);
                }
            })
            ->where(function (Builder $query) use ($overriddenNames) {
                // Item herdado do grupo com o mesmo nome de um item já
                // próprio do Ativo é ocultado -- só a versão própria
                // (personalizada/manual) aparece.
                $query->whereNotNull('asset_id')
                    ->orWhereNotIn('name', $overriddenNames);
            });
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('Item'),
                Tables\Columns\IconColumn::make('is_critical')->boolean()->label('Crítico')->trueColor('danger'),
                Tables\Columns\TextColumn::make('interval_hours')->label('Intervalo (h)')->placeholder('—'),
                Tables\Columns\TextColumn::make('interval_days')->label('Intervalo (dias)')->placeholder('—'),
                Tables\Columns\TextColumn::make('source')
                    ->label('Origem')
                    ->badge()
                    ->state(fn (MaintenancePlan $record) => match (true) {
                        $record->asset_id === null => 'grupo',
                        $record->source === MaintenancePlan::SOURCE_TEMPLATE => 'personalizado',
                        default => 'manual',
                    })
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'grupo' => 'Do Grupo',
                        'personalizado' => 'Personalizado do Grupo',
                        default => 'Manual',
                    })
                    ->color(fn (string $state) => match ($state) {
                        'grupo' => 'gray',
                        'personalizado' => 'info',
                        default => 'gray',
                    }),
                Tables\Columns\IconColumn::make('is_active')->boolean()->label('Ativo'),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['source'] = MaintenancePlan::SOURCE_MANUAL;

                        return $data;
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('personalizar')
                    ->label('Personalizar')
                    ->icon('heroicon-o-document-duplicate')
                    ->color('info')
                    ->visible(fn (MaintenancePlan $record) => $record->asset_id === null)
                    ->action(function (MaintenancePlan $record): void {
                        /** @var Asset $asset */
                        $asset = $this->getOwnerRecord();

                        $asset->copyMaintenancePlanTemplateItem($record);

                        Notification::make()
                            ->title('Item personalizado -- edite os valores abaixo pra alterar só pra este ativo.')
                            ->success()
                            ->send();
                    }),
                Tables\Actions\EditAction::make()
                    ->visible(fn (MaintenancePlan $record) => $record->asset_id !== null),
                Tables\Actions\DeleteAction::make()
                    ->visible(fn (MaintenancePlan $record) => $record->asset_id !== null),
            ]);
    }
}
