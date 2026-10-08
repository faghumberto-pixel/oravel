<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PropostaComercialTemplateResource\Pages;
use App\Models\PropostaComercialTemplate;
use App\Services\PropostaTemplateImportador;
use Filament\Forms;
use Filament\Notifications\Notification;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Filament\Forms\Form;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * CRUD raso -- o admin de cada tenant define seus próprios termos padrão
 * de proposta. Ao criar uma PropostaComercial, default_terms é copiado
 * (não referenciado) pra terms -- ver PropostaComercial::fillFromTemplate().
 */
class PropostaComercialTemplateResource extends BaseResource
{
    protected static ?string $model = PropostaComercialTemplate::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-duplicate';

    protected static ?string $navigationGroup = 'Comercial';

    protected static ?string $navigationParentItem = 'Gestão Comercial';

    protected static ?int $navigationSort = 11;

    protected static ?string $modelLabel = 'Template de Proposta';

    protected static ?string $pluralModelLabel = 'Templates de Proposta';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Começar a partir da proposta que você já usa')
                ->description('Envie uma foto ou imagem da proposta atual. O sistema lê e preenche o nome, o cabeçalho, os termos, a validade e os campos abaixo. Revise e ajuste antes de salvar.')
                ->schema([
                    Forms\Components\FileUpload::make('imagem_referencia')
                        ->label('Imagem da proposta')
                        ->image()
                        ->disk('local')
                        ->directory('templates-proposta')
                        ->maxSize(8192)
                        ->live(),
                    Forms\Components\Actions::make([
                        Forms\Components\Actions\Action::make('ler_imagem')
                            ->label('Ler imagem e preencher')
                            ->icon('heroicon-o-sparkles')
                            ->action(function (Forms\Get $get, Forms\Set $set) {
                                $arquivo = collect((array) $get('imagem_referencia'))->first();

                                if ($arquivo instanceof UploadedFile) {
                                    $conteudo = $arquivo->get();
                                    $mime = (string) $arquivo->getMimeType();
                                } elseif (is_string($arquivo) && Storage::disk('local')->exists($arquivo)) {
                                    $conteudo = Storage::disk('local')->get($arquivo);
                                    $mime = (string) Storage::disk('local')->mimeType($arquivo);
                                } else {
                                    Notification::make()->title('Envie a imagem primeiro')->warning()->send();

                                    return;
                                }

                                $resultado = app(PropostaTemplateImportador::class)->analisar($conteudo, $mime);

                                if (! $resultado['ok']) {
                                    Notification::make()->title('Não consegui ler a imagem')->body($resultado['error'])->danger()->send();

                                    return;
                                }

                                $dados = $resultado['data'];
                                $set('name', $dados['nome'] ?: $get('name'));
                                $set('cabecalho', $dados['cabecalho']);
                                $set('default_terms', $dados['termos']);
                                $set('default_valid_days', $dados['validade_dias']);
                                $set('campos', $dados['campos']);

                                Notification::make()->title('Template preenchido')->body('Revise os campos e salve.')->success()->send();
                            }),
                    ]),
                ]),
            Forms\Components\Section::make('Template de Proposta Comercial')
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->label('Nome')
                        ->required()
                        ->maxLength(255),
                    Forms\Components\Toggle::make('is_default')
                        ->label('Padrão')
                        ->helperText('Usado automaticamente quando o vendedor não escolher um template específico.'),
                    Forms\Components\Toggle::make('is_active')
                        ->label('Ativo')
                        ->default(true),
                    Forms\Components\TextInput::make('default_valid_days')
                        ->label('Validade padrão (dias)')
                        ->numeric()
                        ->helperText('Sugere a data de validade da proposta -- editável pelo vendedor.'),
                    Forms\Components\Textarea::make('cabecalho')
                        ->label('Cabeçalho da empresa')
                        ->helperText('Dados que aparecem no topo da proposta enviada ao cliente.')
                        ->rows(3)
                        ->columnSpanFull(),
                    Forms\Components\Textarea::make('default_terms')
                        ->label('Termos padrão')
                        ->rows(8)
                        ->columnSpanFull(),
                    Forms\Components\Repeater::make('campos')
                        ->label('Campos da proposta')
                        ->helperText('Seções que o cliente vê na proposta, na ordem em que aparecem.')
                        ->schema([
                            Forms\Components\TextInput::make('titulo')->label('Título')->required(),
                            Forms\Components\Textarea::make('texto')->label('Texto padrão')->rows(3),
                        ])
                        ->defaultItems(0)
                        ->addActionLabel('Adicionar campo')
                        ->reorderable()
                        ->collapsible()
                        ->columnSpanFull(),
                ])
                ->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nome')
                    ->searchable(),
                Tables\Columns\IconColumn::make('is_default')
                    ->label('Padrão')
                    ->boolean(),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Ativo')
                    ->boolean(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Criado em')
                    ->dateTime('d/m/Y'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPropostaComercialTemplates::route('/'),
            'create' => Pages\CreatePropostaComercialTemplate::route('/create'),
            'edit' => Pages\EditPropostaComercialTemplate::route('/{record}/edit'),
        ];
    }
}
