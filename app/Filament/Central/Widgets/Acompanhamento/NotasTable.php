<?php

namespace App\Filament\Central\Widgets\Acompanhamento;

use App\Models\SiteAcompanhamentoNota;
use Filament\Forms;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class NotasTable extends TableWidget
{
    protected static bool $isDiscovered = false;

    protected static ?string $heading = 'Acompanhamento (notas por data)';

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return (bool) auth()->user()?->isSuperAdmin();
    }

    private static function formulario(): array
    {
        return [
            Forms\Components\DatePicker::make('data')->label('Data')->default(now())->required()->native(false)->displayFormat('d/m/Y'),
            Forms\Components\TextInput::make('titulo')->label('Título')->maxLength(160),
            Forms\Components\Textarea::make('texto')->label('Acompanhamento e detalhes')->required()->rows(6)->columnSpanFull(),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(SiteAcompanhamentoNota::query())
            ->columns([
                Tables\Columns\TextColumn::make('data')->label('Data')->date('d/m/Y')->sortable(),
                Tables\Columns\TextColumn::make('titulo')->label('Título')->weight('bold')->placeholder('—')->searchable(),
                Tables\Columns\TextColumn::make('texto')->label('Detalhes')->wrap()->searchable(),
                Tables\Columns\TextColumn::make('autor')->label('Quem registrou')->placeholder('—')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('data', 'desc')
            ->headerActions([
                Tables\Actions\CreateAction::make()->label('Nova nota')->icon('heroicon-o-plus')->form(self::formulario())
                    ->mutateFormDataUsing(fn (array $data): array => $data + ['autor' => auth()->user()?->name]),
            ])
            ->actions([
                Tables\Actions\EditAction::make()->form(self::formulario()),
                Tables\Actions\DeleteAction::make(),
            ])
            ->paginated([5, 10, 25])
            ->defaultPaginationPageOption(5)
            ->emptyStateHeading('Nenhuma nota ainda')
            ->emptyStateDescription('Use "Nova nota" para registrar um acompanhamento.');
    }
}
