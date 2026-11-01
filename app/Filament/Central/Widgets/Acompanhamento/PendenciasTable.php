<?php

namespace App\Filament\Central\Widgets\Acompanhamento;

use App\Models\SiteAcompanhamentoPendencia;
use Filament\Forms;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class PendenciasTable extends TableWidget
{
    public const TIPOS = ['decisao' => 'Decisão', 'correcao' => 'Correção', 'teste' => 'Teste', 'tarefa' => 'Tarefa'];

    public const STATUS = ['aberta' => 'Aberta', 'em_andamento' => 'Em andamento', 'concluida' => 'Concluída'];

    protected static bool $isDiscovered = false;

    protected static ?string $heading = 'Pendências e decisões';

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return (bool) auth()->user()?->isSuperAdmin();
    }

    private static function formulario(): array
    {
        return [
            Forms\Components\TextInput::make('titulo')->label('O que precisa ser feito ou decidido')->required()->maxLength(200)->columnSpanFull(),
            Forms\Components\Textarea::make('detalhe')->label('Detalhes')->rows(4)->columnSpanFull(),
            Forms\Components\Select::make('tipo')->label('Tipo')->options(self::TIPOS)->default('decisao')->required()->native(false),
            Forms\Components\Select::make('status')->label('Status')->options(self::STATUS)->default('aberta')->required()->native(false),
            Forms\Components\TextInput::make('responsavel')->label('Responsável')->maxLength(120),
            Forms\Components\DatePicker::make('prazo')->label('Prazo')->native(false)->displayFormat('d/m/Y'),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(SiteAcompanhamentoPendencia::query()
                ->orderByRaw("case status when 'concluida' then 1 else 0 end")
                ->orderByRaw('prazo is null')->orderBy('prazo')->orderBy('created_at'))
            ->columns([
                Tables\Columns\TextColumn::make('titulo')->label('Pendência')->weight('bold')->wrap()->searchable()
                    ->description(fn (SiteAcompanhamentoPendencia $r): ?string => $r->detalhe),
                Tables\Columns\TextColumn::make('tipo')->label('Tipo')->badge()->formatStateUsing(fn (string $state): string => self::TIPOS[$state] ?? $state)->color('gray'),
                Tables\Columns\SelectColumn::make('status')->label('Status')->options(self::STATUS)->selectablePlaceholder(false),
                Tables\Columns\TextInputColumn::make('responsavel')->label('Responsável'),
                Tables\Columns\TextColumn::make('prazo')->label('Prazo')->date('d/m/Y')->placeholder('—'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options(self::STATUS)->label('Status'),
                Tables\Filters\SelectFilter::make('tipo')->options(self::TIPOS)->label('Tipo'),
                Tables\Filters\Filter::make('abertas')->label('Só em aberto')->query(fn (Builder $q): Builder => $q->where('status', '!=', 'concluida')),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()->label('Nova pendência')->icon('heroicon-o-plus')->form(self::formulario()),
            ])
            ->actions([
                Tables\Actions\Action::make('concluir')->label('Concluir')->icon('heroicon-o-check')->color('success')
                    ->visible(fn (SiteAcompanhamentoPendencia $r): bool => $r->status !== 'concluida')
                    ->action(fn (SiteAcompanhamentoPendencia $r) => $r->update(['status' => 'concluida'])),
                Tables\Actions\EditAction::make()->form(self::formulario()),
                Tables\Actions\DeleteAction::make(),
            ])
            ->paginated([5, 10, 25])
            ->defaultPaginationPageOption(10)
            ->emptyStateHeading('Nenhuma pendência');
    }
}
