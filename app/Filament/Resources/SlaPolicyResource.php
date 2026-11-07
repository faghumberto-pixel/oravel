<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SlaPolicyResource\Pages;
use App\Models\MaintenanceOrder;
use App\Models\SlaPolicy;
use App\Services\Prioridade\PrioridadeDaOs;
use App\Services\Sla\SlaService;
use App\Support\Tenancy;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Tables;
use Filament\Tables\Table;

/** Políticas de SLA (F2): prazos de Resposta, Atendimento e Resolução por prioridade (e, se quiser, por tipo de OS). */
class SlaPolicyResource extends BaseResource
{
    protected static ?string $model = SlaPolicy::class;

    protected static ?string $modelLabel = 'Política de SLA';

    protected static ?string $pluralModelLabel = 'Políticas de SLA';

    protected static ?string $navigationIcon = 'heroicon-o-clock';

    protected static ?string $navigationGroup = 'Manutenção';

    protected static ?string $navigationParentItem = 'Cadastros';

    protected static ?int $navigationSort = 6;

    /** @return array<string, string> */
    public static function tiposDeOs(): array
    {
        return [
            MaintenanceOrder::TYPE_CORRECTIVE => 'Corretiva',
            MaintenanceOrder::TYPE_PREVENTIVE => 'Preventiva',
            MaintenanceOrder::TYPE_EMERGENCIA => 'Emergência',
            MaintenanceOrder::TYPE_AVARIA => 'Avaria',
            MaintenanceOrder::TYPE_TROCA => 'Troca',
        ];
    }

    public static function form(Form $form): Form
    {
        $minutos = fn (string $campo, string $rotulo, string $ajuda) => Forms\Components\TextInput::make($campo)->label($rotulo)->helperText($ajuda.' Em minutos (240 = 4 h, 1440 = 24 h). Vazio = sem este relógio.')
            ->numeric()->minValue(1)->maxValue(525600)->suffix('min')->requiredWithoutAll(['response_minutes', 'service_minutes', 'resolution_minutes']);

        return $form->schema([
            Forms\Components\Select::make('priority_code')->label('Prioridade')->required()
                ->options(fn () => PrioridadeDaOs::opcoes((string) Tenancy::current()?->id))
                ->rules([fn (Forms\Get $get, ?SlaPolicy $record) => function (string $attr, $value, \Closure $fail) use ($get, $record) {
                    $existe = SlaPolicy::query()->where('priority_code', $value)->where('maintenance_type', $get('maintenance_type') ?: null)
                        ->when($record, fn ($q) => $q->where('id', '!=', $record->id))->exists();
                    if ($existe) {
                        $fail('Já existe uma política para esta prioridade e tipo de OS.');
                    }
                }]),
            Forms\Components\Select::make('maintenance_type')->label('Tipo de OS')->options(static::tiposDeOs())
                ->placeholder('Todos os tipos')->helperText('A política de um tipo específico vale no lugar da geral.'),
            $minutos('response_minutes', 'Resposta', 'Abertura até o primeiro retorno (técnico atribuído, agendamento ou início do serviço).'),
            $minutos('service_minutes', 'Atendimento', 'Abertura até o início do serviço.'),
            $minutos('resolution_minutes', 'Resolução', 'Abertura até a OS concluída.'),
            Forms\Components\Toggle::make('is_active')->label('Ativa')->default(true),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        $fmt = fn ($state) => SlaService::formatarMinutos($state);

        return $table
            ->columns([
                Tables\Columns\TextColumn::make('priority_code')->label('Prioridade')->badge()
                    ->formatStateUsing(fn ($state) => PrioridadeDaOs::opcoes((string) Tenancy::current()?->id)[$state] ?? $state),
                Tables\Columns\TextColumn::make('maintenance_type')->label('Tipo de OS')->placeholder('Todos')->formatStateUsing(fn ($state) => static::tiposDeOs()[$state] ?? $state),
                Tables\Columns\TextColumn::make('response_minutes')->label('Resposta')->formatStateUsing($fmt)->placeholder('—'),
                Tables\Columns\TextColumn::make('service_minutes')->label('Atendimento')->formatStateUsing($fmt)->placeholder('—'),
                Tables\Columns\TextColumn::make('resolution_minutes')->label('Resolução')->formatStateUsing($fmt)->placeholder('—'),
                Tables\Columns\IconColumn::make('is_active')->label('Ativa')->boolean(),
            ])
            ->actions([Tables\Actions\EditAction::make(), Tables\Actions\DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSlaPolicies::route('/'),
            'create' => Pages\CreateSlaPolicy::route('/create'),
            'edit' => Pages\EditSlaPolicy::route('/{record}/edit'),
        ];
    }
}
