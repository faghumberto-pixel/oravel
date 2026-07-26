<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasSuperAdminTenantColumn;
use App\Filament\Resources\AbcMatrixResource\Pages;
use App\Models\AbcMatrix;
use App\Models\CriticalityLevel;
use App\Support\Tenancy;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class AbcMatrixResource extends Resource
{
    use HasSuperAdminTenantColumn;

    protected static ?string $model = AbcMatrix::class;

    protected static ?string $modelLabel = 'Matriz ABC';

    protected static ?string $pluralModelLabel = 'Matrizes ABC';

    protected static ?string $navigationIcon = 'heroicon-o-squares-2x2';

    protected static ?string $navigationGroup = 'PCM';

    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('asset_id')
                    ->label('Ativo')
                    ->relationship('asset', 'name', fn ($query) => $query->where('tenant_id', Tenancy::current()?->id))
                    ->searchable()
                    ->preload()
                    ->required(),
                Forms\Components\Select::make('nivel')
                    ->label('Nível')
                    ->options(fn () => CriticalityLevel::where('tenant_id', Tenancy::current()?->id)
                        ->orderBy('sort_order')
                        ->pluck('name', 'code'))
                    ->helperText('Cadastrado em Manutenção → Níveis de Criticidade.')
                    ->required(),
                Forms\Components\TextInput::make('descricao')
                    ->label('Descrição')
                    ->required()
                    ->maxLength(255)
                    ->placeholder('Baixa, Media, Critica'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                static::tenantColumn(),
                Tables\Columns\TextColumn::make('asset.name')
                    ->label('Ativo')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('asset.patrimonio')
                    ->label('Patrimônio')
                    ->searchable(),
                Tables\Columns\TextColumn::make('nivel')
                    ->label('Nível')
                    ->badge()
                    ->color(fn (string $state): string => CriticalityLevel::where('tenant_id', Tenancy::current()?->id)
                        ->where('code', $state)
                        ->value('is_urgent') ? 'danger' : 'gray')
                    ->sortable(),
                Tables\Columns\TextColumn::make('descricao')
                    ->label('Descrição')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Criado em')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAbcMatrices::route('/'),
            'create' => Pages\CreateAbcMatrix::route('/create'),
            'edit' => Pages\EditAbcMatrix::route('/{record}/edit'),
        ];
    }
}
