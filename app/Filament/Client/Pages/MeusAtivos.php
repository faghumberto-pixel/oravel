<?php

namespace App\Filament\Client\Pages;

use App\Models\Client;
use App\Models\Contract;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Support\Facades\Auth;

/**
 * Read-only. Ativos não têm relação direta com Client -- sempre derivados
 * via contrato ativo (Client -> contracts -> asset), mesmo padrão usado
 * em Asset::activeContract()/Contract::resolvedLocation().
 */
class MeusAtivos extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-cube';

    protected static ?string $navigationLabel = 'Meus Ativos';

    protected static string $view = 'filament.client.pages.meus-ativos';

    public function table(Table $table): Table
    {
        /** @var Client $client */
        $client = $this->guard()->user();

        return $table
            ->query(
                Contract::withoutGlobalScope('tenant')
                    ->where('tenant_id', $client->tenant_id)
                    ->where('client_id', $client->id)
                    ->whereNotNull('asset_id')
            )
            ->columns([
                Tables\Columns\TextColumn::make('asset.name')
                    ->label('Equipamento'),
                Tables\Columns\TextColumn::make('contract_number')
                    ->label('Contrato'),
                Tables\Columns\TextColumn::make('asset.current_horimeter')
                    ->label('Horímetro Atual')
                    ->formatStateUsing(fn ($state) => $state !== null ? number_format($state, 1, ',', '.').' h' : '—'),
                Tables\Columns\TextColumn::make('local')
                    ->label('Localização')
                    ->state(fn (Contract $record) => $record->resolvedLocation()['label'] ?? '—'),
            ])
            ->defaultSort('start_date', 'desc');
    }

    private function guard(): Guard
    {
        return Auth::guard('client');
    }
}
