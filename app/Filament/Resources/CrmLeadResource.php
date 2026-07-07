<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasSuperAdminTenantColumn;
use App\Filament\Resources\CrmLeadResource\Pages;
use App\Filament\Resources\CrmLeadResource\RelationManagers\InteractionsRelationManager;
use App\Models\CrmLead;
use App\Models\User;
use App\Support\Tenancy;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Tables;
use Filament\Tables\Table;

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
                ->description('Usado no mapa de clientes/leads.')
                ->columns(3)
                ->collapsible()
                ->schema([
                    Forms\Components\TextInput::make('address')->label('Endereço')->columnSpan(2),
                    Forms\Components\TextInput::make('cep')->label('CEP'),
                    Forms\Components\TextInput::make('city')->label('Cidade'),
                    Forms\Components\TextInput::make('uf')->label('UF')->maxLength(2),
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
