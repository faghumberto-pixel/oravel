<?php

namespace App\Filament\Central\Resources;

use App\Filament\Central\Resources\CourseResource\Pages;
use App\Filament\Central\Resources\CourseResource\RelationManagers\LessonsRelationManager;
use App\Models\Course;
use App\Models\LessonProgress;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

/**
 * Cursos da Academia Oravel (conteudo global, publicado aqui pela operacao SaaS).
 * O cliente ve so' o que esta publicado, no menu "Academia" do app.
 */
class CourseResource extends Resource
{
    protected static ?string $model = Course::class;

    protected static ?string $navigationIcon = 'heroicon-o-academic-cap';

    protected static ?string $navigationGroup = 'Gestão SaaS';

    protected static ?string $navigationLabel = 'Academia (cursos)';

    protected static ?string $modelLabel = 'curso';

    protected static ?string $pluralModelLabel = 'cursos';

    protected static bool $isScopedToTenant = false;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withoutGlobalScopes()->withCount('lessons');
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('title')
                ->label('Título')
                ->required()
                ->maxLength(191)
                ->live(onBlur: true)
                ->afterStateUpdated(fn ($state, Forms\Set $set, ?Course $record) => $record ? null : $set('slug', Str::slug((string) $state))),
            Forms\Components\TextInput::make('slug')
                ->label('Identificador (URL)')
                ->required()
                ->unique(ignoreRecord: true)
                ->maxLength(191),
            Forms\Components\Textarea::make('description')
                ->label('Descrição')
                ->rows(3)
                ->columnSpanFull(),
            Forms\Components\TextInput::make('position')
                ->label('Ordem')
                ->numeric()
                ->default(0),
            Forms\Components\Toggle::make('is_published')
                ->label('Publicado (aparece para os clientes)')
                ->default(false),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('position')
            ->columns([
                Tables\Columns\TextColumn::make('title')->label('Curso')->searchable()->weight('bold'),
                Tables\Columns\TextColumn::make('lessons_count')->label('Aulas')->alignCenter(),
                Tables\Columns\IconColumn::make('is_published')->label('Publicado')->boolean(),
                Tables\Columns\TextColumn::make('clientes')
                    ->label('Clientes que já estudaram')
                    ->alignCenter()
                    ->getStateUsing(fn (Course $record) => LessonProgress::withoutGlobalScopes()
                        ->whereIn('lesson_id', $record->lessons()->pluck('id'))
                        ->distinct('tenant_id')->count('tenant_id')),
                Tables\Columns\TextColumn::make('usuarios')
                    ->label('Usuários')
                    ->alignCenter()
                    ->getStateUsing(fn (Course $record) => LessonProgress::withoutGlobalScopes()
                        ->whereIn('lesson_id', $record->lessons()->pluck('id'))
                        ->distinct('user_id')->count('user_id')),
            ])
            ->actions([Tables\Actions\EditAction::make()])
            ->bulkActions([]);
    }

    public static function getRelations(): array
    {
        return [LessonsRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCourses::route('/'),
            'create' => Pages\CreateCourse::route('/create'),
            'pontos' => Pages\AcademyPointsReport::route('/pontos'),
            'edit' => Pages\EditCourse::route('/{record}/edit'),
        ];
    }
}
