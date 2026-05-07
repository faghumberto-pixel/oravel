<?php

namespace App\Filament\Resources\AssetResource\Pages;

use App\Filament\Resources\AssetResource;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;

class ViewAsset extends ViewRecord
{
    protected static string $resource = AssetResource::class;

    // Esta função define o layout de visualização do ativo
    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                // Aba de Identificação
                Infolists\Components\Section::make('Dados do Ativo')
                    ->schema([
                        Infolists\Components\TextEntry::make('name')->label('Descrição'),
                        Infolists\Components\TextEntry::make('tag')->label('TAG/Código'),
                        Infolists\Components\TextEntry::make('patrimonio')->label('Nº Patrimônio'),
                        Infolists\Components\TextEntry::make('status')->badge(),
                    ])->columns(2),

                // Aba de Histórico de Ordens de Serviço (Vinculado ao Ativo)
                Infolists\Components\Section::make('Histórico de Manutenções')
                    ->schema([
                        Infolists\Components\RepeatableEntry::make('maintenanceOrders')
                            ->label('Ordens de Serviço Relacionadas')
                            ->schema([
                                Infolists\Components\TextEntry::make('description')->label('Problema'),
                                Infolists\Components\TextEntry::make('status')->label('Status')->badge(),
                                Infolists\Components\TextEntry::make('created_at')->label('Data Abertura')->dateTime('d/m/Y'),
                            ])->columns(3),
                    ]),
            ]);
    }
}