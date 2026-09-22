<?php

namespace App\Filament\Resources\AssetResource\RelationManagers;

use App\Models\Nr13Document;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * Documentação técnica NR-13 do ativo (prontuário, projeto, PMOC, certificado de inspeção,
 * laudo) -- mesmo padrão de FleetVehicleResource\RelationManagers\DocumentsRelationManager.
 */
class Nr13DocumentsRelationManager extends RelationManager
{
    protected static string $relationship = 'nr13Documents';

    protected static ?string $title = 'Documentos NR-13';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('tipo')
                ->label('Tipo de Documento')
                ->options(Nr13Document::tipoLabels())
                ->required()
                ->native(false),
            Forms\Components\DatePicker::make('data_emissao')->label('Data de Emissão'),
            Forms\Components\DatePicker::make('data_validade')->label('Data de Validade'),
            Forms\Components\Textarea::make('observacoes')->label('Observações')->columnSpanFull(),
            Forms\Components\SpatieMediaLibraryFileUpload::make('arquivo')
                ->label('Arquivo')
                ->collection('arquivo')
                ->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('tipo')
            ->columns([
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
                    }),
                Tables\Columns\TextColumn::make('observacoes')->limit(40),
            ])
            ->defaultSort('data_validade')
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }
}
