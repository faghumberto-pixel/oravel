<?php

namespace App\Filament\Resources;

use App\Filament\Attributes\BelongsToFeature;
use App\Filament\Resources\SupplierResource\Pages;
use App\Models\Supplier;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

#[BelongsToFeature('suppliers')]
class SupplierResource extends Resource
{
    protected static ?string $model = Supplier::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $navigationIcon = 'heroicon-o-building-office-2';

    protected static ?string $navigationGroup = 'Ativos e Materiais';

    protected static ?int $navigationSort = 12;

    protected static ?string $modelLabel = 'Fornecedor';

    protected static ?string $pluralModelLabel = 'Fornecedores';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Group::make()
                    ->schema([
                        Forms\Components\Section::make('Identificação do Fornecedor')
                            ->schema([
                                Forms\Components\TextInput::make('name')
                                    ->label('Razão Social / Nome')
                                    ->required()
                                    ->maxLength(255),

                                Forms\Components\TextInput::make('document')
                                    ->label('CNPJ / CPF')
                                    ->required(),

                                Forms\Components\TextInput::make('email')
                                    ->label('E-mail')
                                    ->email()
                                    ->maxLength(255),

                                Forms\Components\TextInput::make('phone')
                                    ->label('Telefone / WhatsApp')
                                    ->tel(),
                            ])->columns(2),

                        Forms\Components\Section::make('Dados Bancários')
                            ->schema([
                                Forms\Components\TextInput::make('bank_account_pix')
                                    ->label('Conta Bancária / Chave PIX')
                                    ->maxLength(255),
                            ]),
                    ])->columnSpan(['lg' => 2]),

                Forms\Components\Group::make()
                    ->schema([
                        Forms\Components\Section::make('Critérios de Homologação')
                            ->description('Avaliação estrita de requisitos da empresa')
                            ->schema([
                                Forms\Components\Toggle::make('compliance_ceis_cnep')
                                    ->label('Regularidade CEIS/CNEP')
                                    ->helperText('Consulta de empresas inidôneas e suspensas')
                                    ->inline(false),

                                Forms\Components\Toggle::make('lista_trabalho_escravo')
                                    ->label('Fora da Lista de Trabalho Escravo')
                                    ->helperText('Validação do Ministério do Trabalho')
                                    ->inline(false),

                                Forms\Components\Toggle::make('termo_lgpd')
                                    ->label('Termo de Consentimento LGPD')
                                    ->helperText('Assinado e em conformidade')
                                    ->inline(false),
                            ]),

                        Forms\Components\Section::make('Arquivos e Certidões')
                            ->schema([
                                Forms\Components\TextInput::make('cnpj_card')->label('Cartão CNPJ (Link/Ref)'),
                                Forms\Components\TextInput::make('inscricao_estadual')->label('Inscrição Estadual'),
                                Forms\Components\TextInput::make('contrato_social')->label('Contrato Social'),
                                Forms\Components\TextInput::make('cnd_federal')->label('CND Federal'),
                                Forms\Components\TextInput::make('crf_fgts')->label('CRF FGTS'),
                                Forms\Components\TextInput::make('cndt')->label('CNDT Trabalhista'),
                            ])->collapsed(),
                    ])->columnSpan(['lg' => 1]),
            ])->columns(3);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Fornecedor')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('document')
                    ->label('Documento')
                    ->searchable(),

                // STATUS DE COMPLIANCE VISUAL
                Tables\Columns\IconColumn::make('termo_lgpd')
                    ->label('LGPD')
                    ->boolean(),

                Tables\Columns\IconColumn::make('lista_trabalho_escravo')
                    ->label('Ficha Limpa MTE')
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('termo_lgpd')
                    ->label('Apenas com Termo LGPD'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSuppliers::route('/'),
            'create' => Pages\CreateSupplier::route('/create'),
            'edit' => Pages\EditSupplier::route('/{record}/edit'),
        ];
    }
}
