<?php

namespace App\Filament\Resources\UserResource\RelationManagers;

use App\Filament\Resources\MaintenanceOrderResource;
use App\Models\MaintenanceOrder;
use App\Models\MaintenanceStatusHistory;
use App\Models\User;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * Quanto tempo o tecnico gastou em cada maquina e o que foi resolvido --
 * usa o cronometro real (MaintenanceOrder.total_time_seconds), nunca
 * hours_spent (coluna morta, nenhuma tela grava nela fora de seeder).
 *
 * Limitacao conhecida e aceita (decisao junto com o usuario): a relacao
 * "maintenanceOrders" e' via technician_id ATUAL -- uma OS que foi
 * transferida PRA FORA deste usuario nao aparece mais aqui (o dono atual
 * e' quem a ve agora). O segmento de tempo que ESTE usuario acumulou antes
 * de ser transferido fica preservado em MaintenanceStatusHistory
 * (old_technician_id + segment_seconds), so nao e' somado nesta listagem
 * especifica -- reconstruir "todas as OS que ja passaram pela mao dele"
 * exigiria uma consulta separada, fora de escopo por ora.
 */
class OrdensAtendidasRelationManager extends RelationManager
{
    protected static string $relationship = 'maintenanceOrders';

    protected static ?string $title = 'Ordens Atendidas';

    public static function canViewForRecord($ownerRecord, string $pageClass): bool
    {
        return $ownerRecord instanceof User;
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('os_number')
            ->columns([
                Tables\Columns\TextColumn::make('asset.patrimonio')
                    ->label('Patrimônio')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('asset.name')
                    ->label('Ativo')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('os_number')
                    ->label('O.S.'),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge(),
                Tables\Columns\TextColumn::make('description')
                    ->label('Descrição / Resolução')
                    ->limit(50)
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('tempo_trabalhado')
                    ->label('Tempo Trabalhado')
                    ->state(function (MaintenanceOrder $record) {
                        $technicianId = $this->getOwnerRecord()->id;

                        $segmentsAlheios = MaintenanceStatusHistory::where('maintenance_order_id', $record->id)
                            ->whereNotNull('segment_seconds')
                            ->sum('segment_seconds');

                        // Se nunca foi transferida, total_time_seconds inteiro e' deste
                        // tecnico. Se foi, o que sobra (total - segmentos ja fechados e
                        // atribuidos) e' o segmento dele ainda em curso/final -- so faz
                        // sentido se ele for o technician_id atual (garantido aqui, a
                        // relacao ja filtra por isso).
                        $segundos = $technicianId === $record->technician_id
                            ? max(0, (int) $record->total_time_seconds - (int) $segmentsAlheios)
                            : 0;

                        $horas = intdiv($segundos, 3600);
                        $minutos = intdiv($segundos % 3600, 60);

                        return $segundos > 0 ? "{$horas}h{$minutos}min" : '—';
                    }),
            ])
            ->headerActions([])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->url(fn (MaintenanceOrder $record) => MaintenanceOrderResource::getUrl('edit', ['record' => $record])),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
