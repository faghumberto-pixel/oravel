<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ActivityLogResource\Pages;
use App\Models\ActivityLogEntry;
use App\Models\Asset;
use App\Models\CrmLead;
use App\Models\EquipmentDamage;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Log de alteracoes (Spatie activity_log, ja gravado de verdade por
 * Asset/EquipmentDamage/CrmLead via LogsActivity::logOnlyDirty()) como
 * comprovacao de auditoria -- so leitura, so admin do tenant
 * (ActivityLogEntryPolicy). Ate agora essa tabela era escrita mas nunca
 * exibida em lugar nenhum do painel.
 */
class ActivityLogResource extends Resource
{
    protected static ?string $model = ActivityLogEntry::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationGroup = 'Relatórios';

    protected static ?string $navigationLabel = 'Log de Alterações';

    protected static ?string $modelLabel = 'Alteração';

    protected static ?string $pluralModelLabel = 'Log de Alterações';

    protected static ?int $navigationSort = 22;

    private const SUBJECT_TYPES = [
        Asset::class => 'Ativo',
        EquipmentDamage::class => 'Avaria',
        CrmLead::class => 'Lead (CRM)',
    ];

    private const EVENTS = [
        'created' => 'Criado',
        'updated' => 'Editado',
        'deleted' => 'Excluído',
    ];

    public static function canViewAny(): bool
    {
        return (bool) auth()->user()?->can('viewAny', ActivityLogEntry::class);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->whereIn('subject_type', array_keys(self::SUBJECT_TYPES));

        // whereHasMorph faria o join comparando activity_log.subject_id
        // (varchar) direto com o id (uuid nativo) de assets/equipment_damages/
        // crm_leads -- Postgres nao faz cast implicito nessa comparacao
        // ("operator does not exist: character varying = uuid"), quebra em
        // runtime. Em vez disso, busca os ids (como string) de cada model --
        // isso ja aplica o global scope de BelongsToTenant de cada um deles
        // (bypass de super admin incluso), entao o filtro de tenant continua
        // "de graca", so sem o join direto que o Postgres rejeita.
        return $query->where(function (Builder $q) {
            foreach (array_keys(self::SUBJECT_TYPES) as $class) {
                $ids = $class::query()->pluck('id')->map(fn ($id) => (string) $id);
                $q->orWhere(fn (Builder $qq) => $qq->where('subject_type', $class)->whereIn('subject_id', $ids));
            }
        });
    }

    private static function subjectLabel(?string $type): string
    {
        return self::SUBJECT_TYPES[$type] ?? Str::afterLast((string) $type, '\\');
    }

    /**
     * A tabela so mostrava um rotulo generico do tipo ("Ativo"/"Avaria"),
     * nunca QUAL ativo -- resolve o patrimonio do Asset por tras do registro
     * (subject direto quando subject_type = Asset, ou via asset_id quando
     * subject_type = EquipmentDamage). CrmLead nao tem ativo, retorna null.
     */
    private static function resolveAssetPatrimonio(ActivityLogEntry $record): ?string
    {
        return match ($record->subject_type) {
            Asset::class => Asset::find($record->subject_id)?->patrimonio,
            EquipmentDamage::class => EquipmentDamage::find($record->subject_id)?->asset?->patrimonio,
            default => null,
        };
    }

    private static function formatChanges(ActivityLogEntry $record): string
    {
        $properties = $record->properties ?? collect();
        $attributes = collect($properties->get('attributes', []));
        $old = collect($properties->get('old', []));

        if ($record->event === 'updated' && $old->isNotEmpty()) {
            return $attributes
                ->map(fn ($value, $key) => "{$key}: ".self::formatValue($old->get($key)).' → '.self::formatValue($value))
                ->implode('; ');
        }

        if ($record->event === 'deleted') {
            return $old->map(fn ($value, $key) => "{$key}: ".self::formatValue($value))->implode('; ');
        }

        return $attributes->map(fn ($value, $key) => "{$key}: ".self::formatValue($value))->implode('; ');
    }

    private static function formatValue(mixed $value): string
    {
        if (is_null($value)) {
            return '—';
        }

        if (is_bool($value)) {
            return $value ? 'Sim' : 'Não';
        }

        return (string) $value;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('subject_type')
                ->label('Modelo')
                ->formatStateUsing(fn (?string $state) => self::subjectLabel($state))
                ->disabled(),
            Forms\Components\TextInput::make('patrimonio')
                ->label('Patrimônio')
                ->formatStateUsing(fn (ActivityLogEntry $record) => self::resolveAssetPatrimonio($record) ?? '—')
                ->disabled(),
            Forms\Components\TextInput::make('event')
                ->label('Evento')
                ->formatStateUsing(fn (?string $state) => self::EVENTS[$state] ?? $state)
                ->disabled(),
            Forms\Components\TextInput::make('causer.name')->label('Quem Fez')->disabled(),
            Forms\Components\Textarea::make('mudancas')
                ->label('O Que Mudou')
                ->formatStateUsing(fn (ActivityLogEntry $record) => self::formatChanges($record))
                ->disabled(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Data/Hora')
                    ->dateTime('d/m/Y H:i:s')
                    ->sortable(),

                Tables\Columns\TextColumn::make('subject_type')
                    ->label('Modelo')
                    ->formatStateUsing(fn (?string $state) => self::subjectLabel($state))
                    ->badge()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('patrimonio')
                    ->label('Patrimônio')
                    ->state(fn (ActivityLogEntry $record) => self::resolveAssetPatrimonio($record))
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('event')
                    ->label('Evento')
                    ->badge()
                    ->formatStateUsing(fn (?string $state) => self::EVENTS[$state] ?? $state)
                    ->color(fn (?string $state) => match ($state) {
                        'created' => 'success',
                        'updated' => 'warning',
                        'deleted' => 'danger',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('causer.name')
                    ->label('Quem Fez')
                    ->searchable()
                    ->placeholder('Sistema'),

                Tables\Columns\TextColumn::make('mudancas')
                    ->label('O Que Mudou')
                    ->state(fn (ActivityLogEntry $record) => self::formatChanges($record))
                    ->limit(60)
                    ->wrap(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('subject_type')
                    ->label('Modelo')
                    ->options(self::SUBJECT_TYPES),

                Tables\Filters\SelectFilter::make('event')
                    ->label('Evento')
                    ->options(self::EVENTS),

                Tables\Filters\Filter::make('periodo')
                    ->label('Intervalo de Data')
                    ->form([
                        Forms\Components\DatePicker::make('de')->label('De'),
                        Forms\Components\DatePicker::make('ate')->label('Até'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['de'] ?? null, fn (Builder $q, $date) => $q->whereDate('created_at', '>=', $date))
                            ->when($data['ate'] ?? null, fn (Builder $q, $date) => $q->whereDate('created_at', '<=', $date));
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['de'] ?? null) {
                            $indicators[] = 'De: '.Carbon::parse($data['de'])->format('d/m/Y');
                        }
                        if ($data['ate'] ?? null) {
                            $indicators[] = 'Até: '.Carbon::parse($data['ate'])->format('d/m/Y');
                        }

                        return $indicators;
                    }),
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([
                Tables\Actions\ViewAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListActivityLogEntries::route('/'),
        ];
    }
}
