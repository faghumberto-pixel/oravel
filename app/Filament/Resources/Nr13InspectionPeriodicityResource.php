<?php

namespace App\Filament\Resources;

use App\Filament\Resources\Nr13InspectionPeriodicityResource\Pages;
use App\Models\AssetNr13Specification;
use App\Models\Nr13InspectionPeriodicity;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * De-para configurável (tipo_equipamento + categoria_risco -> meses), tenant-wide -- não
 * pertence a um Asset específico, por isso é um Resource próprio (single-page, mesmo padrão
 * de RentalHourFranchiseResource), não um RelationManager do AssetResource. Item do grupo de
 * navegação EXCLUSIVO "Conformidade NR-13" (grupo plano, mesmo padrão do grupo "PMP").
 *
 * NÃO é uma tabela legal fixa -- ver docblock de Nr13InspectionPeriodicity.
 */
class Nr13InspectionPeriodicityResource extends Resource
{
    protected static ?string $model = Nr13InspectionPeriodicity::class;

    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';

    protected static ?string $navigationGroup = 'Conformidade NR-13';

    protected static ?string $navigationLabel = 'Periodicidades';

    protected static ?int $navigationSort = 4;

    protected static ?string $modelLabel = 'Periodicidade NR-13';

    protected static ?string $pluralModelLabel = 'Periodicidades NR-13';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Section::make('Periodicidade de Inspeção')
                ->description('Valor padrão que o sistema sugere para a próxima inspeção. Não substitui a decisão técnica do responsável habilitado (PCPI/RBI) sobre o intervalo real de cada equipamento.')
                ->schema([
                    Select::make('tipo_equipamento')
                        ->label('Tipo de Equipamento')
                        ->options(AssetNr13Specification::tipoEquipamentoLabels())
                        ->native(false)
                        ->required(),

                    TextInput::make('categoria_risco')
                        ->label('Categoria de Risco')
                        ->placeholder('Ex: A, B, C (caldeira) ou I..V (vaso) -- deixe em branco para "qualquer categoria"'),

                    TextInput::make('intervalo_meses')
                        ->label('Intervalo (meses)')
                        ->numeric()
                        ->minValue(1)
                        ->required(),
                ])->columns(3),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('tipo_equipamento')
                    ->label('Tipo de Equipamento')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => AssetNr13Specification::tipoEquipamentoLabels()[$state] ?? $state),
                Tables\Columns\TextColumn::make('categoria_risco')->label('Categoria')->placeholder('Qualquer'),
                Tables\Columns\TextColumn::make('intervalo_meses')->label('Intervalo')->suffix(' meses')->sortable(),
            ])
            ->defaultSort('tipo_equipamento')
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([Tables\Actions\DeleteBulkAction::make()]),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageNr13InspectionPeriodicities::route('/')];
    }
}
