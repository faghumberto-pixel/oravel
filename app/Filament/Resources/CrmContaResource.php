<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CrmContaResource\Pages;
use App\Filament\Resources\CrmContaResource\RelationManagers\ContatosRelationManager;
use App\Filament\Resources\CrmContaResource\RelationManagers\NegociosRelationManager;
use App\Models\CrmConta;
use App\Models\CrmLead;
use App\Models\User;
use App\Services\CepGeocodingService;
use App\Support\CrmCamposCustom;
use App\Support\CrmCnpjPreenchimento;
use App\Support\Tenancy;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/** Contas: cadastro simples de clientes do CRM, sem depender do cadastro de Clientes do ERP. */
class CrmContaResource extends BaseResource
{
    protected static ?string $model = CrmConta::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-office-2';

    protected static ?string $navigationLabel = 'Contas (Clientes)';

    protected static ?string $navigationGroup = 'Comercial';

    protected static ?string $navigationParentItem = 'Gestão CRM';

    protected static ?int $navigationSort = 3;

    protected static ?string $modelLabel = 'Conta';

    protected static ?string $pluralModelLabel = 'Contas';

    protected static ?string $recordTitleAttribute = 'nome';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Identificação')->columns(3)->schema([
                Forms\Components\TextInput::make('nome')->label('Nome / Razão social')->required()->maxLength(255)->columnSpan(2),
                Forms\Components\TextInput::make('nome_fantasia')->label('Nome fantasia')->maxLength(255),
                Forms\Components\TextInput::make('documento')
                    ->label('CNPJ/CPF')
                    ->maxLength(20)
                    ->suffixAction(CrmCnpjPreenchimento::action('conta'))
                    ->rules([fn (?CrmConta $record) => function (string $attribute, $value, \Closure $fail) use ($record) {
                        $existente = CrmConta::porDocumento((string) (Tenancy::current()?->id), $value);

                        if ($existente && $existente->id !== $record?->id) {
                            $fail("Já existe a conta \"{$existente->nome}\" com este CNPJ/CPF.");
                        }
                    }]),
                Forms\Components\TextInput::make('segmento')->label('Segmento')->maxLength(100),
                Forms\Components\Select::make('responsavel_user_id')
                    ->label('Responsável')
                    ->options(fn () => User::where('tenant_id', Tenancy::current()?->id)->orderBy('name')->pluck('name', 'id'))
                    ->searchable(),
                Forms\Components\Toggle::make('ativa')->label('Conta ativa')->default(true),
            ]),
            Forms\Components\Section::make('Contato')->columns(3)->schema([
                Forms\Components\TextInput::make('email')->label('E-mail')->email(),
                Forms\Components\TextInput::make('telefone')->label('Telefone')->tel(),
                Forms\Components\TextInput::make('whatsapp')->label('WhatsApp')->tel(),
            ]),
            Forms\Components\Section::make('Endereço')->columns(3)->collapsible()->schema([
                Forms\Components\TextInput::make('cep')
                    ->label('CEP')
                    ->live(onBlur: true)
                    ->afterStateUpdated(function (Forms\Set $set, ?string $state) {
                        if (! $state) {
                            return;
                        }

                        $servico = app(CepGeocodingService::class);
                        $endereco = $servico->lookupCep($state);

                        if (! $endereco) {
                            Notification::make()->title('CEP não encontrado.')->warning()->send();

                            return;
                        }

                        $set('endereco', $endereco['address']);
                        $set('cidade', $endereco['city']);
                        $set('uf', $endereco['uf']);

                        $coords = $servico->geocodeAddress(trim($endereco['address'].', '.$endereco['city'].' - '.$endereco['uf']));
                        $set('latitude', $coords['latitude'] ?? null);
                        $set('longitude', $coords['longitude'] ?? null);
                    }),
                Forms\Components\TextInput::make('endereco')->label('Endereço')->columnSpan(2)->helperText('Complemente com o número, se necessário.'),
                Forms\Components\TextInput::make('cidade')->label('Cidade'),
                Forms\Components\TextInput::make('uf')->label('UF')->maxLength(2),
                Forms\Components\Hidden::make('latitude'),
                Forms\Components\Hidden::make('longitude'),
            ]),
            Forms\Components\Section::make('Observações')->collapsible()->collapsed()->schema([
                Forms\Components\Textarea::make('observacoes')->label('Observações')->rows(3),
            ]),
            ...array_filter([CrmCamposCustom::secao('conta')]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('nome')
            ->columns([
                Tables\Columns\TextColumn::make('nome')->label('Conta')->searchable()->sortable()->description(fn (CrmConta $r) => $r->nome_fantasia),
                Tables\Columns\TextColumn::make('documento')->label('CNPJ/CPF')->searchable()->placeholder('—'),
                Tables\Columns\TextColumn::make('cidade')->label('Cidade')->placeholder('—')->toggleable(),
                Tables\Columns\TextColumn::make('uf')->label('UF')->placeholder('—')->toggleable(),
                Tables\Columns\TextColumn::make('responsavel.name')->label('Responsável')->placeholder('—')->toggleable(),
                Tables\Columns\TextColumn::make('leads_count')->label('Negócios')->counts('leads'),
                Tables\Columns\IconColumn::make('ativa')->label('Ativa')->boolean(),
                Tables\Columns\IconColumn::make('client_id')->label('No ERP')->boolean()->getStateUsing(fn (CrmConta $r) => (bool) $r->client_id)
                    ->visible(fn () => CrmLeadResource::temErp())->toggleable(),

                ...CrmCamposCustom::colunas('conta'),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('ativa')->label('Ativa'),
                Tables\Filters\SelectFilter::make('responsavel_user_id')->label('Responsável')
                    ->options(fn () => User::where('tenant_id', Tenancy::current()?->id)->orderBy('name')->pluck('name', 'id')),
                Tables\Filters\SelectFilter::make('uf')->label('UF')
                    ->options(fn () => CrmConta::query()->whereNotNull('uf')->distinct()->orderBy('uf')->pluck('uf', 'uf')),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('enviar_para_erp')
                    ->label('Enviar para Clientes do ERP')
                    ->icon('heroicon-o-arrow-up-on-square')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->modalDescription('Cria o Cliente no cadastro do sistema principal (ERP) com os contatos desta conta. É opcional.')
                    ->visible(fn (CrmConta $record) => ! $record->client_id && CrmLeadResource::temErp())
                    ->action(function (CrmConta $record) {
                        $client = $record->enviarParaErp();
                        Notification::make()->title('Cliente criado no ERP.')->success()->send();

                        return redirect(ClientResource::getUrl('edit', ['record' => $client->id]));
                    }),
                Tables\Actions\DeleteAction::make()
                    ->before(function (CrmConta $record, Tables\Actions\DeleteAction $action) {
                        if ($record->leads()->exists()) {
                            Notification::make()->danger()->title('Conta com negócios')
                                ->body('Esta conta está ligada a leads. Desative-a em vez de excluir.')->send();
                            $action->cancel();
                        }
                    }),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = auth()->user();

        // Vendedor sem "ver todos" enxerga as contas que são dele ou onde tem negócio dele.
        if ($user && ! $user->canSeeAllCrmLeads()) {
            $query->where(fn ($q) => $q->where('responsavel_user_id', $user->id)
                ->orWhereIn('id', CrmLead::query()->where('assigned_user_id', $user->id)->whereNotNull('crm_conta_id')->select('crm_conta_id')));
        }

        return $query;
    }

    public static function getRelations(): array
    {
        return [ContatosRelationManager::class, NegociosRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCrmContas::route('/'),
            'create' => Pages\CreateCrmConta::route('/create'),
            'edit' => Pages\EditCrmConta::route('/{record}/edit'),
        ];
    }
}
