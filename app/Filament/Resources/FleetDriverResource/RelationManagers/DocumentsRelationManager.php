<?php

namespace App\Filament\Resources\FleetDriverResource\RelationManagers;

use App\Models\FleetDriverDocument;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class DocumentsRelationManager extends RelationManager
{
    protected static string $relationship = 'documents';

    protected static ?string $title = 'Documentação';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('tipo')
                ->label('Tipo de Documento')
                ->options([
                    FleetDriverDocument::TIPO_MOPP => 'MOPP (Cargas Perigosas)',
                    FleetDriverDocument::TIPO_CERTIFICADO_OPERADOR => 'Certificado de Operador de Equipamento',
                    FleetDriverDocument::TIPO_OUTRO => 'Outro',
                ])
                ->required()
                ->native(false),
            Forms\Components\DatePicker::make('data_emissao')->label('Data de Emissão'),
            Forms\Components\DatePicker::make('data_vencimento')->label('Data de Vencimento'),
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
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        FleetDriverDocument::TIPO_MOPP => 'MOPP',
                        FleetDriverDocument::TIPO_CERTIFICADO_OPERADOR => 'Certificado de Operador',
                        default => 'Outro',
                    }),
                Tables\Columns\TextColumn::make('data_vencimento')
                    ->label('Vencimento')
                    ->date('d/m/Y')
                    ->badge()
                    ->color(fn (FleetDriverDocument $record) => match (true) {
                        $record->isVencido() => 'danger',
                        $record->isProximoVencimento() => 'warning',
                        default => 'success',
                    }),
                Tables\Columns\TextColumn::make('observacoes')->limit(40),
            ])
            ->defaultSort('data_vencimento')
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }
}
