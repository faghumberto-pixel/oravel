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
 * Read-only. A query NUNCA confia no global scope de BelongsToTenant
 * (resolve Auth::user() no guard 'web', não no 'client') -- filtra
 * manualmente tenant_id + client_id do Client autenticado.
 */
class MeusContratos extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationLabel = 'Meus Contratos';

    protected static string $view = 'filament.client.pages.meus-contratos';

    public function table(Table $table): Table
    {
        /** @var Client $client */
        $client = $this->guard()->user();

        return $table
            ->query(
                Contract::withoutGlobalScope('tenant')
                    ->where('tenant_id', $client->tenant_id)
                    ->where('client_id', $client->id)
            )
            ->columns([
                Tables\Columns\TextColumn::make('contract_number')
                    ->label('Contrato'),
                Tables\Columns\TextColumn::make('start_date')
                    ->label('Início')
                    ->date('d/m/Y'),
                Tables\Columns\TextColumn::make('end_date')
                    ->label('Fim')
                    ->date('d/m/Y')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge(),
                Tables\Columns\TextColumn::make('price')
                    ->label('Valor')
                    ->money('BRL'),
            ])
            ->actions([
                Tables\Actions\Action::make('baixarPdf')
                    ->label('Baixar PDF')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->url(fn (Contract $record) => route('cliente.contracts.pdf', ['contract' => $record->id]))
                    ->openUrlInNewTab(),
            ])
            ->defaultSort('start_date', 'desc');
    }

    private function guard(): Guard
    {
        return Auth::guard('client');
    }
}
