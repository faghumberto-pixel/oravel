<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasSuperAdminTenantColumn;
use App\Filament\Resources\CrmLeadResource\Pages;
use App\Filament\Resources\CrmLeadResource\RelationManagers\AssignmentsRelationManager;
use App\Filament\Resources\CrmLeadResource\RelationManagers\ContactsRelationManager;
use App\Filament\Resources\CrmLeadResource\RelationManagers\InteractionsRelationManager;
use App\Models\CrmLead;
use App\Models\User;
use App\Services\CepGeocodingService;
use App\Support\Tenancy;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;

class CrmLeadResource extends BaseResource
{
    use HasSuperAdminTenantColumn;

    protected static ?string $model = CrmLead::class;

    protected static bool $shouldRegisterNavigation = true;

    protected static ?string $navigationIcon = 'heroicon-o-briefcase';

    protected static ?string $navigationLabel = 'Leads (CRM)';

    protected static ?string $navigationGroup = 'Comercial';

    protected static ?string $modelLabel = 'Lead';

    protected static ?string $pluralModelLabel = 'Leads';

    /**
     * Ate aqui, so' o Funil/Mapa/Agenda restringiam vendedor aos proprios
     * leads -- a listagem do Resource nao tinha nenhum getEloquentQuery(),
     * entao qualquer vendedor via TODOS os leads do tenant aqui. Mesmo
     * criterio dos outros 3 lugares (User::canSeeAllCrmLeads()).
     */
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        $user = auth()->user();

        if ($user && ! $user->canSeeAllCrmLeads()) {
            $query->where('assigned_user_id', $user->id);
        }

        return $query;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Identificação')
                ->columns(3)
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->label('Nome do Contato')
                        ->required(),
                    Forms\Components\TextInput::make('company_name')
                        ->label('Empresa'),
                    Forms\Components\Select::make('source')
                        ->label('Origem')
                        ->options(CrmLead::sourceLabels()),
                    Forms\Components\TextInput::make('phone')
                        ->label('Telefone')
                        ->tel(),
                    Forms\Components\TextInput::make('whatsapp')
                        ->label('WhatsApp')
                        ->tel(),
                    Forms\Components\TextInput::make('email')
                        ->label('E-mail')
                        ->email(),
                    Forms\Components\TextInput::make('document')
                        ->label('CNPJ/CPF'),
                    Forms\Components\Select::make('segment')
                        ->label('Segmento')
                        ->options(CrmLead::segmentLabels()),
                    Forms\Components\Select::make('company_size')
                        ->label('Porte')
                        ->options(CrmLead::companySizeLabels()),
                    Forms\Components\TextInput::make('estimated_revenue')
                        ->label('Faturamento Aproximado')
                        ->numeric()
                        ->prefix('R$'),
                    Forms\Components\Textarea::make('equipment_interest')
                        ->label('Equipamento de Interesse')
                        ->placeholder('Ex: Guindaste 50 toneladas, Gerador 100 kVA...')
                        ->columnSpanFull(),
                ]),

            Forms\Components\Section::make('Funil')
                ->columns(3)
                ->schema([
                    Forms\Components\Select::make('stage')
                        ->label('Estágio')
                        ->options(CrmLead::stageLabels())
                        ->default(CrmLead::STAGE_NOVO)
                        ->live()
                        ->required(),
                    Forms\Components\Select::make('assigned_user_id')
                        ->label('Vendedor Responsável')
                        ->options(fn () => User::where('tenant_id', Tenancy::current()?->id)->pluck('name', 'id'))
                        ->searchable()
                        ->preload(),
                    Forms\Components\TextInput::make('estimated_value')
                        ->label('Valor Estimado')
                        ->numeric()
                        ->prefix('R$'),
                    Forms\Components\Select::make('lost_reason_category')
                        ->label('Motivo da Perda')
                        ->options(CrmLead::lostReasonCategoryLabels())
                        ->live()
                        ->visible(fn (Forms\Get $get) => $get('stage') === CrmLead::STAGE_PERDIDO)
                        ->required(fn (Forms\Get $get) => $get('stage') === CrmLead::STAGE_PERDIDO),
                    Forms\Components\Textarea::make('lost_reason')
                        ->label(fn (Forms\Get $get) => $get('lost_reason_category') === CrmLead::LOST_REASON_CONCORRENCIA ? 'Qual concorrente?' : 'Detalhe do Motivo')
                        ->visible(fn (Forms\Get $get) => $get('stage') === CrmLead::STAGE_PERDIDO)
                        ->required(fn (Forms\Get $get) => $get('stage') === CrmLead::STAGE_PERDIDO
                            && in_array($get('lost_reason_category'), CrmLead::lostReasonCategoriesRequiringDetail(), true))
                        ->columnSpanFull(),
                    Forms\Components\Textarea::make('won_notes')
                        ->label('Notas de Fechamento')
                        ->visible(fn (Forms\Get $get) => $get('stage') === CrmLead::STAGE_CONVERTIDO)
                        ->columnSpanFull(),
                ]),

            Forms\Components\Section::make('Endereço')
                ->description('Preencha o CEP: endereço e localização no Mapa de Leads são preenchidos automaticamente.')
                ->columns(3)
                ->collapsible()
                ->schema([
                    Forms\Components\TextInput::make('cep')
                        ->label('CEP')
                        ->placeholder('00000-000')
                        ->live(onBlur: true)
                        ->afterStateUpdated(function (Forms\Set $set, ?string $state) {
                            if (! $state) {
                                return;
                            }

                            $service = app(CepGeocodingService::class);
                            $endereco = $service->lookupCep($state);

                            if (! $endereco) {
                                Notification::make()->title('CEP não encontrado.')->warning()->send();

                                return;
                            }

                            $set('address', $endereco['address']);
                            $set('city', $endereco['city']);
                            $set('uf', $endereco['uf']);

                            $fullAddress = trim($endereco['address'].', '.$endereco['city'].' - '.$endereco['uf']);
                            $coords = $service->geocodeAddress($fullAddress);

                            if ($coords) {
                                $set('latitude', $coords['latitude']);
                                $set('longitude', $coords['longitude']);
                                Notification::make()->title('Endereço localizado no mapa.')->success()->send();
                            } else {
                                $set('latitude', null);
                                $set('longitude', null);
                                Notification::make()->title('Endereço preenchido, mas não foi possível localizar no mapa automaticamente.')->warning()->send();
                            }
                        }),
                    Forms\Components\TextInput::make('address')->label('Endereço')->columnSpan(2)->helperText('Complemente com o número, se necessário.'),
                    Forms\Components\TextInput::make('city')->label('Cidade'),
                    Forms\Components\TextInput::make('uf')->label('UF')->maxLength(2),
                    Forms\Components\Placeholder::make('geo_status')
                        ->label('Localização no Mapa de Leads')
                        ->columnSpanFull()
                        ->content(fn (Forms\Get $get) => $get('latitude') && $get('longitude')
                            ? new HtmlString('<span class="text-emerald-500 font-semibold">✓ Localizado no mapa</span>')
                            : new HtmlString('<span class="text-gray-400">Ainda não localizado — preencha o CEP acima.</span>')),
                    Forms\Components\Hidden::make('latitude'),
                    Forms\Components\Hidden::make('longitude'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                static::tenantColumn(),

                Tables\Columns\TextColumn::make('name')
                    ->label('Lead')
                    ->searchable()
                    ->description(fn (CrmLead $record): ?string => $record->company_name),

                Tables\Columns\TextColumn::make('stage')
                    ->label('Estágio')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => CrmLead::stageLabels()[$state] ?? $state)
                    ->color(fn (string $state) => match ($state) {
                        CrmLead::STAGE_CONVERTIDO => 'success',
                        CrmLead::STAGE_PERDIDO => 'danger',
                        CrmLead::STAGE_QUALIFICADO => 'warning',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('client_id')
                    ->label('Cliente')
                    ->badge()
                    ->color('success')
                    ->formatStateUsing(fn () => 'Convertido')
                    ->url(fn (CrmLead $record) => $record->client_id ? ClientResource::getUrl('edit', ['record' => $record->client_id]) : null)
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('assignedUser.name')
                    ->label('Vendedor')
                    ->placeholder('Sem vendedor'),

                Tables\Columns\TextColumn::make('next_followup_date')
                    ->label('Próximo Follow-up')
                    ->date('d/m/Y')
                    ->placeholder('—')
                    ->sortable()
                    ->color(fn (CrmLead $record) => $record->next_followup_date?->isPast() ? 'danger' : null),

                Tables\Columns\TextColumn::make('estimated_value')
                    ->label('Valor Estimado')
                    ->money('BRL')
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Criado em')
                    ->date('d/m/Y')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('stage')
                    ->label('Estágio')
                    ->options(CrmLead::stageLabels()),
                Tables\Filters\SelectFilter::make('assigned_user_id')
                    ->label('Vendedor')
                    ->relationship(
                        name: 'assignedUser',
                        titleAttribute: 'name',
                        modifyQueryUsing: function (Builder $query) {
                            $tenant = Tenancy::current();

                            return $query->when($tenant, fn (Builder $q) => $q->where('tenant_id', $tenant->id));
                        },
                    ),
                Tables\Filters\TernaryFilter::make('sem_followup')
                    ->label('Sem próximo follow-up')
                    ->queries(
                        true: fn ($query) => $query->whereDoesntHave('interactions'),
                        false: fn ($query) => $query->whereHas('interactions'),
                    ),
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('converter_em_cliente')
                    ->label('Converter em Cliente')
                    ->icon('heroicon-o-user-plus')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalDescription('Cria um Cliente formal com o endereço e contato já cadastrados neste Lead. Não cria nenhum Contrato -- isso continua sendo feito manualmente quando a locação acontecer.')
                    ->visible(fn (CrmLead $record) => $record->stage === CrmLead::STAGE_CONVERTIDO && ! $record->client_id)
                    ->action(function (CrmLead $record) {
                        $client = $record->convertToClient();

                        Notification::make()
                            ->title('Cliente criado a partir do Lead.')
                            ->success()
                            ->send();

                        return redirect(ClientResource::getUrl('edit', ['record' => $client->id]));
                    }),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            ContactsRelationManager::class,
            InteractionsRelationManager::class,
            AssignmentsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCrmLeads::route('/'),
            'create' => Pages\CreateCrmLead::route('/create'),
            'edit' => Pages\EditCrmLead::route('/{record}/edit'),
        ];
    }
}
