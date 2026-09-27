<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TraccarDeviceResource\Pages;
use App\Models\TraccarDevice;
use App\Support\Tenancy;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TraccarDeviceResource extends BaseResource
{
    protected static ?string $model = TraccarDevice::class;

    protected static ?string $navigationIcon = 'heroicon-o-map-pin';

    protected static ?string $navigationGroup = 'Logística';

    protected static ?string $navigationParentItem = 'Frota';

    // Label distinto do da pagina do mapa (App\Filament\Pages\RastreamentoGps)
    // pra nao colidir no menu -- este e' so o CRUD de vinculo device<->usuario.
    protected static ?string $navigationLabel = 'Vínculos de Rastreamento';

    protected static ?string $modelLabel = 'Device GPS';

    protected static ?string $pluralModelLabel = 'Devices GPS';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Section::make()
                ->schema([
                    // User, não Employee/BelongsToTenant -- precisa filtrar
                    // manualmente pelo tenant atual (User não tem a trait).
                    Select::make('user_id')
                        ->label('Vendedor/Técnico')
                        ->relationship(
                            name: 'user',
                            titleAttribute: 'name',
                            modifyQueryUsing: function (Builder $query) {
                                $tenant = Tenancy::current();

                                return $query->when($tenant, fn (Builder $q) => $q->where('tenant_id', $tenant->id));
                            },
                        )
                        ->searchable()
                        ->preload()
                        ->required(),

                    TextInput::make('traccar_device_id')
                        ->label('ID do device no Traccar')
                        ->helperText('ID numérico do device cadastrado no servidor Traccar.')
                        ->numeric()
                        ->required(),

                    TextInput::make('identifier')
                        ->label('Identificador')
                        ->helperText('IMEI/identificador do rastreador, como aparece no Traccar.')
                        ->required()
                        ->maxLength(255),
                ])
                ->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user.name')->label('Vendedor/Técnico')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('identifier')->label('Identificador')->searchable(),
                Tables\Columns\TextColumn::make('traccar_device_id')->label('ID Traccar'),
                Tables\Columns\TextColumn::make('created_at')->label('Vinculado em')->dateTime('d/m/Y H:i')->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([Tables\Actions\DeleteBulkAction::make()]),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageTraccarDevices::route('/')];
    }
}
