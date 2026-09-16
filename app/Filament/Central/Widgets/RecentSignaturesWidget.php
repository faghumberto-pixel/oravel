<?php

namespace App\Filament\Central\Widgets;

use App\Models\Signature;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class RecentSignaturesWidget extends BaseWidget
{
    protected int | string | array $columnSpan = 'full';

    protected static ?string $heading = 'Últimas Assinaturas SLA + LGPD';

    protected static ?string $description = 'Últimas 10 assinaturas recebidas';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Signature::query()
                    ->latest('signed_at')
                    ->limit(10)
            )
            ->columns([
                Tables\Columns\TextColumn::make('company')
                    ->label('Empresa')
                    ->searchable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->copyable(),

                Tables\Columns\TextColumn::make('name')
                    ->label('Responsável')
                    ->searchable(),

                Tables\Columns\TextColumn::make('signed_at')
                    ->label('Data')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                Tables\Columns\IconColumn::make('email_sent')
                    ->label('Email Enviado')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->url(fn (Signature $record) => \App\Filament\Central\Resources\SignatureResource::getUrl('view', ['record' => $record])),
            ])
            ->paginated(false)
            ->striped();
    }
}
