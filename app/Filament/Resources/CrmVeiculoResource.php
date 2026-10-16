<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CrmVeiculoResource\Pages;
use App\Filament\Resources\CrmVeiculoResource\RelationManagers\UsoRelationManager;
use App\Models\CrmVeiculo;
use App\Models\FleetVehicle;
use App\Models\User;
use App\Support\Tenancy;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Tables;
use Filament\Tables\Table;

/** Veículos dos vendedores (cadastro simples; vínculo opcional com a Frota do ERP). */
class CrmVeiculoResource extends BaseResource
{
    protected static ?string $model = CrmVeiculo::class;

    protected static ?string $navigationIcon = 'heroicon-o-truck';

    protected static ?string $navigationLabel = 'Veículos';

    protected static ?string $navigationGroup = 'Comercial';

    protected static ?string $navigationParentItem = 'Gestão CRM';

    protected static ?int $navigationSort = 12;

    protected static ?string $modelLabel = 'Veículo';

    protected static ?string $pluralModelLabel = 'Veículos';

    protected static ?string $recordTitleAttribute = 'placa';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Veículo')->columns(3)->schema([
                Forms\Components\TextInput::make('placa')->label('Placa')->required()->maxLength(10)
                    ->dehydrateStateUsing(fn (?string $state) => CrmVeiculo::normalizarPlaca((string) $state))
                    ->rules([fn (?CrmVeiculo $record) => function (string $attribute, $value, \Closure $fail) use ($record) {
                        $existe = CrmVeiculo::query()->where('placa', CrmVeiculo::normalizarPlaca((string) $value))
                            ->when($record, fn ($q) => $q->whereKeyNot($record->id))->exists();

                        $existe && $fail('Já existe um veículo com esta placa.');
                    }]),
                Forms\Components\TextInput::make('descricao')->label('Apelido / descrição')->maxLength(255),
                Forms\Components\Select::make('responsavel_user_id')->label('Responsável')->searchable()
                    ->options(fn () => User::where('tenant_id', Tenancy::current()?->id)->orderBy('name')->pluck('name', 'id'))
                    ->helperText('Vazio = veículo de uso comum.'),
                Forms\Components\TextInput::make('marca')->label('Marca')->maxLength(60),
                Forms\Components\TextInput::make('modelo')->label('Modelo')->maxLength(80),
                Forms\Components\TextInput::make('ano')->label('Ano')->numeric()->minValue(1980)->maxValue(2100),
                Forms\Components\TextInput::make('km_atual')->label('Km atual')->numeric()->minValue(0)->default(0)->required(),
                Forms\Components\TextInput::make('proxima_revisao_km')->label('Próxima revisão (km)')->numeric()->minValue(0),
                Forms\Components\Toggle::make('ativo')->label('Ativo')->default(true),
            ]),
            Forms\Components\Section::make('Documentos')->columns(2)->schema([
                Forms\Components\DatePicker::make('vencimento_licenciamento')->label('Vencimento do licenciamento'),
                Forms\Components\DatePicker::make('vencimento_seguro')->label('Vencimento do seguro'),
            ]),
            Forms\Components\Section::make('Observações e vínculo')->collapsed()->schema([
                Forms\Components\Textarea::make('observacoes')->label('Observações')->rows(2),
                Forms\Components\Select::make('fleet_vehicle_id')->label('Veículo da Frota do ERP (opcional)')->searchable()
                    ->options(fn () => FleetVehicle::query()->orderBy('placa')->pluck('placa', 'id'))
                    ->visible(fn () => (bool) Tenancy::current()?->hasFeature('tabela_fleet_vehicles')),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('placa')
            ->columns([
                Tables\Columns\TextColumn::make('placa')->label('Placa')->searchable()->sortable()->description(fn (CrmVeiculo $r) => $r->descricao ?: trim($r->marca.' '.$r->modelo)),
                Tables\Columns\TextColumn::make('responsavel.name')->label('Responsável')->placeholder('Uso comum'),
                Tables\Columns\TextColumn::make('km_atual')->label('Km atual')->numeric(thousandsSeparator: '.')->sortable(),
                Tables\Columns\TextColumn::make('vencimento_licenciamento')->label('Licenciamento')->date('d/m/Y')->placeholder('—')
                    ->color(fn (CrmVeiculo $r) => self::corVencimento($r->vencimento_licenciamento)),
                Tables\Columns\TextColumn::make('vencimento_seguro')->label('Seguro')->date('d/m/Y')->placeholder('—')
                    ->color(fn (CrmVeiculo $r) => self::corVencimento($r->vencimento_seguro)),
                Tables\Columns\TextColumn::make('situacao')->label('Situação')->badge()
                    ->state(fn (CrmVeiculo $r) => match ($r->situacaoVencimentos()) {
                        'vencido' => 'Documento vencido', 'a_vencer' => 'Vence em até 30 dias', default => 'Em dia'
                    })
                    ->color(fn (string $state) => match ($state) {
                        'Documento vencido' => 'danger', 'Vence em até 30 dias' => 'warning', default => 'success'
                    }),
                Tables\Columns\IconColumn::make('ativo')->label('Ativo')->boolean(),
            ])
            ->filters([Tables\Filters\TernaryFilter::make('ativo')->label('Ativo')])
            ->actions([Tables\Actions\EditAction::make()]);
    }

    private static function corVencimento($data): ?string
    {
        if (! $data) {
            return null;
        }

        return $data->isPast() && ! $data->isToday() ? 'danger' : ($data->lte(now()->addDays(30)) ? 'warning' : null);
    }

    public static function getRelations(): array
    {
        return [UsoRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCrmVeiculos::route('/'),
            'create' => Pages\CreateCrmVeiculo::route('/create'),
            'edit' => Pages\EditCrmVeiculo::route('/{record}/edit'),
        ];
    }
}
