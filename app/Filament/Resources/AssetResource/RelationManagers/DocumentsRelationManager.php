<?php

namespace App\Filament\Resources\AssetResource\RelationManagers;

use App\Models\AssetDocument;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * Laudos, notas fiscais, manuais e outros documentos do ativo. O arquivo fica no disco PRIVADO
 * 'media_private' e só abre por link assinado temporário (10 min) gerado aqui.
 */
class DocumentsRelationManager extends RelationManager
{
    protected static string $relationship = 'documents';

    protected static ?string $title = 'Documentos (laudos, notas fiscais...)';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('tipo')
                ->label('Tipo')
                ->options(AssetDocument::tipoLabels())
                ->default(AssetDocument::TIPO_LAUDO)
                ->required()
                ->native(false),
            Forms\Components\TextInput::make('titulo')
                ->label('Título')
                ->placeholder('Ex.: Laudo de inspeção anual / NF de compra')
                ->required()
                ->maxLength(255),
            Forms\Components\TextInput::make('numero')
                ->label('Nº da nota fiscal / do laudo')
                ->maxLength(255),
            Forms\Components\TextInput::make('valor')
                ->label('Valor (R$)')
                ->numeric()
                ->prefix('R$')
                ->minValue(0),
            Forms\Components\DatePicker::make('data_emissao')->label('Data de emissão'),
            Forms\Components\DatePicker::make('data_validade')
                ->label('Validade')
                ->helperText('Para laudos e certificados que vencem.'),
            Forms\Components\SpatieMediaLibraryFileUpload::make('arquivo')
                ->label('Arquivo (PDF, imagem, Word ou Excel — máx. 10 MB)')
                ->collection('arquivo')
                ->maxSize(10240)
                ->openable()
                ->downloadable()
                ->required()
                ->columnSpanFull(),
            Forms\Components\Textarea::make('observacoes')->label('Observações')->columnSpanFull(),
        ])->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('titulo')
            ->columns([
                Tables\Columns\TextColumn::make('tipo')
                    ->label('Tipo')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => AssetDocument::tipoLabels()[$state] ?? $state),
                Tables\Columns\TextColumn::make('titulo')->label('Título')->searchable()->wrap(),
                Tables\Columns\TextColumn::make('numero')->label('Nº')->placeholder('—'),
                Tables\Columns\TextColumn::make('valor')->label('Valor')->money('BRL')->placeholder('—'),
                Tables\Columns\TextColumn::make('data_emissao')->label('Emissão')->date('d/m/Y')->placeholder('—'),
                Tables\Columns\TextColumn::make('data_validade')
                    ->label('Validade')
                    ->date('d/m/Y')
                    ->placeholder('—')
                    ->badge()
                    ->color(fn (AssetDocument $record) => match (true) {
                        $record->isVencido() => 'danger',
                        $record->isProximoVencimento() => 'warning',
                        default => 'success',
                    }),
            ])
            ->defaultSort('created_at', 'desc')
            ->headerActions([
                Tables\Actions\CreateAction::make()->label('Adicionar documento'),
            ])
            ->actions([
                Tables\Actions\Action::make('abrir')
                    ->label('Abrir')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->visible(fn (AssetDocument $record) => $record->getFirstMedia('arquivo') !== null)
                    ->url(fn (AssetDocument $record) => $record->getFirstMedia('arquivo')?->getTemporaryUrl(now()->addMinutes(10)))
                    ->openUrlInNewTab(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }
}
