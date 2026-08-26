<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasSuperAdminTenantColumn;
use App\Filament\Resources\ClientResource\Pages;
use App\Models\Client;
use App\Notifications\ClientPortalAccessGranted;
use App\Services\CepGeocodingService;
use App\Traits\HasPlanAuthorization;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
// Adicionado para identificar o Tenant
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Throwable;

class ClientResource extends Resource
{
    use HasPlanAuthorization;
    use HasSuperAdminTenantColumn;

    protected static ?string $model = Client::class;

    // AJUSTE 1: Garante isolamento por tenant
    protected static bool $isScopedToTenant = true;

    protected static ?string $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $navigationLabel = 'Clientes';

    protected static ?string $navigationGroup = 'Comercial';

    protected static ?string $tenantRelationshipName = 'clients';

    public static function getNavigationBadge(): ?string
    {
        return 'G C';
    }

    public static function getNavigationBadgeColor(): string|array|null
    {
        return 'gray';
    }

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
                                Forms\Components\Select::make('activity_type')
                                    ->label('Nicho')
                                    ->options(Client::nicheLabels())
                                    ->native(false)
                                    ->helperText('Usado pra sugerir campos relevantes nas Ordens de Serviço deste cliente (Prazo Fatal, Chamado de Emergência, etc).'),
                            ])->columns(2),
                            Forms\Components\Section::make('Endereço de Faturamento')->schema([
                                Forms\Components\TextInput::make('address')->label('Logradouro e Nº')->maxLength(255),
                                Forms\Components\TextInput::make('address_complement')->label('Complemento')->maxLength(255),
                                Forms\Components\TextInput::make('neighborhood')->label('Bairro')->maxLength(100),
                                Forms\Components\TextInput::make('city')->label('Cidade')->maxLength(100),
                                Forms\Components\TextInput::make('state')->label('UF')->maxLength(2),
                                Forms\Components\TextInput::make('zip_code')
                                    ->label('CEP')
                                    ->maxLength(15)
                                    ->live(onBlur: true)
                                    ->helperText('Usado pra plotar o Cliente no Mapa de Equipamentos, junto com Logradouro/Cidade/UF acima.')
                                    ->afterStateUpdated(function (Get $get, Set $set, ?string $state) {
                                        if (! $state) {
                                            return;
                                        }

                                        $fullAddress = trim(implode(', ', array_filter([
                                            $get('address'),
                                            $get('city'),
                                            $get('state'),
                                        ])));

                                        if (! $fullAddress) {
                                            return;
                                        }

                                        $coords = app(CepGeocodingService::class)->geocodeAddress($fullAddress);

                                        if ($coords) {
                                            $set('latitude', $coords['latitude']);
                                            $set('longitude', $coords['longitude']);
                                            Notification::make()->title('Cliente localizado no mapa.')->success()->send();
                                        } else {
                                            $set('latitude', null);
                                            $set('longitude', null);
                                            Notification::make()->title('CEP preenchido, mas não foi possível localizar no mapa automaticamente.')->warning()->send();
                                        }
                                    }),
                                Forms\Components\Hidden::make('latitude'),
                                Forms\Components\Hidden::make('longitude'),
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
                                Forms\Components\TextInput::make('email')
                                    ->label('E-mail de Contato (Principal)')
                                    ->email()
                                    ->helperText('Usado como destinatário padrão em envios (ex: orçamentos), quando os e-mails setoriais abaixo não se aplicam.'),
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
                    Forms\Components\Tabs\Tab::make('Resumo Financeiro')
                        ->icon('heroicon-o-banknotes')
                        ->schema([
                            Forms\Components\Placeholder::make('financial_summary')
                                ->label('')
                                ->content(function (?Client $record) {
                                    if (! $record) {
                                        return new HtmlString('<span class="text-gray-400">Disponível após o primeiro salvamento.</span>');
                                    }
                                    $s = $record->getFinancialSummary();
                                    $resultColor = $s['result'] >= 0 ? '#16a34a' : '#dc2626';
                                    $fmt = fn ($v) => number_format($v, 2, ',', '.');

                                    return new HtmlString(
                                        "<div class='grid grid-cols-3 gap-4 text-sm p-3 bg-gray-50 dark:bg-gray-800 rounded-lg'>".
                                        "<div><span class='text-gray-400'>Receita de Contratos:</span><br><b>R\$ {$fmt($s['total_rental_revenue'])}</b></div>".
                                        "<div><span class='text-gray-400'>Receita de Excedente de Franquia:</span><br><b>R\$ {$fmt($s['total_overage_revenue'])}</b></div>".
                                        "<div><span class='text-gray-400'>Receita de Avaria Cobrada:</span><br><b>R\$ {$fmt($s['total_damage_revenue'])}</b></div>".
                                        "<div><span class='text-gray-400'>Receita Total:</span><br><b>R\$ {$fmt($s['total_revenue'])}</b></div>".
                                        "<div><span class='text-gray-400'>Custo de Manutenção (O.S. do cliente):</span><br><b>R\$ {$fmt($s['total_maintenance_cost'])}</b></div>".
                                        "<div><span class='text-gray-400'>Resultado:</span><br><b style='color: {$resultColor}'>R\$ {$fmt($s['result'])}</b></div>".
                                        '</div>'
                                    );
                                }),
                        ]),
                ])->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                static::tenantColumn(),
                Tables\Columns\TextColumn::make('name')->label('Razão Social')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('document')->label('CNPJ')->searchable(),
                Tables\Columns\TextColumn::make('city')->label('Cidade')->sortable(),
                Tables\Columns\TextColumn::make('state')->label('UF'),
                Tables\Columns\TextColumn::make('activity_type')
                    ->label('Nicho')
                    ->badge()
                    ->formatStateUsing(fn (?string $state) => $state ? (Client::nicheLabels()[$state] ?? $state) : null)
                    ->placeholder('—'),
            ])
            ->filters([
                Tables\Filters\TrashedFilter::make(),
                Tables\Filters\SelectFilter::make('activity_type')
                    ->label('Nicho')
                    ->options(Client::nicheLabels()),
                Tables\Filters\SelectFilter::make('state')
                    ->label('UF')
                    ->options(fn () => Client::query()->whereNotNull('state')->distinct()->pluck('state', 'state')),
                Tables\Filters\Filter::make('com_contrato_ativo')
                    ->label('Com Contrato Ativo')
                    ->query(fn (Builder $query) => $query->whereHas('contracts', fn ($q) => $q->where('status', 'Ativo')))
                    ->toggle(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('grantPortalAccess')
                    ->label(fn (Client $record) => $record->portal_access_enabled_at ? 'Reenviar acesso' : 'Conceder acesso ao portal')
                    ->icon('heroicon-o-key')
                    ->visible(fn (Client $record) => filled($record->email))
                    ->requiresConfirmation()
                    ->modalDescription('Uma senha temporária será gerada e enviada por e-mail ao cliente.')
                    ->action(function (Client $record) {
                        $temporaryPassword = Str::password(12);

                        $record->update([
                            'password' => $temporaryPassword,
                            'portal_access_enabled_at' => now(),
                        ]);

                        // Senha/flag já ficam salvas mesmo se o e-mail falhar
                        // (SMTP fora do ar, credencial inválida, etc.) -- sem
                        // isso uma falha de e-mail virava 500 pro operador,
                        // apesar do acesso já ter sido concedido de verdade.
                        try {
                            $record->notify(new ClientPortalAccessGranted($temporaryPassword));

                            Notification::make()
                                ->title('Acesso ao portal enviado')
                                ->success()
                                ->send();
                        } catch (Throwable $e) {
                            Log::warning('ClientResource: falha ao enviar e-mail de acesso ao portal.', [
                                'client_id' => $record->id, 'error' => $e->getMessage(),
                            ]);

                            Notification::make()
                                ->title('Acesso concedido, mas o e-mail não pôde ser enviado')
                                ->body('Verifique a configuração de e-mail do sistema. A senha temporária foi gerada e o acesso já está ativo.')
                                ->warning()
                                ->send();
                        }
                    }),
            ])
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
            ->withoutGlobalScopes([SoftDeletingScope::class]);
    }
}
