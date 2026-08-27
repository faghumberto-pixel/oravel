<?php

namespace App\Filament\Central\Resources;

use App\Filament\Central\Resources\PmpEquipmentFamilyResource\Pages;
use App\Filament\Central\Resources\PmpEquipmentFamilyResource\RelationManagers\ChecklistItemsRelationManager;
use App\Filament\Central\Resources\PmpEquipmentFamilyResource\RelationManagers\TemplateItemsRelationManager;
use App\Models\PmpEquipmentFamily;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Catálogo global de templates de PMP por família de equipamento (sem
 * tenant_id, mesmo padrão de PlanResource) -- gerenciado só pelo super
 * admin aqui. Cada tenant importa os itens pra dentro de um
 * ChecklistGroup próprio via MaintenancePlan::importFromFamilyTemplate()
 * (ação no painel admin, MaintenancePlanResource).
 */
class PmpEquipmentFamilyResource extends Resource
{
    protected static ?string $model = PmpEquipmentFamily::class;

    protected static ?string $navigationIcon = 'heroicon-o-wrench-screwdriver';

    protected static ?string $navigationGroup = 'Gestão SaaS';

    protected static ?string $navigationLabel = 'Catálogo de PMP';

    protected static ?string $pluralModelLabel = 'Famílias de Equipamento (PMP)';

    protected static bool $isScopedToTenant = false;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withoutGlobalScopes();
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('segment')
                ->label('Segmento')
                ->helperText('Slug do segmento de equipamento, ex: empilhadeiras, geradores, guindastes.')
                ->required()
                ->maxLength(255),

            Forms\Components\TextInput::make('name')
                ->label('Nome da Família')
                ->required()
                ->maxLength(255),

            Forms\Components\Textarea::make('description')
                ->label('Descrição')
                ->rows(3)
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('segment')->label('Segmento')->badge(),
                Tables\Columns\TextColumn::make('name')->label('Família')->searchable(),
                Tables\Columns\TextColumn::make('description')->label('Descrição')->limit(60),
                Tables\Columns\TextColumn::make('template_items_count')
                    ->label('Itens')
                    ->counts('templateItems'),
                Tables\Columns\TextColumn::make('checklist_items_count')
                    ->label('Checklist')
                    ->counts('checklistItems'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('segment')
                    ->options(fn () => PmpEquipmentFamily::query()->distinct()->pluck('segment', 'segment')),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            TemplateItemsRelationManager::class,
            ChecklistItemsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPmpEquipmentFamilies::route('/'),
            'create' => Pages\CreatePmpEquipmentFamily::route('/create'),
            'edit' => Pages\EditPmpEquipmentFamily::route('/{record}/edit'),
        ];
    }
}
