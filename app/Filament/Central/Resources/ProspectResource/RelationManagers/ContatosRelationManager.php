<?php

namespace App\Filament\Central\Resources\ProspectResource\RelationManagers;

use App\Models\Prospect;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class ContatosRelationManager extends RelationManager
{
    protected static string $relationship = 'contatos';

    protected static ?string $title = 'Acompanhamento (contatos por data)';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\DatePicker::make('data')->label('Data')->default(now())->required()->native(false)->displayFormat('d/m/Y'),
            Forms\Components\Select::make('canal')->options(Prospect::CANAIS)->default('whatsapp')->required()->native(false),
            Forms\Components\TextInput::make('titulo')->label('Título')->maxLength(160)->columnSpanFull(),
            Forms\Components\Textarea::make('texto')->label('O que foi dito / combinado')->required()->rows(5)->columnSpanFull(),
        ])->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('titulo')
            ->columns([
                Tables\Columns\TextColumn::make('data')->label('Data')->date('d/m/Y')->sortable(),
                Tables\Columns\TextColumn::make('canal')->badge()->formatStateUsing(fn (string $state): string => Prospect::CANAIS[$state] ?? $state),
                Tables\Columns\TextColumn::make('titulo')->label('Título')->weight('bold')->placeholder('—'),
                Tables\Columns\TextColumn::make('texto')->label('Detalhes')->wrap()->searchable(),
            ])
            ->defaultSort('data', 'desc')
            ->headerActions([
                Tables\Actions\CreateAction::make()->label('Novo contato')->icon('heroicon-o-plus')
                    ->mutateFormDataUsing(fn (array $data): array => $data + ['autor' => auth()->user()?->name])
                    ->after(fn () => $this->getOwnerRecord()->update(['ultimo_contato_em' => now()])),
            ])
            ->actions([Tables\Actions\EditAction::make(), Tables\Actions\DeleteAction::make()])
            ->emptyStateHeading('Nenhum contato registrado');
    }
}
