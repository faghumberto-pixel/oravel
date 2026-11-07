<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SlaHolidayResource\Pages;
use App\Models\SlaHoliday;
use App\Support\Tenancy;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Tables;
use Filament\Tables\Table;

/** Feriados do calendário de SLA (F5): no horário comercial o relógio não corre nesses dias. */
class SlaHolidayResource extends BaseResource
{
    protected static ?string $model = SlaHoliday::class;

    protected static ?string $modelLabel = 'Feriado do SLA';

    protected static ?string $pluralModelLabel = 'Feriados do SLA';

    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';

    protected static ?string $navigationGroup = 'Manutenção';

    protected static ?string $navigationParentItem = 'Cadastros';

    protected static ?int $navigationSort = 7;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\DatePicker::make('date')->label('Data')->required()->native(false)->displayFormat('d/m/Y')
                ->unique(ignoreRecord: true, modifyRuleUsing: fn ($rule) => $rule->where('tenant_id', Tenancy::current()?->id)),
            Forms\Components\TextInput::make('name')->label('Nome')->required()->maxLength(255)->placeholder('Ex.: Aniversário da cidade'),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('date')
            ->columns([
                Tables\Columns\TextColumn::make('date')->label('Data')->date('d/m/Y')->sortable(),
                Tables\Columns\TextColumn::make('weekday')->label('Dia')->state(fn (SlaHoliday $r) => ucfirst($r->date->locale('pt_BR')->dayName)),
                Tables\Columns\TextColumn::make('name')->label('Nome')->searchable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('ano')->label('Ano')
                    ->options(fn () => collect(range(now()->year - 1, now()->year + 2))->mapWithKeys(fn ($a) => [$a => (string) $a])->all())
                    ->default((string) now()->year)
                    ->query(fn ($query, array $data) => $query->when($data['value'] ?? null, fn ($q, $ano) => $q->whereYear('date', (int) $ano))),
            ])
            ->actions([Tables\Actions\EditAction::make(), Tables\Actions\DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSlaHolidays::route('/'),
            'create' => Pages\CreateSlaHoliday::route('/create'),
            'edit' => Pages\EditSlaHoliday::route('/{record}/edit'),
        ];
    }
}
