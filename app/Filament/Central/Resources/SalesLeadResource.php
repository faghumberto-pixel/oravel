<?php

namespace App\Filament\Central\Resources;

use App\Filament\Central\Resources\SalesLeadResource\Pages;
use App\Filament\Central\Resources\SalesLeadResource\RelationManagers\AppointmentsRelationManager;
use App\Filament\Central\Resources\SalesLeadResource\RelationManagers\InteractionsRelationManager;
use App\Models\Client;
use App\Models\Plan;
use App\Models\SalesLead;
use App\Models\User;
use App\Services\CepGeocodingService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class SalesLeadResource extends Resource
{
    protected static ?string $model = SalesLead::class;

    protected static ?string $navigationIcon = 'heroicon-o-briefcase';

    protected static ?string $navigationLabel = 'CRM Comercial';

    protected static ?string $navigationGroup = 'Comercial';

    protected static ?string $modelLabel = 'Lead Comercial';

    protected static ?string $pluralModelLabel = 'Leads Comerciais';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Identificação')
                ->columns(3)
                ->schema([
                    Forms\Components\TextInput::make('company_name')
                        ->label('Empresa')
                        ->required(),
                    Forms\Components\TextInput::make('website')
                        ->label('Site')
                        ->url()
                        ->prefix('https://'),
                    Forms\Components\TextInput::make('phone')
                        ->label('Telefone')
                        ->tel(),
                    Forms\Components\TextInput::make('email')
                        ->label('E-mail')
                        ->email(),
                    Forms\Components\TextInput::make('estimated_contract_value')
                        ->label('Valor Estimado do Contrato (MRR)')
                        ->numeric()
                        ->prefix('R$'),
                    Forms\Components\Select::make('assigned_user_id')
                        ->label('Responsável')
                        ->options(fn () => User::whereIn('email', config('oravel.super_admins', []))->pluck('name', 'id'))
                        ->searchable()
                        ->preload(),

                    Forms\Components\Select::make('segment')
                        ->label('Segmento Principal')
                        // Mesmos valores de tenants.segment/Client::NICHE_* de
                        // proposito -- quando converte, o Tenant nasce com o
                        // segmento certo, sem vocabulario paralelo. Fica
                        // como o valor UNICO usado pelo resto do sistema
                        // (Kanban, mapa, dashboards) -- segmentos extras vao
                        // no repeater abaixo, sem afetar quem depende desse.
                        ->options(Client::nicheLabels()),
                    Forms\Components\Repeater::make('additional_segments')
                        ->label('Outros Segmentos')
                        ->simple(
                            Forms\Components\TextInput::make('segment')
                                ->datalist(array_values(Client::nicheLabels()))
                                ->required(),
                        )
                        ->addActionLabel('+ Adicionar segmento')
                        ->defaultItems(0)
                        ->columnSpan(2),

                    Forms\Components\Select::make('source')
                        ->label('Origem Principal')
                        ->options(SalesLead::sourceLabels()),
                    Forms\Components\Repeater::make('additional_sources')
                        ->label('Outras Origens')
                        ->simple(
                            Forms\Components\TextInput::make('source')
                                ->datalist(array_values(SalesLead::sourceLabels()))
                                ->required(),
                        )
                        ->addActionLabel('+ Adicionar origem')
                        ->defaultItems(0)
                        ->columnSpan(2),

                    Forms\Components\Repeater::make('decision_makers')
                        ->label('Tomadores de Decisão')
                        ->schema([
                            Forms\Components\TextInput::make('name')
                                ->label('Nome')
                                ->required(),
                            Forms\Components\TextInput::make('role')
                                ->label('Cargo'),
                        ])
                        ->columns(2)
                        ->addActionLabel('+ Adicionar tomador de decisão')
                        ->defaultItems(0)
                        ->columnSpanFull(),

                    Forms\Components\Textarea::make('critical_pain')
                        ->label('Dor Crítica Mapeada')
                        ->columnSpanFull(),
                    Forms\Components\Textarea::make('oravel_solution')
                        ->label('Solução Oravel')
                        ->helperText('O que o sistema oferece pra resolver essa dor específica -- vira a base do argumento de venda.')
                        ->columnSpanFull(),
                ]),

            Forms\Components\Section::make('Endereço')
                ->description('Preencha o CEP: endereço e localização no mapa são preenchidos automaticamente.')
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
                    Forms\Components\TextInput::make('address')->label('Endereço')->columnSpan(2),
                    Forms\Components\TextInput::make('city')->label('Cidade'),
                    Forms\Components\TextInput::make('uf')->label('UF')->maxLength(2),
                    Forms\Components\TextInput::make('latitude')->label('Latitude')->numeric()->disabled()->dehydrated(),
                    Forms\Components\TextInput::make('longitude')->label('Longitude')->numeric()->disabled()->dehydrated(),
                ]),

            Forms\Components\Section::make('Funil')
                ->columns(3)
                ->schema([
                    Forms\Components\Placeholder::make('pipeline_stage_display')
                        ->label('Estágio Atual')
                        ->content(fn (?SalesLead $record) => $record
                            ? (SalesLead::stageLabels()[$record->pipeline_stage] ?? $record->pipeline_stage)
                            : SalesLead::stageLabels()[SalesLead::STAGE_PROSPECCAO])
                        ->helperText('O estágio só avança pelas ações "Avançar Estágio" / "Marcar como Perdido" / "Converter em Tenant" -- não é editável direto, de propósito.'),
                    Forms\Components\Select::make('lost_reason')
                        ->label('Motivo da Perda')
                        ->options(SalesLead::lostReasonLabels())
                        ->visible(fn (?SalesLead $record) => $record?->pipeline_stage === SalesLead::STAGE_PERDIDO)
                        ->disabled(),
                    Forms\Components\Textarea::make('lost_reason_detail')
                        ->label('Detalhe da Perda')
                        ->visible(fn (?SalesLead $record) => $record?->pipeline_stage === SalesLead::STAGE_PERDIDO)
                        ->disabled()
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('company_name')->label('Empresa')->searchable()->weight('bold'),
                Tables\Columns\TextColumn::make('segment')
                    ->label('Segmento')
                    ->badge()
                    ->color('gray')
                    ->formatStateUsing(fn (?string $state) => $state ? (Client::nicheLabels()[$state] ?? $state) : null)
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('pipeline_stage')
                    ->label('Estágio')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => SalesLead::stageLabels()[$state] ?? $state)
                    ->color(fn (string $state) => match ($state) {
                        SalesLead::STAGE_GANHO => 'success',
                        SalesLead::STAGE_PERDIDO => 'danger',
                        default => 'warning',
                    }),
                Tables\Columns\TextColumn::make('estimated_contract_value')
                    ->label('Valor Estimado')
                    ->money('BRL')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('assignedUser.name')->label('Responsável')->placeholder('—'),
                Tables\Columns\TextColumn::make('last_interaction_at')
                    ->label('Última Interação')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('Nunca')
                    ->color(fn (?SalesLead $record) => $record?->isOpen() && (! $record->last_interaction_at || $record->last_interaction_at->diffInDays(now()) >= 3) ? 'danger' : null),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('pipeline_stage')
                    ->label('Estágio')
                    ->options(SalesLead::stageLabels()),
                Tables\Filters\SelectFilter::make('segment')
                    ->label('Segmento')
                    ->options(Client::nicheLabels()),
            ])
            ->actions([
                Tables\Actions\Action::make('advance')
                    ->label('Avançar Estágio')
                    ->icon('heroicon-o-arrow-right-circle')
                    ->color('gray')
                    ->visible(fn (SalesLead $record) => $record->isOpen() && $record->nextStage() && $record->nextStage() !== SalesLead::STAGE_GANHO)
                    ->action(function (SalesLead $record) {
                        try {
                            $record->advanceStage();
                            Notification::make()->title('Estágio avançado.')->success()->send();
                        } catch (\RuntimeException $e) {
                            Notification::make()->title('Não foi possível avançar')->body($e->getMessage())->warning()->send();
                        }
                    }),
                Tables\Actions\Action::make('convert')
                    ->label('Converter em Tenant')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->visible(fn (SalesLead $record) => $record->pipeline_stage === SalesLead::STAGE_PROPOSTA_ENVIADA)
                    ->form([
                        Forms\Components\Select::make('plan_id')
                            ->label('Plano')
                            ->options(fn () => Plan::pluck('name', 'id'))
                            ->required(),
                        Forms\Components\TextInput::make('admin_name')
                            ->label('Nome do Admin')
                            ->required(),
                        Forms\Components\TextInput::make('admin_email')
                            ->label('E-mail do Admin')
                            ->email()
                            ->required(),
                        Forms\Components\TextInput::make('admin_password')
                            ->label('Senha Inicial')
                            ->password()
                            ->revealable()
                            ->required(),
                    ])
                    ->mountUsing(fn (Form $form, SalesLead $record) => $form->fill([
                        'admin_name' => $record->primaryDecisionMaker()['name'] ?? null,
                        'admin_email' => $record->email,
                    ]))
                    ->action(function (SalesLead $record, array $data) {
                        try {
                            $record->convertToTenant($data['plan_id'], [
                                'name' => $data['admin_name'],
                                'email' => $data['admin_email'],
                                'password' => $data['admin_password'],
                            ]);
                            Notification::make()->title('Tenant criado com sucesso!')->success()->send();
                        } catch (\RuntimeException $e) {
                            Notification::make()->title('Não foi possível converter')->body($e->getMessage())->warning()->send();
                        }
                    }),
                Tables\Actions\Action::make('mark_lost')
                    ->label('Marcar como Perdido')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (SalesLead $record) => $record->isOpen())
                    ->requiresConfirmation()
                    ->form([
                        Forms\Components\Select::make('lost_reason')
                            ->label('Motivo')
                            ->options(SalesLead::lostReasonLabels())
                            ->required(),
                        Forms\Components\Textarea::make('lost_reason_detail')
                            ->label('Detalhe'),
                    ])
                    ->action(fn (SalesLead $record, array $data) => $record->markLost($data['lost_reason'], $data['lost_reason_detail'] ?? null)),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            InteractionsRelationManager::class,
            AppointmentsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSalesLeads::route('/'),
            'create' => Pages\CreateSalesLead::route('/create'),
            'edit' => Pages\EditSalesLead::route('/{record}/edit'),
        ];
    }
}
