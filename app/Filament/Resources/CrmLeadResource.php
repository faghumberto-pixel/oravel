<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasSuperAdminTenantColumn;
use App\Filament\Resources\CrmLeadResource\Pages;
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
                    Forms\Components\TextInput::make('source')
                        ->label('Origem')
                        ->placeholder('Indicação, site, ligação ativa...'),
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
                    Forms\Components\Textarea::make('lost_reason')
                        ->label('Motivo da Perda')
                        ->visible(fn (Forms\Get $get) => $get('stage') === CrmLead::STAGE_PERDIDO)
                        ->required(fn (Forms\Get $get) => $get('stage') === CrmLead::STAGE_PERDIDO)
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
                    ->relationship('assignedUser', 'name'),
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
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            InteractionsRelationManager::class,
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
