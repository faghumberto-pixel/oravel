<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EpiDeliveryResource\Pages;
use App\Models\EpiDelivery;
use App\Models\EpiSpecification;
use App\Models\Material;
use App\Models\MaterialStockMovement;
use App\Services\MaterialStockService;
use App\Services\SignatureService;
use App\Support\Tenancy;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Ficha de entrega/emprestimo de EPI por colaborador (compliance NR-6).
 * Cada linha e' um ciclo de entrega -- devolucao/troca abrem ou fecham
 * ciclos, nunca reescrevem um ciclo passado (ver App\Models\EpiDelivery).
 */
class EpiDeliveryResource extends BaseResource
{
    protected static ?string $model = EpiDelivery::class;

    protected static ?string $navigationIcon = 'heroicon-o-shield-check';

    protected static ?string $navigationGroup = 'Ativos e Materiais';

    protected static ?string $navigationParentItem = 'Gestão de EPI';

    protected static ?string $navigationLabel = 'Entrega de EPI';

    protected static ?string $modelLabel = 'Entrega de EPI';

    protected static ?string $pluralModelLabel = 'Entregas de EPI';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Entrega')
                    ->schema([
                        Forms\Components\Select::make('employee_id')
                            ->label('Colaborador')
                            ->relationship('employee', 'name', fn (Builder $query) => $query->where('tenant_id', Tenancy::current()?->id))
                            ->searchable()
                            ->preload()
                            ->required(),

                        Forms\Components\Select::make('material_id')
                            ->label('EPI')
                            ->relationship(
                                'material',
                                'name',
                                fn (Builder $query) => $query
                                    ->where('tenant_id', Tenancy::current()?->id)
                                    ->whereHas('epiSpecification')
                            )
                            ->getOptionLabelFromRecordUsing(fn (Material $record) => $record->name.($record->epiSpecification?->size_label ? ' — '.$record->epiSpecification->size_label : ''))
                            ->searchable()
                            ->preload()
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

                        Forms\Components\Select::make('delivered_by_user_id')
                            ->label('Entregue por')
                            ->relationship('deliveredBy', 'name', fn (Builder $query) => $query->where('tenant_id', Tenancy::current()?->id))
                            ->default(fn () => auth()->id())
                            ->searchable()
                            ->preload(),

                        Forms\Components\DatePicker::make('expected_return_at')
                            ->label('Devolução Prevista')
                            ->visible(fn (Get $get) => $get('ownership_mode') === EpiSpecification::OWNERSHIP_EMPRESTIMO_TEMPORARIO)
                            ->required(fn (Get $get) => $get('ownership_mode') === EpiSpecification::OWNERSHIP_EMPRESTIMO_TEMPORARIO),

                        Forms\Components\Textarea::make('notes')
                            ->label('Observações')
                            ->columnSpanFull(),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('employee.name')
                    ->label('Colaborador')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('material.name')
                    ->label('EPI')
                    ->formatStateUsing(fn (EpiDelivery $record) => $record->material->name.($record->material->epiSpecification?->size_label ? ' — '.$record->material->epiSpecification->size_label : ''))
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('material.epiSpecification.ca_number')
                    ->label('CA'),

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
                    ->trueIcon('heroicon-o-x-circle')
                    ->falseIcon('heroicon-o-check-circle')
                    ->trueColor('danger')
                    ->falseColor('success'),

                Tables\Columns\TextColumn::make('reason')
                    ->label('Motivo')
                    ->formatStateUsing(fn (string $state) => EpiDelivery::reasonLabels()[$state] ?? $state)
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('delivered_at')
                    ->label('Entregue em')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('expected_return_at')
                    ->label('Devolução Prevista')
                    ->date('d/m/Y')
                    ->color(fn (EpiDelivery $record) => $record->isOverdueForReturn() ? 'danger' : null)
                    ->placeholder('—'),

                Tables\Columns\IconColumn::make('signatures_signed')
                    ->label('Assinado')
                    ->boolean()
                    ->getStateUsing(fn (EpiDelivery $record) => $record->signedSignatures()->exists()),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options(EpiDelivery::statusLabels()),
                Tables\Filters\TernaryFilter::make('blocked')->label('CA Vencido'),
                Tables\Filters\Filter::make('devolucao_atrasada')
                    ->label('Devolução Atrasada')
                    ->query(fn (Builder $query) => $query
                        ->where('ownership_mode', EpiSpecification::OWNERSHIP_EMPRESTIMO_TEMPORARIO)
                        ->where('status', EpiDelivery::STATUS_ATIVO)
                        ->whereNull('returned_at')
                        ->whereDate('expected_return_at', '<', now()))
                    ->toggle(),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\EditAction::make(),
                    static::generateSignatureAction(),
                    static::registerReturnAction(),
                    static::registerReplacementAction(),
                ]),
            ])
            ->defaultSort('delivered_at', 'desc');
    }

    /**
     * Baixa o estoque do Material entregue na filial escolhida -- mesmo
     * ponto unico de escrita usado por todo o resto do sistema
     * (MaterialStockService), nunca decrementa current_quantity na mao.
     * Compartilhada entre CreateEpiDelivery (Resource) e o CreateAction da
     * EpiDeliveriesRelationManager (EmployeeResource).
     */
    public static function consumeStockForDelivery(EpiDelivery $delivery): void
    {
        if (! $delivery->internalUnit) {
            return;
        }

        app(MaterialStockService::class)->consume(
            $delivery->material,
            $delivery->internalUnit,
            $delivery->quantity,
            $delivery,
            auth()->id(),
        );

        $movement = MaterialStockMovement::where('reference_type', EpiDelivery::class)
            ->where('reference_id', $delivery->id)
            ->latest()
            ->first();

        if ($movement) {
            $delivery->updateQuietly(['material_stock_movement_id' => $movement->id]);
        }
    }

    /**
     * Reaproveita o mecanismo de assinatura eletronica generico (mesmo
     * usado por Contract/MaintenanceOrder) -- gera um DocumentSignature +
     * link publico, sem nenhuma infraestrutura nova. Compartilhada entre
     * este Resource e EpiDeliveriesRelationManager (EmployeeResource).
     */
    public static function generateSignatureAction(): Tables\Actions\Action
    {
        return Tables\Actions\Action::make('generate_signature')
            ->label('Gerar Assinatura')
            ->icon('heroicon-o-pencil-square')
            ->color('info')
            ->visible(fn (EpiDelivery $record) => ! $record->signedSignatures()->exists())
            ->action(function (EpiDelivery $record) {
                $link = app(SignatureService::class)->generateSignatureLink($record, [
                    'name' => $record->employee->name,
                    'document' => $record->employee->cpf,
                ]);

                Notification::make()
                    ->title('Link de assinatura gerado')
                    ->body($link)
                    ->success()
                    ->send();
            });
    }

    /**
     * Fecha o ciclo de emprestimo. So' credita de volta o estoque
     * (MaterialStockService::receive) quando o item volta em condicao
     * reutilizavel -- danificado/perdido nao retorna ao estoque disponivel.
     */
    public static function registerReturnAction(): Tables\Actions\Action
    {
        return Tables\Actions\Action::make('register_return')
            ->label('Registrar Devolução')
            ->icon('heroicon-o-arrow-uturn-left')
            ->color('warning')
            ->visible(fn (EpiDelivery $record) => $record->ownership_mode === EpiSpecification::OWNERSHIP_EMPRESTIMO_TEMPORARIO
                && $record->status === EpiDelivery::STATUS_ATIVO)
            ->form([
                Forms\Components\Select::make('returned_condition')
                    ->label('Condição do Item')
                    ->options(EpiDelivery::returnedConditionLabels())
                    ->required(),
                Forms\Components\Textarea::make('notes')->label('Observações'),
            ])
            ->action(function (EpiDelivery $record, array $data) {
                $record->update([
                    'status' => EpiDelivery::STATUS_DEVOLVIDO,
                    'returned_at' => now(),
                    'returned_by_user_id' => auth()->id(),
                    'returned_condition' => $data['returned_condition'],
                    'notes' => trim(($record->notes ? $record->notes."\n" : '').($data['notes'] ?? '')),
                ]);

                if ($data['returned_condition'] === EpiDelivery::RETURNED_CONDITION_BOA && $record->internalUnit) {
                    app(MaterialStockService::class)->receive(
                        $record->material,
                        $record->internalUnit,
                        $record->quantity,
                        $record,
                        auth()->id(),
                    );
                }

                Notification::make()->title('Devolução registrada')->success()->send();
            });
    }

    /**
     * Fecha a entrega atual (desgaste/CA vencido/perda/dano) e abre um
     * novo ciclo pro mesmo colaborador -- cada troca vira uma nova linha
     * (rastreabilidade completa), encadeada via replaced_by_delivery_id.
     */
    public static function registerReplacementAction(): Tables\Actions\Action
    {
        return Tables\Actions\Action::make('register_replacement')
            ->label('Registrar Troca')
            ->icon('heroicon-o-arrow-path')
            ->color('danger')
            ->visible(fn (EpiDelivery $record) => $record->status === EpiDelivery::STATUS_ATIVO)
            ->form([
                Forms\Components\Select::make('reason')
                    ->label('Motivo da Troca')
                    ->options([
                        EpiDelivery::REASON_TROCA_DESGASTE => EpiDelivery::reasonLabels()[EpiDelivery::REASON_TROCA_DESGASTE],
                        EpiDelivery::REASON_TROCA_CA_VENCIDO => EpiDelivery::reasonLabels()[EpiDelivery::REASON_TROCA_CA_VENCIDO],
                        EpiDelivery::REASON_PERDA => EpiDelivery::reasonLabels()[EpiDelivery::REASON_PERDA],
                        EpiDelivery::REASON_DANO => EpiDelivery::reasonLabels()[EpiDelivery::REASON_DANO],
                    ])
                    ->default(EpiDelivery::REASON_TROCA_DESGASTE)
                    ->required(),
            ])
            ->action(function (EpiDelivery $record, array $data) {
                $novaEntrega = EpiDelivery::create([
                    'tenant_id' => $record->tenant_id,
                    'employee_id' => $record->employee_id,
                    'material_id' => $record->material_id,
                    'internal_unit_id' => $record->internal_unit_id,
                    'quantity' => $record->quantity,
                    'reason' => $data['reason'],
                    'ownership_mode' => $record->ownership_mode,
                    'delivered_at' => now(),
                    'delivered_by_user_id' => auth()->id(),
                    'expected_return_at' => $record->ownership_mode === EpiSpecification::OWNERSHIP_EMPRESTIMO_TEMPORARIO
                        ? $record->expected_return_at
                        : null,
                ]);

                static::consumeStockForDelivery($novaEntrega);

                $record->update([
                    'status' => $data['reason'] === EpiDelivery::REASON_PERDA ? EpiDelivery::STATUS_EXTRAVIADO : EpiDelivery::STATUS_SUBSTITUIDO,
                    'replaced_by_delivery_id' => $novaEntrega->id,
                ]);

                Notification::make()->title('Troca registrada, nova entrega criada')->success()->send();
            });
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEpiDeliveries::route('/'),
            'create' => Pages\CreateEpiDelivery::route('/create'),
            'edit' => Pages\EditEpiDelivery::route('/{record}/edit'),
        ];
    }
}
