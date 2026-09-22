<?php

namespace App\Filament\Resources;

use App\Filament\Resources\Nr13InspectionResource\Pages;
use App\Models\Nr13Inspection;
use App\Support\Tenancy;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * Lista TODAS as inspeções NR-13 do tenant, entre equipamentos -- complementa (não substitui)
 * a Nr13InspectionsRelationManager dentro de cada Asset. Mesmo model, mesma Policy
 * (Nr13InspectionPolicy) -- ver docblock de Nr13DocumentResource sobre coexistir com o
 * RelationManager.
 *
 * Item do grupo de navegação EXCLUSIVO "Conformidade NR-13" (grupo plano, mesmo padrão de PMP).
 */
class Nr13InspectionResource extends Resource
{
    protected static ?string $model = Nr13Inspection::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static ?string $navigationGroup = 'Conformidade NR-13';

    protected static ?string $navigationLabel = 'Inspeções';

    protected static ?int $navigationSort = 3;

    protected static ?string $modelLabel = 'Inspeção NR-13';

    protected static ?string $pluralModelLabel = 'Inspeções NR-13';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Select::make('asset_id')
                ->label('Equipamento')
                ->relationship('asset', 'name', fn ($query) => $query->where('tenant_id', Tenancy::current()?->id))
                ->searchable()
                ->preload()
                ->required(),

            Select::make('tipo')
                ->label('Tipo de Inspeção')
                ->options(Nr13Inspection::tipoLabels())
                ->required()
                ->native(false),

            DatePicker::make('data_inspecao')->label('Data da Inspeção')->required(),
            DatePicker::make('data_proxima_inspecao')->label('Próxima Inspeção'),

            Select::make('resultado')
                ->label('Resultado')
                ->options(Nr13Inspection::resultadoLabels())
                ->native(false),

            TextInput::make('responsavel_tecnico')->label('Responsável Técnico'),
            TextInput::make('numero_art')->label('Número da ART'),
            Textarea::make('observacoes')->label('Observações')->columnSpanFull(),

            SpatieMediaLibraryFileUpload::make('laudo')
                ->label('Laudo')
                ->collection('laudo')
                ->columnSpanFull(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('asset.name')->label('Equipamento')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('tipo')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => Nr13Inspection::tipoLabels()[$state] ?? $state),
                Tables\Columns\TextColumn::make('data_inspecao')->label('Realizada em')->date('d/m/Y')->sortable(),
                Tables\Columns\TextColumn::make('data_proxima_inspecao')
                    ->label('Próxima')
                    ->date('d/m/Y')
                    ->placeholder('Não definida')
                    ->badge()
                    ->color(fn (Nr13Inspection $record) => match (true) {
                        ! $record->data_proxima_inspecao => 'gray',
                        $record->isVencida() => 'danger',
                        $record->isProximaDoVencimento() => 'warning',
                        default => 'success',
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('resultado')
                    ->badge()
                    ->formatStateUsing(fn (?string $state) => $state ? Nr13Inspection::resultadoLabels()[$state] ?? $state : '—')
                    ->color(fn (?string $state) => match ($state) {
                        Nr13Inspection::RESULTADO_APROVADO => 'success',
                        Nr13Inspection::RESULTADO_REPROVADO => 'danger',
                        Nr13Inspection::RESULTADO_COM_RESSALVA => 'warning',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('responsavel_tecnico')->label('Responsável'),
            ])
            ->defaultSort('data_proxima_inspecao')
            ->filters([
                Tables\Filters\SelectFilter::make('tipo')->options(Nr13Inspection::tipoLabels()),
                Tables\Filters\SelectFilter::make('resultado')->options(Nr13Inspection::resultadoLabels()),
            ])
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
        return ['index' => Pages\ManageNr13Inspections::route('/')];
    }
}
