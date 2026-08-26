<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EquipmentPickupRequestResource\Pages;
use App\Models\EquipmentPickupRequest;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * Onde o operador vê e aciona as solicitações de retirada abertas pelo
 * Client no Portal (App\Filament\Client\Pages\SolicitarRetirada). Sem
 * automação de despacho -- o operador muda o status manualmente aqui
 * (mesmo padrão de outras Resources simples do sistema).
 */
class EquipmentPickupRequestResource extends BaseResource
{
    protected static ?string $model = EquipmentPickupRequest::class;

    protected static ?string $navigationIcon = 'heroicon-o-truck';

    protected static ?string $navigationGroup = 'Logística';

    protected static ?string $navigationLabel = 'Solicitações de Retirada';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('client_id')
                ->relationship('client', 'name')
                ->label('Cliente')
                ->disabled(),
            Forms\Components\Select::make('asset_id')
                ->relationship('asset', 'name')
                ->label('Equipamento')
                ->disabled(),
            Forms\Components\Select::make('status')
                ->label('Status')
                ->options([
                    EquipmentPickupRequest::STATUS_SOLICITADO => 'Solicitado',
                    EquipmentPickupRequest::STATUS_AGENDADO => 'Agendado',
                    EquipmentPickupRequest::STATUS_CONCLUIDO => 'Concluído',
                ])
                ->required(),
            Forms\Components\Textarea::make('notes')
                ->label('Observações')
                ->rows(3),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('client.name')
                    ->label('Cliente'),
                Tables\Columns\TextColumn::make('asset.name')
                    ->label('Equipamento'),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge(),
                Tables\Columns\TextColumn::make('requested_at')
                    ->label('Solicitado em')
                    ->dateTime('d/m/Y H:i'),
            ])
            ->defaultSort('requested_at', 'desc')
            ->actions([
                Tables\Actions\EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEquipmentPickupRequests::route('/'),
            'edit' => Pages\EditEquipmentPickupRequest::route('/{record}/edit'),
        ];
    }
}
