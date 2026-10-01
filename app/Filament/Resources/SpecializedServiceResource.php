<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SpecializedServiceResource\Pages;
use App\Models\SpecializedService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Tables;
use Filament\Tables\Table;

class SpecializedServiceResource extends BaseResource
{
    protected static ?string $model = SpecializedService::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $navigationGroup = 'Itens Agregados';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Mão de Obra Especializada';

    protected static ?string $modelLabel = 'Serviço Especializado';

    protected static ?string $pluralModelLabel = 'Mão de Obra Especializada';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('service_type')->label('Tipo de serviço')
                ->options(SpecializedService::typeLabels())->required()->native(false),
            Forms\Components\TextInput::make('title')->label('Descrição do serviço')
                ->placeholder('Ex: Operador de guindaste, Plano de içamento, Transporte em prancha')
                ->required()->maxLength(255),
            Forms\Components\Select::make('employee_id')->label('Colaborador (equipe própria)')
                ->relationship('employee', 'name')->searchable()->preload(),
            Forms\Components\Select::make('supplier_id')->label('Fornecedor (terceirizado)')
                ->relationship('supplier', 'name')->searchable()->preload(),
            Forms\Components\Select::make('asset_id')->label('Equipamento')
                ->relationship('asset', 'name')->searchable()->preload(),
            Forms\Components\Select::make('contract_id')->label('Contrato de locação')
                ->relationship('contract', 'contract_number')->searchable()->preload(),
            Forms\Components\DatePicker::make('start_date')->label('Início'),
            Forms\Components\DatePicker::make('end_date')->label('Fim'),
            Forms\Components\TextInput::make('cost')->label('Custo')->numeric()->prefix('R$'),
            Forms\Components\Select::make('status')->label('Status')
                ->options(SpecializedService::statusLabels())->default('planejado')->required()->native(false),
            Forms\Components\Textarea::make('notes')->label('Observações')->rows(2)->columnSpanFull(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('service_type')->label('Tipo')->badge()
                    ->formatStateUsing(fn ($state) => SpecializedService::typeLabels()[$state] ?? $state),
                Tables\Columns\TextColumn::make('title')->label('Serviço')->searchable(),
                Tables\Columns\TextColumn::make('employee.name')->label('Colaborador')->placeholder('—'),
                Tables\Columns\TextColumn::make('supplier.name')->label('Fornecedor')->placeholder('—'),
                Tables\Columns\TextColumn::make('asset.name')->label('Equipamento')->placeholder('—'),
                Tables\Columns\TextColumn::make('contract.contract_number')->label('Contrato')->placeholder('—'),
                Tables\Columns\TextColumn::make('start_date')->label('Início')->date('d/m/Y')->sortable(),
                Tables\Columns\TextColumn::make('cost')->label('Custo')->money('BRL')
                    ->summarize(Tables\Columns\Summarizers\Sum::make()->money('BRL')),
                Tables\Columns\TextColumn::make('status')->label('Status')->badge()
                    ->formatStateUsing(fn ($state) => SpecializedService::statusLabels()[$state] ?? $state)
                    ->color(fn ($state) => match ($state) {
                        'concluido' => 'success', 'em_andamento' => 'info', 'cancelado' => 'gray', default => 'warning',
                    }),
            ])
            ->defaultSort('start_date', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('service_type')->label('Tipo')->options(SpecializedService::typeLabels()),
                Tables\Filters\SelectFilter::make('status')->label('Status')->options(SpecializedService::statusLabels()),
            ])
            ->actions([Tables\Actions\EditAction::make(), Tables\Actions\DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageSpecializedServices::route('/')];
    }
}
