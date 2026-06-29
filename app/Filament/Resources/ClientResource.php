<?php

namespace App\Filament\Resources;

use App\Filament\Attributes\BelongsToFeature;

use App\Filament\Resources\ClientResource\Pages;
use App\Models\Client;
use App\Traits\HasPlanAuthorization;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Facades\Filament; // Adicionado para identificar o Tenant
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

#[BelongsToFeature('clients')]
class ClientResource extends Resource
{ 
    use HasPlanAuthorization;

    
    protected static ?string $model = Client::class;
    
    // AJUSTE 1: Garante isolamento por tenant
    protected static bool $isScopedToTenant = true;
    
    protected static ?string $navigationIcon = 'heroicon-o-user-group';
    protected static ?string $navigationLabel = 'Clientes';
    protected static ?string $navigationGroup = 'GESTÃO COMERCIAL';

    protected static ?string $tenantRelationshipName = 'clients';


    public static function form(Form $form): Form
    {
        // (SEU CÓDIGO ORIGINAL DE TABS FOI MANTIDO INTEGRALMENTE)
        return $form->schema([
            Forms\Components\Tabs::make('Ficha Cadastral para Integração ERP')
                ->tabs([
                    Forms\Components\Tabs\Tab::make('Identificação e Faturamento')
                        ->icon('heroicon-o-identification')
                        ->schema([
                            Forms\Components\Group::make()->schema([
                                Forms\Components\TextInput::make('name')->label('Razão Social')->required()->maxLength(255),
                                Forms\Components\TextInput::make('fantasy_name')->label('Nome Fantasia')->maxLength(255),
                                Forms\Components\TextInput::make('document')->label('CNPJ')->maxLength(20),
                                Forms\Components\TextInput::make('state_registration')->label('Inscrição Estadual')->maxLength(50),
                                Forms\Components\TextInput::make('municipal_registration')->label('Inscrição Municipal')->maxLength(50),
                                Forms\Components\TextInput::make('tax_regime')->label('Regime Tributário')->placeholder('Ex: Simples Nacional, Lucro Presumido...')->maxLength(100),
                            ])->columns(2),
                            Forms\Components\Section::make('Endereço de Faturamento')->schema([
                                Forms\Components\TextInput::make('address')->label('Logradouro e Nº')->maxLength(255),
                                Forms\Components\TextInput::make('address_complement')->label('Complemento')->maxLength(255),
                                Forms\Components\TextInput::make('neighborhood')->label('Bairro')->maxLength(100),
                                Forms\Components\TextInput::make('city')->label('Cidade')->maxLength(100),
                                Forms\Components\TextInput::make('state')->label('UF')->maxLength(2),
                                Forms\Components\TextInput::make('zip_code')->label('CEP')->maxLength(15),
                            ])->columns(3),
                        ]),
                    Forms\Components\Tabs\Tab::make('Entrega e Contatos')
                        ->icon('heroicon-o-truck')
                        ->schema([
                            Forms\Components\Section::make('Local de Entrega / Canteiro de Obras')->schema([
                                Forms\Components\TextInput::make('delivery_address')->label('Endereço Completo da Obra')->columnSpanFull(),
                                Forms\Components\TextInput::make('site_manager')->label('Nome do Responsável na Obra')->maxLength(255),
                                Forms\Components\TextInput::make('site_phone')->label('Telefone do Canteiro')->tel(),
                            ])->columns(2),
                            Forms\Components\Section::make('Contatos e Setores (Campos ERP)')->schema([
                                Forms\Components\TextInput::make('phone')->label('Telefone Comercial')->tel(),
                                Forms\Components\TextInput::make('whatsapp')->label('WhatsApp')->tel(),
                                Forms\Components\TextInput::make('email_financial')->label('E-mail Financeiro')->email(),
                                Forms\Components\TextInput::make('email_purchasing')->label('E-mail Suprimentos')->email(),
                            ])->columns(2),
                        ]),
                    Forms\Components\Tabs\Tab::make('Legal e Documentação')
                        ->icon('heroicon-o-document-check')
                        ->schema([
                            Forms\Components\Section::make('Representante Legal')->schema([
                                Forms\Components\TextInput::make('legal_name')->label('Nome Completo')->maxLength(255),
                                Forms\Components\TextInput::make('legal_cpf')->label('CPF')->maxLength(20),
                                Forms\Components\TextInput::make('legal_rg')->label('RG')->maxLength(20),
                                Forms\Components\TextInput::make('legal_role')->label('Cargo')->maxLength(100),
                            ])->columns(2),
                            Forms\Components\Section::make('📂 Checklist de Documentos Anexados')->schema([
                                Forms\Components\Checkbox::make('doc_cnpj')->label('Cartão CNPJ Atualizado'),
                                Forms\Components\Checkbox::make('doc_statute')->label('Contrato Social'),
                                Forms\Components\Checkbox::make('doc_id')->label('RG/CNH Sócio'),
                                Forms\Components\Checkbox::make('doc_proxy')->label('Procuração'),
                                Forms\Components\Checkbox::make('doc_address')->label('Comprovante Endereço'),
                                Forms\Components\Checkbox::make('doc_art')->label('ART/CREA'),
                                Forms\Components\Checkbox::make('doc_registration_form')->label('Ficha de Cadastro'),
                            ])->columns(1),
                        ]),
                    Forms\Components\Tabs\Tab::make('Análise de Risco')
                        ->icon('heroicon-o-shield-check')
                        ->schema([
                            Forms\Components\Toggle::make('check_internal_fraud')->label('Consulta de CNPJ Vinculado'),
                            Forms\Components\Toggle::make('check_blacklist')->label('Blacklist Interna'),
                            Forms\Components\Toggle::make('check_credit_bureau')->label('Birôs de Crédito'),
                            Forms\Components\TextInput::make('credit_score')->label('Score de Crédito PJ')->numeric(),
                        ]),
                ])->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('Razão Social')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('document')->label('CNPJ')->searchable(),
                Tables\Columns\TextColumn::make('city')->label('Cidade')->sortable(),
                Tables\Columns\TextColumn::make('state')->label('UF'),
            ])
            ->filters([Tables\Filters\TrashedFilter::make()])
            ->actions([Tables\Actions\EditAction::make()])
            ->bulkActions([Tables\Actions\BulkActionGroup::make([Tables\Actions\DeleteBulkAction::make()])]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListClients::route('/'),
            'create' => Pages\CreateClient::route('/create'),
            'edit' => Pages\EditClient::route('/{record}/edit'),
        ];
    }

    // AJUSTE 3: Garante que a Query sempre filtre pelo Tenant logado
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('tenant_id', \App\Support\Tenancy::current()?->id) 
            ->withoutGlobalScopes([SoftDeletingScope::class]);
    }
}