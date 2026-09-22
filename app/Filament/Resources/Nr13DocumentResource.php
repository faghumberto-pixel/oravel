<?php

namespace App\Filament\Resources;

use App\Filament\Resources\Nr13DocumentResource\Pages;
use App\Models\Nr13Document;
use App\Support\Tenancy;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * Lista TODOS os documentos NR-13 do tenant, entre equipamentos -- complementa (não substitui)
 * a Nr13DocumentsRelationManager dentro de cada Asset (útil pra editar no contexto de um
 * equipamento específico; este Resource é pra ver tudo de uma vez, com o dashboard). Mesmo
 * model, mesma Policy (Nr13DocumentPolicy) -- Filament permite um model ter tanto um Resource
 * top-level quanto RelationManagers em outro Resource ao mesmo tempo, sem conflito.
 *
 * Item do grupo de navegação EXCLUSIVO "Conformidade NR-13" (grupo plano, mesmo padrão de PMP).
 */
class Nr13DocumentResource extends Resource
{
    protected static ?string $model = Nr13Document::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationGroup = 'Conformidade NR-13';

    protected static ?string $navigationLabel = 'Documentos';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'Documento NR-13';

    protected static ?string $pluralModelLabel = 'Documentos NR-13';

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
                ->label('Tipo de Documento')
                ->options(Nr13Document::tipoLabels())
                ->required()
                ->native(false),

            DatePicker::make('data_emissao')->label('Data de Emissão'),
            DatePicker::make('data_validade')->label('Data de Validade'),
            Textarea::make('observacoes')->label('Observações')->columnSpanFull(),

            SpatieMediaLibraryFileUpload::make('arquivo')
                ->label('Arquivo')
                ->collection('arquivo')
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
                    ->formatStateUsing(fn (string $state) => Nr13Document::tipoLabels()[$state] ?? $state),
                Tables\Columns\TextColumn::make('data_validade')
                    ->label('Validade')
                    ->date('d/m/Y')
                    ->placeholder('Sem validade')
                    ->badge()
                    ->color(fn (Nr13Document $record) => match (true) {
                        ! $record->data_validade => 'gray',
                        $record->isVencido() => 'danger',
                        $record->isProximoVencimento() => 'warning',
                        default => 'success',
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('observacoes')->limit(40),
            ])
            ->defaultSort('data_validade')
            ->filters([
                Tables\Filters\SelectFilter::make('tipo')->options(Nr13Document::tipoLabels()),
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
        return ['index' => Pages\ManageNr13Documents::route('/')];
    }
}
