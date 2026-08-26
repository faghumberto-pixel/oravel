<?php

namespace App\Filament\Client\Pages;

use App\Models\Client;
use App\Models\MaintenanceOrder;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Support\Facades\Auth;

/**
 * Read-only. Reaproveita slaColor() e statusHistories() já existentes em
 * MaintenanceOrder -- nenhuma lógica de SLA/timeline nova aqui, só leitura
 * filtrada por tenant_id+client_id do Client autenticado no guard 'client'.
 */
class MinhasOS extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-wrench-screwdriver';

    protected static ?string $navigationLabel = 'Minhas OS';

    protected static string $view = 'filament.client.pages.minhas-os';

    public function table(Table $table): Table
    {
        /** @var Client $client */
        $client = $this->guard()->user();

        return $table
            ->query(
                MaintenanceOrder::withoutGlobalScope('tenant')
                    ->where('tenant_id', $client->tenant_id)
                    ->where('client_id', $client->id)
                    ->with(['statusHistories' => fn ($query) => $query->latest()->limit(1)])
            )
            ->columns([
                Tables\Columns\TextColumn::make('os_number')
                    ->label('OS'),
                Tables\Columns\TextColumn::make('asset.name')
                    ->label('Equipamento')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('maintenance_type')
                    ->label('Tipo')
                    ->badge(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (MaintenanceOrder $record) => $record->slaColor() ?? 'gray'),
                Tables\Columns\TextColumn::make('scheduled_at')
                    ->label('Agendado para')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('lastUpdate')
                    ->label('Última atualização')
                    ->state(function (MaintenanceOrder $record) {
                        $latest = $record->statusHistories->first();

                        return $latest
                            ? "{$latest->old_status} → {$latest->new_status} em {$latest->created_at->format('d/m/Y H:i')}"
                            : 'Sem histórico ainda';
                    }),
            ])
            ->defaultSort('created_at', 'desc');
    }

    private function guard(): Guard
    {
        return Auth::guard('client');
    }
}
