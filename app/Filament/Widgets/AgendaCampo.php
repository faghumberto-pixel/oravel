<?php

namespace App\Filament\Widgets;

use Filament\Widgets\TableWidget as BaseWidget;
use Filament\Tables;
use Filament\Tables\Table;
use App\Models\MaintenanceOrder;
use Filament\Facades\Filament;

class AgendaCampo extends BaseWidget
{
    protected int | string | array $columnSpan = ['md' => 1];

    // Impede que o widget tente renderizar se não houver um tenant ativo
    public static function canView(): bool
    {
        return (bool) \App\Support\Tenancy::current();
    }

    public function table(Table $table): Table
    {
        // Proteção extra: obtém o ID de forma segura
        $tenantId = \App\Support\Tenancy::current()?->id;

        return $table
            ->query(
                MaintenanceOrder::query()
                    ->where('tenant_id', $tenantId)
                    ->where('status', 'agendado')
                    ->whereBetween('scheduled_at', [now(), now()->addHours(24)])
            )
            ->columns([
                Tables\Columns\TextColumn::make('titulo')
                    ->label('Serviço'),
                Tables\Columns\TextColumn::make('scheduled_at')
                    ->label('Previsão')
                    ->dateTime('d/m H:i'),
                Tables\Columns\TextColumn::make('technician.name')
                    ->label('Técnico'),
            ])
            ->paginated(false);
    }
}