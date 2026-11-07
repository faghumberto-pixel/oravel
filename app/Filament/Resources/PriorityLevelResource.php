<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PriorityLevelResource\Pages;
use App\Models\MaintenanceOrder;
use App\Models\PriorityLevel;
use App\Support\Tenancy;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Tables;
use Filament\Tables\Table;

/** Catálogo de prioridades da O.S. do tenant (F1): os 4 níveis do sistema + os que a empresa criar. */
class PriorityLevelResource extends BaseResource
{
    protected static ?string $model = PriorityLevel::class;

    protected static ?string $modelLabel = 'Nível de Prioridade';

    protected static ?string $pluralModelLabel = 'Níveis de Prioridade';

    protected static ?string $navigationIcon = 'heroicon-o-flag';

    protected static ?string $navigationGroup = 'Manutenção';

    protected static ?string $navigationParentItem = 'Cadastros';

    protected static ?int $navigationSort = 5;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('code')->label('Código')
                ->helperText('Identificador curto, só minúsculas, números e _ (ex.: planejada). Não muda depois de criado.')
                ->required()->maxLength(40)->regex('/^[a-z0-9_]+$/')
                ->disabled(fn (?PriorityLevel $record) => $record !== null)->dehydrated(fn (?PriorityLevel $record) => $record === null)
                ->unique(ignoreRecord: true, modifyRuleUsing: fn ($rule) => $rule->where('tenant_id', Tenancy::current()?->id)),
            Forms\Components\TextInput::make('name')->label('Nome')->required()->maxLength(255),
            Forms\Components\Select::make('base_level')->label('Equivale a')
                ->helperText('Nível base usado nos relatórios para comparar prioridades diferentes.')
                ->options(PriorityLevel::BASE_LEVELS)->required(),
            Forms\Components\ColorPicker::make('color')->label('Cor')->required(),
            Forms\Components\TextInput::make('sort_order')->label('Ordem (1 = mais alta)')->numeric()->minValue(1)->default(10)->required(),
            Forms\Components\Toggle::make('is_active')->label('Ativa')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('Nome')->searchable()->badge()->color(fn (PriorityLevel $r) => $r->badgeColor()),
                Tables\Columns\TextColumn::make('code')->label('Código')->color('gray'),
                Tables\Columns\TextColumn::make('base_level')->label('Equivale a')->formatStateUsing(fn ($state) => PriorityLevel::BASE_LEVELS[$state] ?? $state),
                Tables\Columns\ColorColumn::make('color')->label('Cor'),
                Tables\Columns\TextColumn::make('sort_order')->label('Ordem')->sortable(),
                Tables\Columns\IconColumn::make('is_system')->label('Do sistema')->boolean(),
                Tables\Columns\IconColumn::make('is_active')->label('Ativa')->boolean(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->hidden(fn (PriorityLevel $record) => $record->is_system)
                    ->before(function (PriorityLevel $record, Tables\Actions\DeleteAction $action) {
                        if (MaintenanceOrder::where('priority', $record->code)->exists()) {
                            Notification::make()->title('Há O.S. com esta prioridade')->body('Desative a prioridade em vez de excluir.')->warning()->send();
                            $action->cancel();
                        }
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPriorityLevels::route('/'),
            'create' => Pages\CreatePriorityLevel::route('/create'),
            'edit' => Pages\EditPriorityLevel::route('/{record}/edit'),
        ];
    }
}
