<?php

namespace App\Filament\Resources\EmployeeResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class CertificationsRelationManager extends RelationManager
{
    protected static string $relationship = 'certifications';

    protected static ?string $title = 'Certificações (NR)';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('norma')
                ->label('Norma Regulamentadora')
                ->placeholder('Ex: NR-11, NR-12, NR-35')
                ->required(),
            Forms\Components\DatePicker::make('data_emissao')->label('Data de Emissão'),
            Forms\Components\DatePicker::make('data_validade')->label('Data de Validade')->required(),
            Forms\Components\Textarea::make('observacoes')->label('Observações')->columnSpanFull(),
            Forms\Components\SpatieMediaLibraryFileUpload::make('arquivo')
                ->label('Arquivo do Certificado')
                ->collection('arquivo')
                ->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('norma')
            ->columns([
                Tables\Columns\TextColumn::make('norma')->label('Norma')->badge(),
                Tables\Columns\TextColumn::make('data_validade')
                    ->label('Validade')
                    ->date('d/m/Y')
                    ->badge()
                    ->color(fn ($record) => match (true) {
                        $record->isVencida() => 'danger',
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
