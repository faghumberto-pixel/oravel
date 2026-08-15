<?php

namespace App\Filament\Resources;

use App\Filament\Resources\FleetDriverResource\Pages;
use App\Filament\Resources\FleetDriverResource\RelationManagers;
use App\Models\FleetDriver;
use App\Support\Tenancy;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class FleetDriverResource extends Resource
{
    protected static ?string $model = FleetDriver::class;

    protected static ?string $navigationIcon = 'heroicon-o-identification';

    protected static ?string $navigationGroup = 'Logística';

    protected static ?string $navigationLabel = 'Motoristas';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Dados do Motorista')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->label('Nome')
                        ->required(),
                    Forms\Components\TextInput::make('cpf')
                        ->label('CPF')
                        ->maxLength(20),
                    Forms\Components\TextInput::make('phone')
                        ->label('Telefone')
                        ->tel(),
                    Forms\Components\Select::make('employment_type')
                        ->label('Vínculo')
                        ->options([
                            FleetDriver::EMPLOYMENT_PROPRIO => 'Funcionário Próprio',
                            FleetDriver::EMPLOYMENT_TERCEIRO => 'Terceiro (Transportadora)',
                        ])
                        ->default(FleetDriver::EMPLOYMENT_PROPRIO)
                        ->required()
                        ->live()
                        ->native(false),
                    Forms\Components\Select::make('freight_carrier_id')
                        ->label('Transportadora')
                        ->relationship('freightCarrier', 'nome')
                        ->searchable()
                        ->preload()
                        ->visible(fn (Forms\Get $get) => $get('employment_type') === FleetDriver::EMPLOYMENT_TERCEIRO)
                        ->required(fn (Forms\Get $get) => $get('employment_type') === FleetDriver::EMPLOYMENT_TERCEIRO),
                    Forms\Components\Select::make('employee_id')
                        ->label('Colaborador (Departamento Pessoal)')
                        ->helperText('Motorista próprio precisa ter um Colaborador vinculado -- é o que dá lastro pra bater ponto e registrar deslocamento pelo app.')
                        ->relationship(
                            name: 'employee',
                            titleAttribute: 'name',
                            modifyQueryUsing: function (Builder $query) {
                                $tenant = Tenancy::current();

                                return $query->when($tenant, fn ($q) => $q->where('tenant_id', $tenant->id));
                            },
                        )
                        ->searchable()
                        ->preload()
                        ->visible(fn (Forms\Get $get) => $get('employment_type') === FleetDriver::EMPLOYMENT_PROPRIO)
                        ->required(fn (Forms\Get $get) => $get('employment_type') === FleetDriver::EMPLOYMENT_PROPRIO),
                    Forms\Components\Toggle::make('active')
                        ->label('Ativo')
                        ->default(true)
                        ->inline(false),
                ]),

            Forms\Components\Section::make('CNH')
                ->columns(3)
                ->schema([
                    Forms\Components\TextInput::make('cnh_number')
                        ->label('Número da CNH'),
                    Forms\Components\Select::make('cnh_category')
                        ->label('Categoria')
                        ->options(array_combine(
                            ['A', 'B', 'AB', 'C', 'D', 'E', 'AC', 'AD', 'AE'],
                            ['A', 'B', 'AB', 'C', 'D', 'E', 'AC', 'AD', 'AE'],
                        ))
                        ->native(false),
                    Forms\Components\DatePicker::make('cnh_expiry_date')
                        ->label('Validade da CNH'),
                ]),

            Forms\Components\Section::make('Veículos Habilitados')
                ->description('Quais veículos da frota este motorista está habilitado a dirigir.')
                ->schema([
                    Forms\Components\Select::make('vehicles')
                        ->label('Veículos')
                        ->relationship('vehicles', 'placa')
                        ->multiple()
                        ->searchable()
                        ->preload(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('Nome')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('cpf')->label('CPF')->searchable(),
                Tables\Columns\TextColumn::make('phone')->label('Telefone'),
                Tables\Columns\TextColumn::make('employee.name')
                    ->label('Colaborador')
                    ->placeholder('Sem vínculo')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('employment_type')
                    ->label('Vínculo')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        FleetDriver::EMPLOYMENT_TERCEIRO => 'Terceiro',
                        default => 'Próprio',
                    })
                    ->color(fn (string $state) => $state === FleetDriver::EMPLOYMENT_TERCEIRO ? 'gray' : 'success'),
                Tables\Columns\TextColumn::make('cnh_category')->label('Cat. CNH'),
                Tables\Columns\TextColumn::make('cnh_expiry_date')
                    ->label('Validade CNH')
                    ->date('d/m/Y')
                    ->badge()
                    ->color(fn (FleetDriver $record) => match (true) {
                        $record->isCnhVencida() => 'danger',
                        $record->isCnhProximoVencimento() => 'warning',
                        default => 'success',
                    }),
                Tables\Columns\IconColumn::make('active')->label('Ativo')->boolean(),
                Tables\Columns\TextColumn::make('alertas')
                    ->label('Alertas')
                    ->state(function (FleetDriver $record) {
                        $docsVencidos = $record->documents()->get()->filter(fn ($d) => $d->isVencido())->count();
                        $docsProximos = $record->documents()->get()->filter(fn ($d) => $d->isProximoVencimento())->count();

                        $partes = [];
                        if ($record->isCnhVencida()) {
                            $partes[] = 'CNH vencida';
                        } elseif ($record->isCnhProximoVencimento()) {
                            $partes[] = 'CNH vencendo';
                        }
                        if ($docsVencidos) {
                            $partes[] = "{$docsVencidos} doc. vencido(s)";
                        }
                        if ($docsProximos) {
                            $partes[] = "{$docsProximos} doc. vencendo";
                        }

                        return $partes ? implode(' · ', $partes) : 'Em dia';
                    })
                    ->badge()
                    ->color(function (FleetDriver $record) {
                        $docsVencidos = $record->documents()->get()->filter(fn ($d) => $d->isVencido())->count();
                        if ($record->isCnhVencida() || $docsVencidos > 0) {
                            return 'danger';
                        }

                        $docsProximos = $record->documents()->get()->filter(fn ($d) => $d->isProximoVencimento())->count();

                        return ($record->isCnhProximoVencimento() || $docsProximos > 0) ? 'warning' : 'success';
                    }),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('employment_type')
                    ->label('Vínculo')
                    ->options([
                        FleetDriver::EMPLOYMENT_PROPRIO => 'Próprio',
                        FleetDriver::EMPLOYMENT_TERCEIRO => 'Terceiro',
                    ]),
                Tables\Filters\TernaryFilter::make('active')->label('Ativo'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\DocumentsRelationManager::class,
            RelationManagers\MovementsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListFleetDrivers::route('/'),
            'create' => Pages\CreateFleetDriver::route('/create'),
            'edit' => Pages\EditFleetDriver::route('/{record}/edit'),
        ];
    }
}
