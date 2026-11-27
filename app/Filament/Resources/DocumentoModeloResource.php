<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DocumentoModeloResource\Pages;
use App\Models\DocumentoModelo;
use App\Services\DocumentoModeloImportador;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Modelos de documento da empresa (orçamento, proposta, contrato): o cliente envia o arquivo que já usa e o sistema extrai o modelo, ou monta
 * à mão. Ao imprimir ou enviar um documento do tipo, vale o modelo padrão dele.
 */
class DocumentoModeloResource extends BaseResource
{
    protected static ?string $model = DocumentoModelo::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-duplicate';

    protected static ?string $navigationGroup = 'Comercial';

    protected static ?int $navigationSort = 12;

    protected static ?string $modelLabel = 'Modelo de Documento';

    protected static ?string $pluralModelLabel = 'Modelos de Documento';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Começar do documento que você já usa')
                ->description('Escolha o tipo e envie o arquivo (imagem, PDF ou Word). O sistema lê e preenche o nome, o cabeçalho, as seções ou cláusulas, os termos e o rodapé. Revise e ajuste antes de salvar.')
                ->schema([
                    Forms\Components\Select::make('tipo')
                        ->label('Tipo do documento')
                        ->options(DocumentoModelo::tipos())
                        ->required()
                        ->live(),
                    Forms\Components\FileUpload::make('arquivo_referencia')
                        ->label('Arquivo do modelo')
                        ->disk('local')
                        ->directory('modelos-documento')
                        ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'application/pdf', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'])
                        ->maxSize(10240)
                        ->live(),
                    Forms\Components\Actions::make([
                        Forms\Components\Actions\Action::make('ler_arquivo')
                            ->label('Ler arquivo e preencher')
                            ->icon('heroicon-o-sparkles')
                            ->action(function (Forms\Get $get, Forms\Set $set) {
                                if (! $get('tipo')) {
                                    Notification::make()->title('Escolha o tipo do documento primeiro')->warning()->send();

                                    return;
                                }

                                $arquivo = collect((array) $get('arquivo_referencia'))->first();

                                if ($arquivo instanceof UploadedFile) {
                                    $conteudo = $arquivo->get();
                                    $mime = (string) $arquivo->getMimeType();
                                } elseif (is_string($arquivo) && Storage::disk('local')->exists($arquivo)) {
                                    $conteudo = Storage::disk('local')->get($arquivo);
                                    $mime = (string) Storage::disk('local')->mimeType($arquivo);
                                } else {
                                    Notification::make()->title('Envie o arquivo primeiro')->warning()->send();

                                    return;
                                }

                                $resultado = app(DocumentoModeloImportador::class)->analisar($conteudo, $mime, (string) $get('tipo'));

                                if (! $resultado['ok']) {
                                    Notification::make()->title('Não consegui ler o arquivo')->body($resultado['error'])->danger()->send();

                                    return;
                                }

                                $dados = $resultado['data'];
                                $set('nome', $dados['nome'] ?: $get('nome'));
                                $set('cabecalho', $dados['cabecalho']);
                                $set('rodape', $dados['rodape']);
                                $set('termos', $dados['termos']);
                                $set('validade_dias', $dados['validade_dias']);
                                $set('campos', $dados['campos']);

                                Notification::make()->title('Modelo preenchido')->body('Revise o que foi lido e salve.')->success()->send();
                            }),
                    ]),
                ]),
            Forms\Components\Section::make('Modelo')
                ->schema([
                    Forms\Components\TextInput::make('nome')->label('Nome')->required()->maxLength(255),
                    Forms\Components\Toggle::make('is_default')
                        ->label('Padrão deste tipo')
                        ->helperText('Usado ao imprimir ou enviar. Marcar este desmarca o padrão anterior do mesmo tipo.'),
                    Forms\Components\Toggle::make('is_active')->label('Ativo')->default(true),
                    Forms\Components\TextInput::make('validade_dias')->label('Validade padrão (dias)')->numeric()->minValue(1),
                    Forms\Components\FileUpload::make('logo_path')
                        ->label('Logo da empresa')
                        ->image()
                        ->disk('local')
                        ->directory('modelos-documento/logos')
                        ->maxSize(2048),
                    Forms\Components\Textarea::make('cabecalho')->label('Cabeçalho da empresa')->helperText('Dados que aparecem no topo do documento.')->rows(3)->columnSpanFull(),
                    Forms\Components\Repeater::make('campos')
                        ->label('Seções ou cláusulas')
                        ->helperText('Na ordem em que aparecem no documento. Pode usar marcadores como {{cliente}}, {{cnpj}}, {{valor_total}}, {{data}}, {{numero}}, {{validade}}, {{empresa}} e {{endereco_cliente}}: o sistema preenche ao imprimir.')
                        ->schema([
                            Forms\Components\TextInput::make('titulo')->label('Título')->required(),
                            Forms\Components\Textarea::make('texto')->label('Texto')->rows(4),
                        ])
                        ->defaultItems(0)
                        ->addActionLabel('Adicionar seção')
                        ->reorderable()
                        ->collapsible()
                        ->columnSpanFull(),
                    Forms\Components\Textarea::make('termos')->label('Termos gerais')->rows(6)->columnSpanFull(),
                    Forms\Components\Textarea::make('rodape')->label('Rodapé')->helperText('Foro, dados bancários, observações finais.')->rows(3)->columnSpanFull(),
                ])
                ->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('nome')->label('Nome')->searchable(),
                Tables\Columns\TextColumn::make('tipo')->label('Tipo')->badge()->formatStateUsing(fn (string $state) => DocumentoModelo::tipos()[$state] ?? $state),
                Tables\Columns\IconColumn::make('is_default')->label('Padrão')->boolean(),
                Tables\Columns\IconColumn::make('is_active')->label('Ativo')->boolean(),
                Tables\Columns\TextColumn::make('created_at')->label('Criado em')->dateTime('d/m/Y'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('tipo')->label('Tipo')->options(DocumentoModelo::tipos()),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([Tables\Actions\DeleteBulkAction::make()]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDocumentoModelos::route('/'),
            'create' => Pages\CreateDocumentoModelo::route('/create'),
            'edit' => Pages\EditDocumentoModelo::route('/{record}/edit'),
        ];
    }
}
