<?php

namespace App\Filament\Resources\EmployeeResource\RelationManagers;

use App\Filament\Resources\EpiDeliveryResource;
use App\Models\EpiDelivery;
use App\Models\EpiSpecification;
use App\Models\Material;
use App\Support\Tenancy;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Ficha de EPI do colaborador (compliance NR-6) -- mesmo padrao de
 * CertificationsRelationManager, mas pra App\Models\EpiDelivery. O CRUD
 * completo (com todas as ações) continua em EpiDeliveryResource; aqui é
 * só a visão "histórico deste colaborador".
 */
class EpiDeliveriesRelationManager extends RelationManager
{
    protected static string $relationship = 'epiDeliveries';

    protected static ?string $title = 'Ficha de EPI';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('material_id')
                ->label('EPI')
                ->options(fn () => Material::where('tenant_id', Tenancy::current()?->id)
                    ->whereHas('epiSpecification')
                    ->get()
                    ->mapWithKeys(fn (Material $material) => [
                        $material->id => $material->name.($material->epiSpecification?->size_label ? ' — '.$material->epiSpecification->size_label : ''),
                    ]))
                ->searchable()
                ->live()
                ->afterStateUpdated(function (Get $get, Forms\Set $set, ?string $state) {
                    $spec = $state ? Material::find($state)?->epiSpecification : null;
                    $set('ownership_mode', $spec?->default_ownership_mode ?? EpiSpecification::OWNERSHIP_DEFINITIVA);
                })
                ->required(),

            Forms\Components\Select::make('internal_unit_id')
                ->label('Filial (baixa de estoque)')
                ->relationship('internalUnit', 'name', fn (Builder $query) => $query->where('tenant_id', Tenancy::current()?->id))
                ->searchable()
                ->preload()
                ->required(),

            Forms\Components\TextInput::make('quantity')
                ->label('Quantidade')
                ->numeric()
                ->default(1)
                ->minValue(1)
                ->required(),

            Forms\Components\Select::make('reason')
                ->label('Motivo')
                ->options(EpiDelivery::reasonLabels())
                ->default(EpiDelivery::REASON_ENTREGA_INICIAL)
                ->native(false)
                ->required(),

            Forms\Components\Select::make('ownership_mode')
                ->label('Modo de Posse')
                ->options(EpiSpecification::ownershipModeLabels())
                ->live()
                ->required(),

            Forms\Components\DateTimePicker::make('delivered_at')
                ->label('Data/Hora da Entrega')
                ->default(now())
                ->required(),

            Forms\Components\DatePicker::make('expected_return_at')
                ->label('Devolução Prevista')
                ->visible(fn (Get $get) => $get('ownership_mode') === EpiSpecification::OWNERSHIP_EMPRESTIMO_TEMPORARIO)
                ->required(fn (Get $get) => $get('ownership_mode') === EpiSpecification::OWNERSHIP_EMPRESTIMO_TEMPORARIO),

            Forms\Components\Textarea::make('notes')
                ->label('Observações')
                ->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('material_id')
            ->columns([
                Tables\Columns\TextColumn::make('material.name')
                    ->label('EPI')
                    ->formatStateUsing(fn (EpiDelivery $record) => $record->material->name.($record->material->epiSpecification?->size_label ? ' — '.$record->material->epiSpecification->size_label : '')),

                Tables\Columns\TextColumn::make('material.epiSpecification.ca_number')->label('CA'),

                Tables\Columns\BadgeColumn::make('status')
                    ->label('Status')
                    ->formatStateUsing(fn (string $state) => EpiDelivery::statusLabels()[$state] ?? $state)
                    ->colors([
                        'success' => EpiDelivery::STATUS_ATIVO,
                        'gray' => EpiDelivery::STATUS_DEVOLVIDO,
                        'warning' => EpiDelivery::STATUS_SUBSTITUIDO,
                        'danger' => EpiDelivery::STATUS_EXTRAVIADO,
                    ]),

                Tables\Columns\IconColumn::make('blocked')
                    ->label('CA Vencido')
                    ->boolean()
                    ->trueColor('danger')
                    ->falseColor('success'),

                Tables\Columns\TextColumn::make('delivered_at')->label('Entregue em')->dateTime('d/m/Y H:i'),

                Tables\Columns\TextColumn::make('expected_return_at')
                    ->label('Devolução Prevista')
                    ->date('d/m/Y')
                    ->color(fn (EpiDelivery $record) => $record->isOverdueForReturn() ? 'danger' : null)
                    ->placeholder('—'),
            ])
            ->defaultSort('delivered_at', 'desc')
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->after(fn (EpiDelivery $record) => EpiDeliveryResource::consumeStockForDelivery($record)),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\EditAction::make(),
                    EpiDeliveryResource::generateSignatureAction(),
                    EpiDeliveryResource::registerReturnAction(),
                    EpiDeliveryResource::registerReplacementAction(),
                ]),
            ]);
    }
}
