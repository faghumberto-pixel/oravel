<?php

namespace App\Filament\Pages;

use App\Models\EpiDelivery;
use App\Models\EpiSpecification;
use Filament\Pages\Page;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Relatorio de conformidade NR-6 por colaborador: o que cada um tem em
 * posse hoje (status=ativo), se o CA de cada item esta em dia e se a
 * vida util recomendada ja foi ultrapassada -- rastreabilidade em caso
 * de acidente ("quais EPIs este colaborador tinha e desde quando").
 */
class EpiComplianceReport extends Page implements HasTable
{
    use InteractsWithTable;

    protected static bool $shouldRegisterNavigation = true;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static ?string $navigationGroup = 'Ativos e Materiais';

    protected static ?string $navigationParentItem = 'Gestão de EPI';

    protected static ?string $navigationLabel = 'Conformidade NR-6 (EPI)';

    protected static ?string $title = 'Conformidade NR-6 — EPI por Colaborador';

    protected static string $view = 'filament.pages.epi-compliance-report';

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can('viewAny', EpiDelivery::class);
    }

    public function getTableQuery(): Builder
    {
        return EpiDelivery::query()
            ->where('status', EpiDelivery::STATUS_ATIVO)
            ->with(['employee', 'material.epiSpecification']);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getTableQuery())
            ->groups(['employee.name'])
            ->defaultGroup('employee.name')
            ->columns([
                TextColumn::make('employee.name')->label('Colaborador')->searchable(),

                TextColumn::make('material.name')
                    ->label('EPI')
                    ->formatStateUsing(fn (EpiDelivery $record) => $record->material->name.($record->material->epiSpecification?->size_label ? ' — '.$record->material->epiSpecification->size_label : ''))
                    ->searchable(),

                TextColumn::make('material.epiSpecification.ca_number')->label('CA'),

                IconColumn::make('blocked')
                    ->label('CA em Dia')
                    ->boolean()
                    ->trueIcon('heroicon-o-x-circle')
                    ->falseIcon('heroicon-o-check-circle')
                    ->trueColor('danger')
                    ->falseColor('success')
                    ->getStateUsing(fn (EpiDelivery $record) => (bool) $record->blocked),

                TextColumn::make('delivered_at')->label('Entregue em')->date('d/m/Y'),

                TextColumn::make('vida_util')
                    ->label('Vida Útil')
                    ->state(function (EpiDelivery $record) {
                        $lifespan = $record->material->epiSpecification?->estimated_lifespan_days;

                        if (! $lifespan) {
                            return '—';
                        }

                        return "{$record->daysInUse()}/{$lifespan} dias";
                    })
                    ->color(fn (EpiDelivery $record) => $record->isLifespanExceeded() ? 'danger' : null),

                TextColumn::make('ownership_mode')
                    ->label('Posse')
                    ->formatStateUsing(fn (string $state) => EpiSpecification::ownershipModeLabels()[$state] ?? $state)
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('employee_id')
                    ->label('Colaborador')
                    ->relationship('employee', 'name')
                    ->searchable()
                    ->preload(),
            ]);
    }
}
