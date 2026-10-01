<?php

namespace App\Filament\Central\Resources\CourseResource\RelationManagers;

use App\Models\Plan;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class LessonsRelationManager extends RelationManager
{
    protected static string $relationship = 'lessons';

    protected static ?string $title = 'Aulas';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('title')->label('Título')->required()->maxLength(191)->columnSpanFull(),
            Forms\Components\Textarea::make('summary')->label('Resumo')->rows(2)->columnSpanFull(),
            Forms\Components\TextInput::make('page_url')
                ->label('Página da base de conhecimento')
                ->url()
                ->placeholder('https://academy.oravel.com.br/modulos/ativos.html')
                ->columnSpanFull(),
            Forms\Components\TextInput::make('video_url')
                ->label('Vídeo (YouTube ou Vimeo)')
                ->url()
                ->helperText('Só o link. Use YouTube "não listado" ou Vimeo.')
                ->columnSpanFull(),
            Forms\Components\RichEditor::make('body')->label('Texto de apoio')->columnSpanFull(),
            Forms\Components\FileUpload::make('attachment_path')
                ->label('Anexo (PDF)')
                ->acceptedFileTypes(['application/pdf'])
                ->directory('academy')
                ->maxSize(10240)
                ->columnSpanFull(),
            Forms\Components\Select::make('feature_key')
                ->label('Módulo do contrato que esta aula ensina')
                ->options(fn () => Plan::getAvailableFeaturesOptions())
                ->searchable()
                ->placeholder('Todos os clientes com a Academia')
                ->helperText('O cliente só vê a aula se o contrato dele incluir esse módulo. Vazio = aparece para todos.')
                ->columnSpanFull(),
            Forms\Components\TextInput::make('position')->label('Ordem')->numeric()->default(0),
        ])->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
            ->defaultSort('position')
            ->reorderable('position')
            ->columns([
                Tables\Columns\TextColumn::make('position')->label('#')->width('3rem'),
                Tables\Columns\TextColumn::make('title')->label('Aula')->searchable(),
                Tables\Columns\TextColumn::make('feature_key')->label('Módulo')->placeholder('Todos')->formatStateUsing(fn ($state) => Plan::getAvailableFeaturesOptions()[$state] ?? $state)->badge()->color('gray'),
                Tables\Columns\IconColumn::make('video_url')->label('Vídeo')->boolean()->getStateUsing(fn ($record) => (bool) $record->embedUrl()),
                Tables\Columns\IconColumn::make('page_url')->label('Página')->boolean()->getStateUsing(fn ($record) => (bool) $record->page_url),
            ])
            ->headerActions([Tables\Actions\CreateAction::make()])
            ->actions([Tables\Actions\EditAction::make(), Tables\Actions\DeleteAction::make()]);
    }
}
