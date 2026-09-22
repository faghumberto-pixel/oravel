<?php

namespace App\Filament\Resources\AssetResource\RelationManagers;

use App\Models\Nr13Inspection;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * Histórico de inspeções/ensaios NR-13 do ativo (interna, externa, de segurança, hidrostática).
 * data_proxima_inspecao é preenchida manualmente aqui (não recalculada a partir de
 * Nr13InspectionPeriodicity automaticamente) -- ver docblock de Nr13Inspection.
 */
class Nr13InspectionsRelationManager extends RelationManager
{
    protected static string $relationship = 'nr13Inspections';

    protected static ?string $title = 'Inspeções NR-13';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('tipo')
                ->label('Tipo de Inspeção')
                ->options(Nr13Inspection::tipoLabels())
                ->required()
                ->native(false),
            Forms\Components\DatePicker::make('data_inspecao')->label('Data da Inspeção')->required(),
            Forms\Components\DatePicker::make('data_proxima_inspecao')->label('Próxima Inspeção'),
            Forms\Components\Select::make('resultado')
                ->label('Resultado')
                ->options(Nr13Inspection::resultadoLabels())
                ->native(false),
            Forms\Components\TextInput::make('responsavel_tecnico')->label('Responsável Técnico'),
            Forms\Components\TextInput::make('numero_art')->label('Número da ART'),
            Forms\Components\Textarea::make('observacoes')->label('Observações')->columnSpanFull(),
            Forms\Components\SpatieMediaLibraryFileUpload::make('laudo')
                ->label('Laudo')
                ->collection('laudo')
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
                    ->formatStateUsing(fn (string $state) => Nr13Inspection::tipoLabels()[$state] ?? $state),
                Tables\Columns\TextColumn::make('data_inspecao')->label('Realizada em')->date('d/m/Y'),
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
                    }),
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
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }
}
