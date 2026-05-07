<?php

namespace App\Filament\Resources\MaintenanceOrderResource\RelationManagers;

use App\Models\Material;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class MaterialsRelationManager extends RelationManager
{
    protected static string $relationship = 'materials';
    protected static ?string $title = 'Materiais Utilizados';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('quantity')
                ->label('Quantidade')
                ->numeric()
                ->default(1)
                ->required(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Material'),
                Tables\Columns\TextColumn::make('pivot.quantity')
                    ->label('Qtd'),
                Tables\Columns\TextColumn::make('pivot.cost_at_time')
                    ->label('Custo Unitário')
                    ->money('BRL')
                    ->visible(fn () => Auth::user()->hasAnyRole(['gestor', 'diretor'])),
            ])
            ->headerActions([
                Tables\Actions\AttachAction::make()
                    ->label('Adicionar Material')
                    ->preloadRecordSelect()
                    ->form(fn (Tables\Actions\AttachAction $action): array => [
                        $action->getRecordSelect(),
                        Forms\Components\TextInput::make('quantity')
                            ->label('Quantidade')
                            ->numeric()
                            ->default(1)
                            ->required(),
                    ])
                    ->mutateFormDataUsing(function (array $data): array {
                        // O 'recordId' é o nome padrão do Filament para o ID selecionado no AttachAction
                        $materialId = $data['recordId'] ?? null;
                        
                        $material = Material::find($materialId);
                        
                        // Garante que o custo seja gravado na tabela pivot
                        $data['cost_at_time'] = $material->price ?? 0;
                        
                        return $data;
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DetachAction::make()->label('Remover'),
            ]);
    }
}