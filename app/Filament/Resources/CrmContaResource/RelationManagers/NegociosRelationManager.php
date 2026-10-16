<?php

namespace App\Filament\Resources\CrmContaResource\RelationManagers;

use App\Filament\Resources\CrmLeadResource;
use App\Models\CrmLead;
use App\Support\CrmPalette;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

/** Leads/negócios ligados a esta conta (somente leitura; abrir o lead leva ao funil). */
class NegociosRelationManager extends RelationManager
{
    protected static string $relationship = 'leads';

    protected static ?string $title = 'Leads e negócios';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('Lead')->description(fn (CrmLead $r) => $r->company_name),
                Tables\Columns\TextColumn::make('etapa.nome')->label('Etapa')->badge()
                    ->color(fn (CrmLead $r) => CrmPalette::etapa($r->etapa)['filament']),
                Tables\Columns\TextColumn::make('estimated_value')->label('Valor')->money('BRL')->placeholder('—'),
                Tables\Columns\TextColumn::make('assignedUser.name')->label('Vendedor')->placeholder('—'),
                Tables\Columns\TextColumn::make('created_at')->label('Criado em')->date('d/m/Y'),
            ])
            ->actions([
                Tables\Actions\Action::make('abrir')->label('Abrir')->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn (CrmLead $r) => CrmLeadResource::getUrl('edit', ['record' => $r->id])),
            ]);
    }
}
