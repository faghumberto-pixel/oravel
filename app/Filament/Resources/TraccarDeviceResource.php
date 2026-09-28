<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TraccarDeviceResource\Pages;
use App\Models\TraccarDevice;
use App\Support\Tenancy;
use App\Support\TraccarService;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
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

    protected static ?int $navigationSort = 5;

    protected static ?string $modelLabel = 'Device GPS';

    protected static ?string $pluralModelLabel = 'Devices GPS';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Section::make('Como funciona')
                ->description(
                    "1) O colaborador instala o app \"Traccar Client\" no celular (Android/iOS) e configura o servidor da Oravel, escolhendo um Identificador único (ex: nome ou matrícula).\n".
                    "2) Assim que o app manda a primeira posição, o Traccar cria o device sozinho -- antes disso, a busca abaixo não encontra nada.\n".
                    '3) Preencha o Identificador (o mesmo texto configurado no app) e clique em "Buscar no Traccar" pra preencher o ID automaticamente -- não precisa saber esse número de cor.'
                )
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
                        ->required()
                        ->columnSpanFull(),

                    TextInput::make('identifier')
                        ->label('Identificador')
                        ->helperText('IMEI/identificador do rastreador, o mesmo configurado no app Traccar Client.')
                        ->required()
                        ->maxLength(255),

                    TextInput::make('traccar_device_id')
                        ->label('ID do device no Traccar')
                        ->helperText('Preenchido automaticamente ao clicar em "Buscar no Traccar" -- só digite na mão se já souber o número.')
                        ->numeric()
                        ->required()
                        ->suffixAction(
                            Action::make('buscarNoTraccar')
                                ->label('Buscar no Traccar')
                                ->icon('heroicon-o-magnifying-glass')
                                ->action(function (Get $get, Set $set) {
                                    $identifier = $get('identifier');

                                    if (blank($identifier)) {
                                        Notification::make()
                                            ->title('Preencha o Identificador antes de buscar.')
                                            ->warning()
                                            ->send();

                                        return;
                                    }

                                    $device = app(TraccarService::class)->findDeviceByIdentifier($identifier);

                                    if (! $device) {
                                        Notification::make()
                                            ->title('Device não encontrado no Traccar.')
                                            ->body('Confirme se o app já mandou pelo menos uma posição com esse identificador.')
                                            ->warning()
                                            ->send();

                                        return;
                                    }

                                    $set('traccar_device_id', $device['id']);

                                    Notification::make()
                                        ->title('Encontrado! ID '.$device['id'].' preenchido.')
                                        ->success()
                                        ->send();
                                }),
                        ),
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
