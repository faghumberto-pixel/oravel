<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasSuperAdminTenantColumn;
use App\Filament\Resources\PreventiveMaintenanceExecutionResource\Pages;
use App\Models\Asset;
use App\Models\ChecklistGroup;
use App\Models\MaintenancePlan;
use App\Models\PreventiveMaintenanceExecution;
use App\Models\User;
use App\Support\Tenancy;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PreventiveMaintenanceExecutionResource extends Resource
{
    use HasSuperAdminTenantColumn;

    protected static ?string $model = PreventiveMaintenanceExecution::class;

    protected static ?string $navigationIcon = 'heroicon-o-wrench';

    protected static ?string $navigationGroup = 'PCM';

    protected static ?string $navigationLabel = 'Preventivas';

    protected static ?string $modelLabel = 'Manutenção Preventiva';

    protected static ?string $pluralModelLabel = 'Manutenções Preventivas';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Execução')->schema([
                Forms\Components\Select::make('asset_id')
                    ->label('Ativo')
                    ->relationship('asset', 'name', fn (Builder $query) => $query->where('tenant_id', Tenancy::current()?->id))
                    ->searchable()
                    ->preload()
                    ->live()
                    ->required(),

                Forms\Components\Select::make('maintenance_plan_id')
                    ->label('Item de Preventiva')
                    ->options(function (Get $get) {
                        $asset = Asset::find($get('asset_id'));

                        if (! $asset || ! $asset->checklist_group_id) {
                            return [];
                        }

                        return MaintenancePlan::where('checklist_group_id', $asset->checklist_group_id)
                            ->where('is_active', true)
                            ->pluck('name', 'id');
                    })
                    ->disabled(fn (Get $get) => blank($get('asset_id')))
                    ->live()
                    ->searchable()
                    ->required(),

                Forms\Components\Select::make('maintenance_order_id')
                    ->label('Ordem de Serviço Vinculada')
                    ->relationship(
                        'maintenanceOrder',
                        'os_number',
                        fn (Builder $query, Get $get) => $query
                            ->where('tenant_id', Tenancy::current()?->id)
                            ->when($get('asset_id'), fn ($q, $assetId) => $q->where('asset_id', $assetId))
                    )
                    ->searchable()
                    ->preload()
                    ->required(),

                Forms\Components\Select::make('technician_id')
                    ->label('Técnico Responsável')
                    ->options(fn () => User::where('tenant_id', Tenancy::current()?->id)->pluck('name', 'id'))
                    ->searchable(),

                Forms\Components\TextInput::make('horimetro_at_execution')
                    ->label('Horímetro na Execução')
                    ->numeric()
                    ->live(onBlur: true)
                    ->required(),

                Forms\Components\Placeholder::make('proxima_previsao')
                    ->label('Próxima Previsão')
                    ->content(function (Get $get) {
                        $plan = MaintenancePlan::find($get('maintenance_plan_id'));
                        $horimetro = (float) ($get('horimetro_at_execution') ?? 0);

                        if (! $plan || ! $horimetro) {
                            return '—';
                        }

                        return number_format($horimetro + $plan->interval_hours, 2).'h';
                    }),

                Forms\Components\Textarea::make('observacao')
                    ->label('Estado da Máquina / Observações')
                    ->rows(3)
                    ->columnSpanFull(),

                Forms\Components\SpatieMediaLibraryFileUpload::make('photos')
                    ->collection('photos')
                    ->label('Fotos')
                    ->multiple()
                    ->image()
                    ->columnSpanFull(),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('created_at')->label('Data')->dateTime('d/m/Y H:i')->sortable(),
                Tables\Columns\TextColumn::make('asset.patrimonio')->label('Patrimônio')->searchable(),
                Tables\Columns\TextColumn::make('asset.name')->label('Ativo')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('asset.checklistGroup.name')->label('Grupo'),
                Tables\Columns\TextColumn::make('maintenancePlan.name')->label('Item Executado'),
                Tables\Columns\TextColumn::make('horimetro_at_execution')->label('Horímetro')->numeric(2),
                Tables\Columns\TextColumn::make('next_due_horimetro')->label('Próxima Previsão')->numeric(2),
                Tables\Columns\TextColumn::make('technician.name')->label('Técnico'),
                Tables\Columns\TextColumn::make('maintenanceOrder.os_number')->label('OS')->searchable(),
                self::tenantColumn(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('asset_id')
                    ->label('Ativo')
                    ->relationship('asset', 'name'),
                Tables\Filters\SelectFilter::make('checklist_group')
                    ->label('Grupo de Ativo')
                    ->options(fn () => ChecklistGroup::where('tenant_id', Tenancy::current()?->id)->pluck('name', 'id'))
                    ->query(fn (Builder $query, array $data) => $query->when(
                        $data['value'] ?? null,
                        fn ($q, $groupId) => $q->whereHas('asset', fn ($assetQuery) => $assetQuery->where('checklist_group_id', $groupId))
                    )),
                Tables\Filters\Filter::make('created_at')
                    ->form([
                        Forms\Components\DatePicker::make('from')->label('De'),
                        Forms\Components\DatePicker::make('until')->label('Até'),
                    ])
                    ->query(fn (Builder $query, array $data) => $query
                        ->when($data['from'] ?? null, fn ($q, $date) => $q->whereDate('created_at', '>=', $date))
                        ->when($data['until'] ?? null, fn ($q, $date) => $q->whereDate('created_at', '<=', $date))),
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            PreventiveMaintenanceExecutionResource\RelationManagers\MaterialsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPreventiveMaintenanceExecutions::route('/'),
            'create' => Pages\CreatePreventiveMaintenanceExecution::route('/create'),
            'view' => Pages\ViewPreventiveMaintenanceExecution::route('/{record}'),
            'edit' => Pages\EditPreventiveMaintenanceExecution::route('/{record}/edit'),
        ];
    }
}
