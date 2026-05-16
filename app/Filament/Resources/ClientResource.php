<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ClientResource\Pages;
use App\Models\Client;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ClientResource extends Resource
{ 
    protected static ?string $model = Client::class;
    protected static ?string $navigationIcon = 'heroicon-o-user-group';
    protected static ?string $navigationLabel = 'Clientes';
    protected static ?string $navigationGroup = 'GESTÃO COMERCIAL';

    protected static ?string $tenantRelationshipName = 'clients';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Tabs::make('Ficha Cadastral para Integração ERP')
                    ->tabs([
                        
                        // ABA 1: IDENTIFICAÇÃO E FATURAMENTO
                        Forms\Components\Tabs\Tab::make('Identificação e Faturamento')
                            ->icon('heroicon-o-identification')
                            ->schema([
                                Forms\Components\Group::make()
                                    ->schema([
                                        Forms\Components\TextInput::make('name')
                                            ->label('Razão Social')
                                            ->required()
                                            ->maxLength(255),
                                        Forms\Components\TextInput::make('fantasy_name')
                                            ->label('Nome Fantasia')
                                            ->maxLength(255),
                                        Forms\Components\TextInput::make('document')
                                            ->label('CNPJ')
                                            ->maxLength(20),
                                        Forms\Components\TextInput::make('state_registration')
                                            ->label('Inscrição Estadual')
                                            ->maxLength(50),
                                        Forms\Components\TextInput::make('municipal_registration')
                                            ->label('Inscrição Municipal')
                                            ->maxLength(50),
                                        Forms\Components\TextInput::make('tax_regime')
                                            ->label('Regime Tributário')
                                            ->placeholder('Ex: Simples Nacional, Lucro Presumido...')
                                            ->maxLength(100),
                                    ])->columns(2),

                                Forms\Components\Section::make('Endereço de Faturamento')
                                    ->schema([
                                        Forms\Components\TextInput::make('address')
                                            ->label('Logradouro e Nº')
                                            ->maxLength(255),
                                        Forms\Components\TextInput::make('address_complement')
                                            ->label('Complemento')
                                            ->maxLength(255),
                                        Forms\Components\TextInput::make('neighborhood')
                                            ->label('Bairro')
                                            ->maxLength(100),
                                        Forms\Components\TextInput::make('city')
                                            ->label('Cidade')
                                            ->maxLength(100),
                                        Forms\Components\TextInput::make('state')
                                            ->label('UF')
                                            ->maxLength(2),
                                        Forms\Components\TextInput::make('zip_code')
                                            ->label('CEP')
                                            ->maxLength(15),
                                    ])->columns(3),
                            ]),

                        // ABA 2: LOCAL DE ENTREGA E CONTATOS ERP
                        Forms\Components\Tabs\Tab::make('Entrega e Contatos')
                            ->icon('heroicon-o-truck')
                            ->schema([
                                Forms\Components\Section::make('Local de Entrega / Canteiro de Obras')
                                    ->description('Destino do maquinário (geradores, compressores...)')
                                    ->schema([
                                        Forms\Components\TextInput::make('delivery_address')
                                            ->label('Endereço Completo da Obra')
                                            ->columnSpanFull(),
                                        Forms\Components\TextInput::make('site_manager')
                                            ->label('Nome do Responsável na Obra')
                                            ->maxLength(255),
                                        Forms\Components\TextInput::make('site_phone')
                                            ->label('Telefone do Canteiro')
                                            ->tel(),
                                    ])->columns(2),

                                Forms\Components\Section::make('Contatos e Setores (Campos ERP)')
                                    ->schema([
                                        Forms\Components\TextInput::make('phone')
                                            ->label('Telefone Comercial')
                                            ->tel(),
                                        Forms\Components\TextInput::make('whatsapp')
                                            ->label('WhatsApp')
                                            ->tel(),
                                        Forms\Components\TextInput::make('email_financial')
                                            ->label('E-mail Financeiro (XML/Boletos)')
                                            ->email()
                                            ->maxLength(255),
                                        Forms\Components\TextInput::make('email_purchasing')
                                            ->label('E-mail Suprimentos/Compras')
                                            ->email()
                                            ->maxLength(255),
                                    ])->columns(2),
                            ]),

                        // ABA 3: REPRESENTANTE E DOCUMENTOS ATTACHED
                        Forms\Components\Tabs\Tab::make('Legal e Documentação')
                            ->icon('heroicon-o-document-check')
                            ->schema([
                                Forms\Components\Section::make('Representante Legal (Assina o Contrato)')
                                    ->schema([
                                        Forms\Components\TextInput::make('legal_name')
                                            ->label('Nome Completo')
                                            ->maxLength(255),
                                        Forms\Components\TextInput::make('legal_cpf')
                                            ->label('CPF')
                                            ->maxLength(20),
                                        Forms\Components\TextInput::make('legal_rg')
                                            ->label('RG')
                                            ->maxLength(20),
                                        Forms\Components\TextInput::make('legal_role')
                                            ->label('Cargo')
                                            ->maxLength(100),
                                    ])->columns(2),

                                Forms\Components\Section::make('📂 Checklist de Documentos Anexados')
                                    ->description('Marque os documentos que já foram entregues pelo comprador.')
                                    ->schema([
                                        Forms\Components\Checkbox::make('doc_cnpj')
                                            ->label('Cartão CNPJ Atualizado (emitido nos últimos 30 dias)'),
                                        Forms\Components\Checkbox::make('doc_statute')
                                            ->label('Contrato Social Consolidado ou Estatuto com Última Alteração'),
                                        Forms\Components\Checkbox::make('doc_id')
                                            ->label('Documento de Identidade com Foto do Sócio Administrador (RG/CNH)'),
                                        Forms\Components\Checkbox::make('doc_proxy')
                                            ->label('Procuração com firma reconhecida (se assinado por terceiros)'),
                                        Forms\Components\Checkbox::make('doc_address')
                                            ->label('Comprovante de Endereço Comercial Recente (Água, Luz ou Telefone)'),
                                        Forms\Components\Checkbox::make('doc_art')
                                            ->label('Cópia da ART ou CREA da obra (obrigatório para grandes estruturas)'),
                                        Forms\Components\Checkbox::make('doc_registration_form')
                                            ->label('Ficha de Cadastro preenchida e assinada pelo comprador'),
                                    ])->columns(1),
                            ]),

                        // ABA 4: PROCESSO DE ANÁLISE DE RISCO
                        Forms\Components\Tabs\Tab::make('Análise de Risco')
                            ->icon('heroicon-o-shield-check')
                            ->schema([
                                Forms\Components\Placeholder::make('instrucoes')
                                    ->label('🔍 Passos Obrigatórios para Liberação de Equipamentos de Alto Valor')
                                    ->content('Siga os passos abaixo antes de aprovar qualquer locação para mitigar fraudes:'),

                                Forms\Components\Section::make('1. Rastreamento de Fraudes e Bloqueios Internos')
                                    ->compact()
                                    ->schema([
                                        Forms\Components\Toggle::make('check_internal_fraud')
                                            ->label('Consulta de CNPJ Vinculado (Cruzar CPF de sócios com dívidas antigas)')
                                            ->inline(false),
                                        Forms\Components\Toggle::make('check_blacklist')
                                            ->label('Blacklist Interna (Verificar histórico de inadimplência ou mau uso do maquinário)')
                                            ->inline(false),
                                    ]),

                                Forms\Components\Section::make('2. Análise Cadastral e Restrições Externas')
                                    ->compact()
                                    ->schema([
                                        Forms\Components\Toggle::make('check_credit_bureau')
                                            ->label('Birôs de Crédito (Serasa/SPC): Checar protestos, falências ou recuperação judicial')
                                            ->inline(false),
                                        Forms\Components\TextInput::make('credit_score')
                                            ->label('Score de Crédito PJ')
                                            ->numeric()
                                            ->placeholder('Ex: 650'),
                                        Forms\Components\Toggle::make('check_query_history')
                                            ->label('Histórico de Consultas: Monitorar alta concentração de consultas recentes (indício de golpe)')
                                            ->inline(false),
                                    ]),

                                Forms\Components\Section::make('3. Validação de Idoneidade Comercial')
                                    ->compact()
                                    ->schema([
                                        Forms\Components\Toggle::make('check_sinintegra')
                                            ->label('Sintegra / CCC: Confirmar se a Inscrição Estadual está como ATIVA')
                                            ->inline(false),
                                        Forms\Components\Textarea::make('commercial_references')
                                            ->label('Referências Comerciais (Fornecedores consultados, pontualidade e conservação de bens locados)')
                                            ->rows(2),
                                    ]),
                            ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('Razão Social')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('fantasy_name')->label('Nome Fantasia')->searchable(),
                Tables\Columns\TextColumn::make('document')->label('CNPJ')->searchable(),
                Tables\Columns\TextColumn::make('city')->label('Cidade'),
            ])
            ->filters([
                Tables\Filters\TrashedFilter::make(), 
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
                Tables\Actions\RestoreAction::make(),
                Tables\Actions\ForceDeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListClients::route('/'),
            'create' => Pages\CreateClient::route('/create'),
            'edit' => Pages\EditClient::route('/{record}/edit'),
        ];
    }
}