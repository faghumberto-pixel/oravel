<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ContractResource\Pages;
use App\Models\Contract;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ContractResource extends Resource
{
    protected static ?string $model = Contract::class;
    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static ?string $navigationGroup = 'GESTÃO COMERCIAL';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('1. Qualificação das Partes')
                ->schema([
                    Forms\Components\Grid::make(3)->schema([
                        Forms\Components\TextInput::make('cnpj')
                            ->label('CNPJ do Cliente')
                            ->mask('99.999.999/9999-99')
                            ->required(),
                        Forms\Components\Select::make('client_id')
                            ->relationship('client', 'name')
                            ->label('Cliente')
                            ->required()
                            ->columnSpan(2),
                    ]),
                ]),

            Forms\Components\Section::make('2. Objeto e Prazos')
                ->schema([
                    Forms\Components\Grid::make(3)->schema([
                        Forms\Components\TextInput::make('contract_number')
                            ->label('Número do Contrato')
                            ->required(),
                        Forms\Components\Select::make('asset_id')
                            ->relationship('asset', 'name')
                            ->label('Equipamento (Marca/Série)')
                            ->required(),
                        Forms\Components\DatePicker::make('start_date')
                            ->label('Início da Vigência')
                            ->required(),
                    ]),
                ]),

            Forms\Components\Section::make('3. Manutenção, Logística e Riscos')
                ->schema([
                    Forms\Components\Grid::make(2)->schema([
                        Forms\Components\Select::make('responsavel_manutencao')
                            ->options(['locador' => 'Locadora', 'locatario' => 'Locatário'])
                            ->label('Responsável pela Manutenção')
                            ->required(),
                        Forms\Components\Toggle::make('frete_incluso')
                            ->label('Frete de Ida/Volta incluso?'),
                    ]),
                    Forms\Components\RichEditor::make('regras_uso')
                        ->label('Normas de Uso e Segurança (NRs)')
                        ->columnSpanFull(),
                ]),

            Forms\Components\Section::make('4. Responsabilidades e Rescisão')
                ->schema([
                    Forms\Components\Grid::make(2)->schema([
                        Forms\Components\TextInput::make('price')
                            ->label('Valor Mensal')
                            ->prefix('R$')
                            ->numeric()
                            ->required(),
                        Forms\Components\TextInput::make('multa_rescisoria')
                            ->label('Multa Rescisória (%)')
                            ->numeric(),
                    ]),
                    Forms\Components\FileUpload::make('vistoria_entrega')
                        ->label('Laudo de Vistoria de Entrega (PDF/Imagem)')
                        ->directory('contratos/vistorias')
                        ->downloadable()
                        ->previewable(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('contract_number')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('client.name')->label('Cliente'),
                Tables\Columns\TextColumn::make('asset.name')->label('Ativo'),
                Tables\Columns\TextColumn::make('start_date')->date('d/m/Y'),
                Tables\Columns\IconColumn::make('is_active')->boolean()->label('Ativo'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListContracts::route('/'),
            'create' => Pages\CreateContract::route('/create'),
            'edit' => Pages\EditContract::route('/{record}/edit'),
        ];
    }
}