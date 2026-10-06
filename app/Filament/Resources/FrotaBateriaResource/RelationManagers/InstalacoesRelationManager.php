<?php

namespace App\Filament\Resources\FrotaBateriaResource\RelationManagers;

use App\Models\FrotaBateria;
use App\Models\FrotaInstalacaoComponente;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

/** Histórico de instalações da bateria: em que veículo esteve, por quantos km e por que saiu. */
class InstalacoesRelationManager extends RelationManager
{
    protected static string $relationship = 'instalacoes';

    protected static ?string $title = 'Histórico de instalações';

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('ativo'))
            ->columns([
                Tables\Columns\TextColumn::make('instalado_em')->label('Instalada em')->dateTime('d/m/Y H:i'),
                Tables\Columns\TextColumn::make('ativo.placa')->label('Veículo')->state(fn (FrotaInstalacaoComponente $r) => $r->ativo?->placa ?: $r->ativo?->name),
                Tables\Columns\TextColumn::make('odometro_instalacao')->label('Km na instalação')->numeric(thousandsSeparator: '.'),
                Tables\Columns\TextColumn::make('removido_em')->label('Removida em')->dateTime('d/m/Y H:i')->placeholder('Instalada agora'),
                Tables\Columns\TextColumn::make('odometro_remocao')->label('Km na remoção')->numeric(thousandsSeparator: '.')->placeholder('—'),
                Tables\Columns\TextColumn::make('rodado')->label('Km rodados')->suffix(' km')
                    ->state(fn (FrotaInstalacaoComponente $r) => max(0, (int) ($r->removido_em ? $r->odometro_remocao : floor((float) $r->ativo?->odometro_atual)) - (int) $r->odometro_instalacao)),
                Tables\Columns\TextColumn::make('motivo_remocao')->label('Motivo')->formatStateUsing(fn (?string $state) => FrotaBateria::motivoRemocaoLabels()[$state] ?? '—'),
            ])
            ->defaultSort('instalado_em', 'desc')
            ->paginated(false);
    }
}
