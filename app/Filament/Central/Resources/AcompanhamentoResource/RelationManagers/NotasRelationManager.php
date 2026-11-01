<?php

namespace App\Filament\Central\Resources\AcompanhamentoResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class NotasRelationManager extends RelationManager
{
    protected static string $relationship = 'notas';

    protected static ?string $title = 'Acompanhamento (notas por data)';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\DatePicker::make('data')->label('Data')->default(now())->required()->native(false)->displayFormat('d/m/Y'),
            Forms\Components\TextInput::make('titulo')->label('Título')->maxLength(160),
            Forms\Components\Textarea::make('texto')->label('Acompanhamento e detalhes')->required()->rows(6)->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('titulo')
            ->columns([
                Tables\Columns\TextColumn::make('data')->label('Data')->date('d/m/Y')->sortable(),
                Tables\Columns\TextColumn::make('titulo')->label('Título')->weight('bold')->placeholder('—')->searchable(),
                Tables\Columns\TextColumn::make('texto')->label('Detalhes')->wrap()->searchable(),
                Tables\Columns\TextColumn::make('autor')->label('Quem registrou')->placeholder('—')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('data', 'desc')
            ->headerActions([
                Tables\Actions\CreateAction::make()->label('Nova nota')->icon('heroicon-o-plus')
                    ->mutateFormDataUsing(fn (array $data): array => $data + ['autor' => auth()->user()?->name]),
            ])
            ->actions([Tables\Actions\EditAction::make(), Tables\Actions\DeleteAction::make()])
            ->paginated([5, 10, 25])
            ->defaultPaginationPageOption(5)
            ->emptyStateHeading('Nenhuma nota ainda');
    }
}
