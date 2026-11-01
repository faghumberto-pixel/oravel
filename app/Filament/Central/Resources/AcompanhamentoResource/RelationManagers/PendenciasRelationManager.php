<?php

namespace App\Filament\Central\Resources\AcompanhamentoResource\RelationManagers;

use App\Models\AcompanhamentoPendencia;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PendenciasRelationManager extends RelationManager
{
    public const TIPOS = ['decisao' => 'Decisão', 'correcao' => 'Correção', 'teste' => 'Teste', 'tarefa' => 'Tarefa'];

    public const STATUS = ['aberta' => 'Aberta', 'em_andamento' => 'Em andamento', 'concluida' => 'Concluída'];

    protected static string $relationship = 'pendencias';

    protected static ?string $title = 'Pendências e decisões';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('titulo')->label('O que precisa ser feito ou decidido')->required()->maxLength(200)->columnSpanFull(),
            Forms\Components\Textarea::make('detalhe')->label('Detalhes')->rows(4)->columnSpanFull(),
            Forms\Components\Select::make('tipo')->label('Tipo')->options(self::TIPOS)->default('decisao')->required()->native(false),
            Forms\Components\Select::make('status')->label('Status')->options(self::STATUS)->default('aberta')->required()->native(false),
            Forms\Components\TextInput::make('responsavel')->label('Responsável')->maxLength(120),
            Forms\Components\DatePicker::make('prazo')->label('Prazo')->native(false)->displayFormat('d/m/Y'),
        ])->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('titulo')
            ->columns([
                Tables\Columns\TextColumn::make('titulo')->label('Pendência')->weight('bold')->wrap()->searchable()
                    ->description(fn (AcompanhamentoPendencia $r): ?string => $r->detalhe),
                Tables\Columns\TextColumn::make('tipo')->label('Tipo')->badge()->color('gray')->formatStateUsing(fn (string $state): string => self::TIPOS[$state] ?? $state),
                Tables\Columns\SelectColumn::make('status')->label('Status')->options(self::STATUS)->selectablePlaceholder(false),
                Tables\Columns\TextInputColumn::make('responsavel')->label('Responsável'),
                Tables\Columns\TextColumn::make('prazo')->label('Prazo')->date('d/m/Y')->placeholder('—'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options(self::STATUS)->label('Status'),
                Tables\Filters\SelectFilter::make('tipo')->options(self::TIPOS)->label('Tipo'),
                Tables\Filters\Filter::make('abertas')->label('Só em aberto')->query(fn (Builder $q): Builder => $q->where('status', '!=', 'concluida')),
            ])
            ->headerActions([Tables\Actions\CreateAction::make()->label('Nova pendência')->icon('heroicon-o-plus')])
            ->actions([
                Tables\Actions\Action::make('concluir')->label('Concluir')->icon('heroicon-o-check')->color('success')
                    ->visible(fn (AcompanhamentoPendencia $r): bool => $r->status !== 'concluida')
                    ->action(fn (AcompanhamentoPendencia $r) => $r->update(['status' => 'concluida'])),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->defaultSort(fn (Builder $query): Builder => $query->orderByRaw("case status when 'concluida' then 1 else 0 end")->orderByRaw('prazo is null')->orderBy('prazo')->orderBy('created_at'))
            ->paginated([5, 10, 25])
            ->defaultPaginationPageOption(10)
            ->emptyStateHeading('Nenhuma pendência');
    }
}
