<?php

namespace App\Filament\Central\Widgets\Acompanhamento;

use App\Models\SiteAcompanhamentoPagina;
use Filament\Forms;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class PaginasTable extends TableWidget
{
    public const STATUS = [
        'nao_convertida' => 'Padrão antigo',
        'rascunho' => 'Rascunho',
        'em_revisao' => 'Em revisão',
        'aprovada' => 'Aprovada',
        'publicada' => 'Publicada',
    ];

    protected static bool $isDiscovered = false;

    protected static ?string $heading = 'Status por página';

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return (bool) auth()->user()?->isSuperAdmin();
    }

    private static function formulario(): array
    {
        return [
            Forms\Components\TextInput::make('nome')->label('Nome')->required()->maxLength(160),
            Forms\Components\TextInput::make('slug')->label('Endereço (começa com /)')->required()->maxLength(190)
                ->unique(SiteAcompanhamentoPagina::class, 'slug', ignoreRecord: true),
            Forms\Components\TextInput::make('grupo')->label('Grupo')->maxLength(80),
            Forms\Components\Select::make('status')->label('Status')->options(self::STATUS)->default('rascunho')->required()->native(false),
            Forms\Components\Textarea::make('observacao')->label('Observação')->rows(3)->columnSpanFull(),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(SiteAcompanhamentoPagina::query())
            ->columns([
                Tables\Columns\TextColumn::make('grupo')->label('Grupo')->badge()->color('gray')->sortable()->placeholder('—'),
                Tables\Columns\TextColumn::make('nome')->label('Página')->weight('bold')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('slug')->label('Endereço')->searchable()
                    ->url(fn (SiteAcompanhamentoPagina $r): string => 'https://oravel.com.br'.$r->slug, true)->color('primary'),
                Tables\Columns\SelectColumn::make('status')->label('Status')->options(self::STATUS)->selectablePlaceholder(false),
                Tables\Columns\TextInputColumn::make('observacao')->label('Observação'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options(self::STATUS),
                Tables\Filters\SelectFilter::make('grupo')->options(fn () => SiteAcompanhamentoPagina::query()->whereNotNull('grupo')->distinct()->orderBy('grupo')->pluck('grupo', 'grupo')->all()),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()->label('Nova página')->icon('heroicon-o-plus')->form(self::formulario()),
            ])
            ->actions([
                Tables\Actions\EditAction::make()->form(self::formulario()),
                Tables\Actions\DeleteAction::make(),
            ])
            ->defaultSort('grupo')
            ->paginated([10, 25, 50])
            ->defaultPaginationPageOption(10);
    }
}
