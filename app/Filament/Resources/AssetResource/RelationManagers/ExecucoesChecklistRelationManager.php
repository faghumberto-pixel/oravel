<?php

namespace App\Filament\Resources\AssetResource\RelationManagers;

use App\Filament\Resources\CkExecutionResource;
use App\Models\CkExecution;
use App\Support\CkExecucaoExport;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/** C4 -- historico de checklists executados neste equipamento (inclui os copiados da Frota). Somente leitura. */
class ExecucoesChecklistRelationManager extends RelationManager
{
    protected static string $relationship = 'ckExecucoes';

    protected static ?string $title = 'Checklists executados';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return Gate::allows('viewAny', CkExecution::class);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['executor'])->withCount([
                'respostas as criticos_nc_count' => fn ($q) => $q->where('critico', true)->where('resposta', 'nao_conforme'),
                'respostas as pendencias_count' => fn ($q) => $q->where('critico', false)->where('resposta', 'nao_conforme'),
            ]))
            ->defaultSort('finalizado_em', 'desc')
            ->recordUrl(fn (CkExecution $r) => CkExecutionResource::getUrl('view', ['record' => $r]))
            ->columns([
                Tables\Columns\TextColumn::make('finalizado_em')->label('Data')->dateTime('d/m/Y H:i')->sortable(),
                Tables\Columns\TextColumn::make('template_nome')->label('Checklist')->wrap(),
                Tables\Columns\TextColumn::make('executor.name')->label('Executor')->placeholder('—'),
                Tables\Columns\TextColumn::make('status')->label('Resultado')->badge()
                    ->formatStateUsing(fn (?string $state) => CkExecucaoExport::STATUS[$state] ?? $state)
                    ->color(fn (?string $state) => match ($state) {
                        CkExecution::STATUS_APROVADO => 'success', CkExecution::STATUS_APROVADO_COM_RESSALVA => 'warning', CkExecution::STATUS_REPROVADO => 'danger', default => 'gray'
                    }),
                Tables\Columns\TextColumn::make('criticos_nc_count')->label('Críticos')->alignEnd(),
                Tables\Columns\TextColumn::make('pendencias_count')->label('Pendências')->alignEnd(),
                Tables\Columns\TextColumn::make('legado_tipo')->label('Origem')->badge()->formatStateUsing(fn (?string $state) => $state ? 'Frota' : 'Checklist')->color('gray'),
            ]);
    }
}
