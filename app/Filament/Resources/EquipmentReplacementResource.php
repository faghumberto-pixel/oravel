<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EquipmentReplacementResource\Pages;
use App\Models\Asset;
use App\Models\EquipmentReplacement;
use App\Support\Tenancy;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Tela sobre o workflow que ja existia inteiro em App\Models\EquipmentReplacement
 * (identifyReplacement/startLogisticsMovements/syncStatusFromMovements/completeSwap,
 * ja testado via seeder/tinker) -- ate 2026-07-17 nao tinha Resource nenhum,
 * so um historico somente-leitura em Asset/Contract. Sem Create solto: troca
 * sempre nasce de um gatilho (OS com Tipo de Operacao "Troca de Equipamento",
 * ver CreatesReplacementFromOsType; vinculo de substituto numa Avaria, ver
 * ViewEquipmentDamage; ou o botao "Nova Requisição" aqui pra caso comercial
 * direto sem OS/Avaria previa).
 */
class EquipmentReplacementResource extends Resource
{
    protected static ?string $model = EquipmentReplacement::class;

    protected static ?string $modelLabel = 'Troca de Equipamento';

    protected static ?string $pluralModelLabel = 'Trocas de Equipamento';

    protected static ?string $navigationIcon = 'heroicon-o-arrow-path';

    /**
     * Prazo por urgencia (horas ate' o substituto precisar estar
     * identificado) -- so' usado pro indicador visual de SLA na tabela,
     * nao bloqueia nada. Sem coluna nova: computado a partir de
     * created_at + urgency.
     */
    private const SLA_HOURS = [
        EquipmentReplacement::URGENCY_CRITICO => 2,
        EquipmentReplacement::URGENCY_URGENTE => 8,
        EquipmentReplacement::URGENCY_NORMAL => 48,
    ];

    public static function slaColor(EquipmentReplacement $record): string
    {
        if (in_array($record->status, [EquipmentReplacement::STATUS_CONCLUIDO, EquipmentReplacement::STATUS_CANCELADO], true)) {
            return 'gray';
        }

        $limiteHoras = self::SLA_HOURS[$record->urgency] ?? self::SLA_HOURS[EquipmentReplacement::URGENCY_NORMAL];
        $horasDecorridas = $record->created_at->diffInHours(now());

        return match (true) {
            $horasDecorridas >= $limiteHoras => 'danger',
            $horasDecorridas >= $limiteHoras * 0.7 => 'warning',
            default => 'success',
        };
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('original_asset_id')
                ->label('Ativo Original')
                ->relationship('originalAsset', 'name', fn (Builder $query) => $query->where('tenant_id', Tenancy::current()?->id))
                ->getOptionLabelFromRecordUsing(fn (Asset $record) => "{$record->patrimonio} — {$record->name}")
                ->searchable(['name', 'patrimonio'])
                ->preload()
                ->required(),
            Forms\Components\Select::make('urgency')
                ->label('Urgência')
                ->options([
                    EquipmentReplacement::URGENCY_NORMAL => 'Normal',
                    EquipmentReplacement::URGENCY_URGENTE => 'Urgente',
                    EquipmentReplacement::URGENCY_CRITICO => 'Crítico',
                ])
                ->default(EquipmentReplacement::URGENCY_NORMAL)
                ->required()
                ->native(false),
            Forms\Components\Textarea::make('reason')
                ->label('Motivo')
                ->required()
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('originalAsset.patrimonio')
                    ->label('Patrimônio')
                    ->searchable(),
                Tables\Columns\TextColumn::make('originalAsset.name')
                    ->label('Ativo Original')
                    ->searchable(),
                Tables\Columns\TextColumn::make('replacementAsset.name')
                    ->label('Substituto')
                    ->placeholder('— não identificado —'),
                Tables\Columns\BadgeColumn::make('urgency')
                    ->label('Urgência')
                    ->colors([
                        'gray' => EquipmentReplacement::URGENCY_NORMAL,
                        'warning' => EquipmentReplacement::URGENCY_URGENTE,
                        'danger' => EquipmentReplacement::URGENCY_CRITICO,
                    ])
                    ->formatStateUsing(fn (string $state) => ucfirst($state)),
                Tables\Columns\BadgeColumn::make('status')
                    ->label('Status')
                    ->colors([
                        'gray' => EquipmentReplacement::STATUS_SOLICITADO,
                        'info' => EquipmentReplacement::STATUS_SUBSTITUTO_IDENTIFICADO,
                        'warning' => [EquipmentReplacement::STATUS_DESMOBILIZACAO_ANDAMENTO, EquipmentReplacement::STATUS_MOBILIZACAO_ANDAMENTO],
                        'success' => EquipmentReplacement::STATUS_CONCLUIDO,
                        'danger' => EquipmentReplacement::STATUS_CANCELADO,
                    ])
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        EquipmentReplacement::STATUS_SOLICITADO => 'Solicitado',
                        EquipmentReplacement::STATUS_SUBSTITUTO_IDENTIFICADO => 'Substituto Identificado',
                        EquipmentReplacement::STATUS_DESMOBILIZACAO_ANDAMENTO => 'Desmobilização em Andamento',
                        EquipmentReplacement::STATUS_MOBILIZACAO_ANDAMENTO => 'Mobilização em Andamento',
                        EquipmentReplacement::STATUS_CONCLUIDO => 'Concluído',
                        EquipmentReplacement::STATUS_CANCELADO => 'Cancelado',
                        default => $state,
                    }),
                Tables\Columns\TextColumn::make('requestedBy.name')
                    ->label('Solicitante'),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('SLA')
                    ->badge()
                    ->color(fn (EquipmentReplacement $record) => self::slaColor($record))
                    ->formatStateUsing(fn (EquipmentReplacement $record) => match (self::slaColor($record)) {
                        'danger' => 'Vencido',
                        'warning' => 'Próximo do prazo',
                        'success' => 'No prazo',
                        default => '—',
                    })
                    ->since(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        EquipmentReplacement::STATUS_SOLICITADO => 'Solicitado',
                        EquipmentReplacement::STATUS_SUBSTITUTO_IDENTIFICADO => 'Substituto Identificado',
                        EquipmentReplacement::STATUS_DESMOBILIZACAO_ANDAMENTO => 'Desmobilização em Andamento',
                        EquipmentReplacement::STATUS_MOBILIZACAO_ANDAMENTO => 'Mobilização em Andamento',
                        EquipmentReplacement::STATUS_CONCLUIDO => 'Concluído',
                        EquipmentReplacement::STATUS_CANCELADO => 'Cancelado',
                    ]),
                Tables\Filters\SelectFilter::make('urgency')
                    ->label('Urgência')
                    ->options([
                        EquipmentReplacement::URGENCY_NORMAL => 'Normal',
                        EquipmentReplacement::URGENCY_URGENTE => 'Urgente',
                        EquipmentReplacement::URGENCY_CRITICO => 'Crítico',
                    ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([
                Tables\Actions\ViewAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEquipmentReplacements::route('/'),
            'view' => Pages\ViewEquipmentReplacement::route('/{record}'),
        ];
    }
}
