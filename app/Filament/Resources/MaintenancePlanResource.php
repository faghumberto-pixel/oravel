<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MaintenancePlanResource\Pages;
use App\Models\MaintenancePlan;
use App\Models\Asset;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Facades\Filament;

class MaintenancePlanResource extends Resource
{
    protected static ?string $model = MaintenancePlan::class;
    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';
    protected static ?string $navigationGroup = 'GESTÃO DE MANUTENÇÃO';
    protected static ?string $navigationLabel = 'Planos Preventivos';
    
    // Garante que o resource seja escopado ao tenant atual
    protected static bool $isScopedToTenant = true;

    // Define que o relacionamento de propriedade é 'tenant'
    protected static ?string $tenantOwnershipRelationshipName = 'tenant';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Configuração do Plano')->schema([
                Forms\Components\Select::make('asset_id')
                    ->label('Ativo')
                    ->relationship('asset', 'name', fn ($query) => $query->where('tenant_id', Filament::getTenant()?->id))
                    ->required()
                    ->searchable()
                    ->preload(),
                Forms\Components\TextInput::make('name')
                    ->label('Nome do Plano (Ex: Preventiva 250h)')
                    ->required(),
                Forms\Components\TextInput::make('interval_hours')
                    ->label('Intervalo (Horas)')
                    ->numeric()
                    ->required(),
                Forms\Components\Toggle::make('is_active')
                    ->label('Plano Ativo')
                    ->default(true),
            ])
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('asset.name')->label('Ativo')->sortable(),
            Tables\Columns\TextColumn::make('name')->label('Nome do Plano')->searchable(),
            Tables\Columns\TextColumn::make('interval_hours')->label('Intervalo (h)'),
            Tables\Columns\IconColumn::make('is_active')->boolean()->label('Ativo'),
        ])
        ->actions([
            Tables\Actions\EditAction::make(),
            Tables\Actions\DeleteAction::make(),
        ]);
    }

    // Injeta o tenant_id antes de salvar para evitar erros de integridade
    public static function mutateFormDataBeforeCreate(array $data): array
    {
        $data['tenant_id'] = Filament::getTenant()?->id;
        return $data;
    }

    public static function getPages(): array {
        return [
            'index' => Pages\ListMaintenancePlans::route('/'),
            'create' => Pages\CreateMaintenancePlan::route('/create'),
            'edit' => Pages\EditMaintenancePlan::route('/{record}/edit'),
        ];
    }
}