<?php

namespace App\Filament\Central\Resources;

use App\Filament\Central\Resources\DatabaseBackupResource\Pages;
use App\Models\DatabaseBackup;
use App\Models\Tenant;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * So' leitura -- log dos backups diarios do banco (scripts/backup-prod-database.sh),
 * criado 2026-09-18 apos o incidente de perda de dados de PROD. Um dump
 * cobre todos os tenants de uma vez (nao ha isolamento por tenant a nivel
 * de infra), entao "filtro por tenant" busca dentro do JSON tenant_names
 * (lista de quem existia no momento daquele backup) em vez de uma FK real.
 */
class DatabaseBackupResource extends Resource
{
    protected static ?string $model = DatabaseBackup::class;

    protected static ?string $navigationIcon = 'heroicon-o-circle-stack';

    protected static ?string $navigationGroup = 'Gestão SaaS';

    protected static ?string $navigationLabel = 'Backups do Banco';

    protected static ?string $pluralModelLabel = 'Backups do Banco';

    protected static bool $isScopedToTenant = false;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withoutGlobalScopes();
    }

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Data/Hora')
                    ->dateTime('d/m/Y H:i:s')
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => $state === DatabaseBackup::STATUS_COMPLETED ? 'Concluído' : 'Falhou')
                    ->color(fn (string $state) => $state === DatabaseBackup::STATUS_COMPLETED ? 'success' : 'danger'),

                Tables\Columns\TextColumn::make('filename')
                    ->label('Arquivo')
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('size_bytes')
                    ->label('Tamanho')
                    ->formatStateUsing(fn (int $state) => $state > 0 ? number_format($state / 1048576, 2, ',', '.').' MB' : '—'),

                Tables\Columns\TextColumn::make('tenant_count')
                    ->label('Tenants Incluídos')
                    ->badge()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('path')
                    ->label('Caminho no Servidor')
                    ->limit(50)
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->copyable(),

                Tables\Columns\TextColumn::make('error_message')
                    ->label('Erro')
                    ->limit(60)
                    ->placeholder('—')
                    ->color('danger')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('tenant')
                    ->label('Tenant')
                    ->options(fn () => Tenant::orderBy('name')->pluck('name', 'name'))
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when(
                            $data['value'] ?? null,
                            fn (Builder $q, $tenantName) => $q->whereJsonContains('tenant_names', $tenantName)
                        );
                    }),

                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        DatabaseBackup::STATUS_COMPLETED => 'Concluído',
                        DatabaseBackup::STATUS_FAILED => 'Falhou',
                    ]),

                Tables\Filters\Filter::make('created_at')
                    ->form([
                        DatePicker::make('from')->label('De'),
                        DatePicker::make('until')->label('Até'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'], fn (Builder $q, $date) => $q->whereDate('created_at', '>=', $date))
                            ->when($data['until'], fn (Builder $q, $date) => $q->whereDate('created_at', '<=', $date));
                    }),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDatabaseBackups::route('/'),
        ];
    }
}
