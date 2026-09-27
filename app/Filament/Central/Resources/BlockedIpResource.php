<?php

namespace App\Filament\Central\Resources;

use App\Filament\Central\Resources\BlockedIpResource\Pages;
use App\Models\BlockedIp;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;

/**
 * Lista/gerencia os IPs bloqueados (2026-09-27, pedido do usuário --
 * "bloquear IP" em Acessos e Visitantes). Existe separado pra dar um jeito
 * de DESBLOQUEAR (a tela de Acessos só bloqueia, sem essa aqui um bloqueio
 * seria permanente sem chance de desfazer). Enforcement real em
 * App\Http\Middleware\BlockBannedIps.
 */
class BlockedIpResource extends Resource
{
    protected static ?string $model = BlockedIp::class;

    protected static ?string $navigationIcon = 'heroicon-o-no-symbol';

    protected static ?string $navigationGroup = 'Gestão SaaS';

    protected static ?string $navigationLabel = 'IPs Bloqueados';

    protected static ?string $pluralModelLabel = 'IPs Bloqueados';

    protected static bool $isScopedToTenant = false;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withoutGlobalScopes();
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('ip_address')
                ->label('Endereço IP')
                ->required()
                ->ip()
                ->unique(ignoreRecord: true),

            Forms\Components\TextInput::make('reason')
                ->label('Motivo')
                ->maxLength(255),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('ip_address')
                    ->label('IP')
                    ->searchable()
                    ->copyable(),

                Tables\Columns\TextColumn::make('reason')
                    ->label('Motivo')
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('blockedBy.name')
                    ->label('Bloqueado por')
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Bloqueado em')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->label('Desbloquear')
                    ->icon('heroicon-o-lock-open')
                    ->after(fn () => Cache::forget('blocked-ips-list')),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->after(fn () => Cache::forget('blocked-ips-list')),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBlockedIps::route('/'),
        ];
    }
}
