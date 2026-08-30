<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MaintenancePlanResource\Pages;
use App\Filament\Resources\MaintenancePlanResource\Support\PlanStatus;
use App\Models\ChecklistGroup;
use App\Models\MaintenancePlan;
use App\Support\Tenancy;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class MaintenancePlanResource extends Resource
{
    // Navegacao manual dentro do submenu "PMP" (AdminPanelProvider), nao
    // auto-registrada -- agrupa tudo que e' preventiva (pedido do usuario
    // 2026-07-26), mesmo padrao de Almoxarifado/Compras.
    protected static ?string $model = MaintenancePlan::class;

    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';

    protected static ?string $navigationGroup = 'PMP';

    protected static ?string $navigationLabel = 'Planos Preventivos';

    protected static ?int $navigationSort = 4;

    // Garante que o resource seja escopado ao tenant atual
    protected static bool $isScopedToTenant = true;

    // Define que o relacionamento de propriedade é 'tenant'
    protected static ?string $tenantOwnershipRelationshipName = 'tenant';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Configuração do Plano')->schema([
                Forms\Components\Placeholder::make('scope_hint')
                    ->label('')
                    ->content('Preencha "Ativo" (regra só para esse equipamento) OU "Grupo de Ativo" (regra vale para todo equipamento do grupo, ex: template de preventiva por horas) — nunca os dois.'),
                Forms\Components\Select::make('asset_id')
                    ->label('Ativo (regra individual)')
                    ->relationship('asset', 'name', fn ($query) => $query->where('tenant_id', Tenancy::current()?->id))
                    ->requiredWithout('checklist_group_id')
                    ->searchable()
                    ->preload(),
                Forms\Components\Select::make('checklist_group_id')
                    ->label('Grupo de Ativo (template do grupo)')
                    ->relationship('checklistGroup', 'name', fn ($query) => $query->where('tenant_id', Tenancy::current()?->id))
                    ->requiredWithout('asset_id')
                    ->searchable()
                    ->preload(),
                Forms\Components\TextInput::make('name')
                    ->label('Nome do Item (Ex: Troca de óleo do motor)')
                    ->required(),
                Forms\Components\TextInput::make('interval_hours')
                    ->label('Intervalo (Horas)')
                    ->numeric()
                    ->requiredWithoutAll(['interval_days', 'interval_battery_cycles'])
                    ->helperText('Preencha ao menos um dos três intervalos -- vence pelo que chegar primeiro.'),
                Forms\Components\TextInput::make('interval_days')
                    ->label('Intervalo (Dias)')
                    ->numeric()
                    ->requiredWithoutAll(['interval_hours', 'interval_battery_cycles']),
                Forms\Components\TextInput::make('interval_battery_cycles')
                    ->label('Intervalo (Ciclos de Bateria)')
                    ->numeric()
                    ->requiredWithoutAll(['interval_hours', 'interval_days'])
                    ->helperText('Relevante pra equipamentos elétricos (empilhadeira, PTA).'),
                Forms\Components\TextInput::make('notes')
                    ->label('Observação (Ex: Obrigatório NR-13)'),
                Forms\Components\Toggle::make('is_critical')
                    ->label('Item crítico')
                    ->helperText('Marca visualmente como prioridade máxima quando vencer.')
                    ->default(false),
                Forms\Components\Toggle::make('is_active')
                    ->label('Plano Ativo')
                    ->default(true),
                Forms\Components\Toggle::make('auto_create_order')
                    ->label('Gerar O.S. automaticamente quando vencer')
                    ->helperText('Sem isso, o sistema só notifica o admin (comportamento padrão). Com isso ligado, uma O.S. Preventiva é criada sozinha assim que o horímetro ultrapassar o intervalo.')
                    ->default(false),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('asset.patrimonio')->label('Patrimônio')->placeholder('—'),
            Tables\Columns\TextColumn::make('asset.name')->label('Ativo')->placeholder('—')->sortable(),
            Tables\Columns\TextColumn::make('checklistGroup.name')->label('Grupo')->placeholder('—')->sortable(),
            Tables\Columns\TextColumn::make('name')->label('Item')->searchable(),
            Tables\Columns\IconColumn::make('is_critical')->boolean()->label('Crítico')->trueColor('danger'),
            Tables\Columns\TextColumn::make('interval_hours')->label('Intervalo (h)')->placeholder('—'),
            Tables\Columns\TextColumn::make('interval_days')->label('Intervalo (dias)')->placeholder('—'),
            Tables\Columns\TextColumn::make('interval_battery_cycles')->label('Intervalo (ciclos)')->placeholder('—')->toggleable(isToggledHiddenByDefault: true),
            Tables\Columns\TextColumn::make('source')
                ->label('Origem')
                ->badge()
                ->formatStateUsing(fn (string $state) => $state === 'template' ? 'Copiado do Grupo' : 'Manual')
                ->color(fn (string $state) => $state === 'template' ? 'info' : 'gray'),
            Tables\Columns\TextColumn::make('notes')->label('Observação')->placeholder('—'),
            Tables\Columns\IconColumn::make('is_active')->boolean()->label('Ativo'),
            Tables\Columns\TextColumn::make('plan_status')
                ->label('Status')
                ->badge()
                ->state(fn (MaintenancePlan $record) => PlanStatus::label(PlanStatus::forPlan($record)))
                ->color(fn (MaintenancePlan $record) => PlanStatus::color(PlanStatus::forPlan($record))),
        ])
            ->filters([
                Tables\Filters\SelectFilter::make('plan_status')
                    ->label('Status do Plano')
                    ->options([
                        'vencido' => 'Vencido',
                        'a_vencer' => 'A Vencer',
                        'dentro_do_prazo' => 'Dentro do Prazo',
                    ])
                    ->query(function (Builder $query, array $data) {
                        if (blank($data['value'] ?? null)) {
                            return $query;
                        }

                        $ids = $query->get()->filter(
                            fn (MaintenancePlan $plan) => PlanStatus::forPlan($plan) === $data['value']
                        )->pluck('id');

                        return $query->whereIn('id', $ids);
                    }),
                Tables\Filters\TernaryFilter::make('is_active')->label('Plano Ativo'),
                Tables\Filters\SelectFilter::make('asset_id')
                    ->label('Ativo')
                    ->relationship('asset', 'name'),
                Tables\Filters\SelectFilter::make('checklist_group_id')
                    ->label('Grupo de Ativo')
                    ->options(fn () => ChecklistGroup::where('tenant_id', Tenancy::current()?->id)->pluck('name', 'id')),
            ])
            ->filtersLayout(FiltersLayout::AboveContent)
            ->filtersFormColumns(4)
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }

    // Injeta o tenant_id antes de salvar para evitar erros de integridade
    public static function mutateFormDataBeforeCreate(array $data): array
    {
        $data['tenant_id'] = Tenancy::current()?->id;

        return $data;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMaintenancePlans::route('/'),
            'create' => Pages\CreateMaintenancePlan::route('/create'),
            'edit' => Pages\EditMaintenancePlan::route('/{record}/edit'),
        ];
    }
}
