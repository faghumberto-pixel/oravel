<?php

namespace App\Filament\Central\Resources;

use App\Filament\Central\Resources\AnnouncementResource\Pages;
use App\Models\Announcement;
use App\Models\Tenant;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Avisos/mensagens da operacao SaaS pros tenants -- exibidos como banner no
 * topo do painel admin + notificacao no sino (ver AnnouncementObserver,
 * AnnouncementNotification, e o render hook em AdminPanelProvider).
 */
class AnnouncementResource extends Resource
{
    protected static ?string $model = Announcement::class;

    protected static ?string $navigationIcon = 'heroicon-o-megaphone';

    protected static ?string $navigationGroup = 'Gestão SaaS';

    protected static ?string $navigationLabel = 'Avisos aos Tenants';

    protected static ?string $pluralModelLabel = 'Avisos';

    protected static bool $isScopedToTenant = false;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withoutGlobalScopes();
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('title')
                ->label('Título')
                ->required()
                ->maxLength(191),

            Forms\Components\Textarea::make('message')
                ->label('Mensagem')
                ->required()
                ->rows(4),

            Forms\Components\Select::make('level')
                ->label('Nível')
                ->options([
                    Announcement::LEVEL_INFO => 'Informativo',
                    Announcement::LEVEL_WARNING => 'Atenção',
                    Announcement::LEVEL_CRITICAL => 'Crítico',
                ])
                ->default(Announcement::LEVEL_INFO)
                ->required()
                ->native(false),

            Forms\Components\Select::make('target_tenant_id')
                ->label('Destinatário')
                ->placeholder('Todos os tenants')
                ->helperText('Deixe em branco para enviar a todos os tenants.')
                ->options(fn () => Tenant::orderBy('name')->pluck('name', 'id'))
                ->searchable(),

            Forms\Components\Grid::make(2)->schema([
                Forms\Components\DateTimePicker::make('starts_at')
                    ->label('Início (opcional)')
                    ->helperText('Em branco = já vale imediatamente.'),

                Forms\Components\DateTimePicker::make('expires_at')
                    ->label('Expira em (opcional)')
                    ->helperText('Em branco = não expira sozinho.'),
            ]),

            Forms\Components\Toggle::make('is_active')
                ->label('Ativo')
                ->default(true)
                ->helperText('Desative para tirar de circulação sem apagar.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->label('Título')
                    ->searchable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('level')
                    ->label('Nível')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        Announcement::LEVEL_WARNING => 'Atenção',
                        Announcement::LEVEL_CRITICAL => 'Crítico',
                        default => 'Informativo',
                    })
                    ->color(fn (string $state): string => match ($state) {
                        Announcement::LEVEL_WARNING => 'warning',
                        Announcement::LEVEL_CRITICAL => 'danger',
                        default => 'info',
                    }),

                Tables\Columns\TextColumn::make('targetTenant.name')
                    ->label('Destinatário')
                    ->placeholder('Todos os tenants'),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Ativo')
                    ->boolean(),

                Tables\Columns\TextColumn::make('expires_at')
                    ->label('Expira em')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Criado em')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('level')
                    ->label('Nível')
                    ->options([
                        Announcement::LEVEL_INFO => 'Informativo',
                        Announcement::LEVEL_WARNING => 'Atenção',
                        Announcement::LEVEL_CRITICAL => 'Crítico',
                    ]),
                Tables\Filters\TernaryFilter::make('is_active')->label('Ativo'),
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAnnouncements::route('/'),
            'create' => Pages\CreateAnnouncement::route('/create'),
            'edit' => Pages\EditAnnouncement::route('/{record}/edit'),
        ];
    }
}
