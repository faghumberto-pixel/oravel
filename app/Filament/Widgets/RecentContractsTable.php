<?php

namespace App\Filament\Widgets;

use App\Models\Contract;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class RecentContractsTable extends BaseWidget
{
    protected static ?int $sort = 3;
    protected int | string | array $columnSpan = 'full';
    protected static ?string $heading = 'Próximas Devoluções e Contratos Recentes';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Contract::query()
                    ->where('tenant_id', auth()->user()->tenant_id)
                    ->orderBy('end_date', 'asc')
                    ->limit(5)
            )
            ->columns([
                Tables\Columns\TextColumn::make('contract_number')->label('Contrato'),
                Tables\Columns\TextColumn::make('client.name')->label('Cliente'),
                Tables\Columns\TextColumn::make('asset.name')->label('Equipamento'),
                Tables\Columns\TextColumn::make('end_date')->label('Devolução')->date('d/m/Y')->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Ativo' => 'success',
                        'Vencido' => 'danger',
                        default => 'gray',
                    }),
            ]);
    }
}