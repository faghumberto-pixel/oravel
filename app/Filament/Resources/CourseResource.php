<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CourseResource\Pages;
use App\Models\Course;
use App\Models\LessonProgress;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Academia Oravel no app do cliente: so' leitura. Mostra os cursos PUBLICADOS
 * (conteudo global, cadastrado na Central) e o progresso do proprio usuario.
 * Ligado por contrato (HasSaaSMetadata em Course).
 */
class CourseResource extends BaseResource
{
    protected static ?string $model = Course::class;

    protected static ?string $navigationIcon = 'heroicon-o-academic-cap';

    protected static ?string $navigationLabel = 'Academia Oravel';

    protected static ?string $modelLabel = 'curso';

    protected static ?string $pluralModelLabel = 'Academia Oravel';

    protected static ?string $navigationGroup = 'Treinamento';

    protected static ?int $navigationSort = 99;

    protected static bool $isScopedToTenant = false;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->published()->available()
            ->withCount(['lessons' => fn (Builder $q) => $q->available()]);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    /** Quantas aulas do curso o usuario logado ja concluiu. */
    public static function completedBy(?string $userId, Course $course): int
    {
        if (! $userId) {
            return 0;
        }

        return LessonProgress::where('user_id', $userId)
            ->whereIn('lesson_id', $course->lessons()->available()->pluck('id'))
            ->count();
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('position')
            ->columns([
                Tables\Columns\TextColumn::make('title')->label('Curso')->weight('bold')->searchable()
                    ->description(fn (Course $record) => $record->description),
                Tables\Columns\TextColumn::make('lessons_count')->label('Aulas')->alignCenter(),
                Tables\Columns\TextColumn::make('progresso')
                    ->label('Seu progresso')
                    ->alignCenter()
                    ->getStateUsing(fn (Course $record) => self::completedBy(auth()->id(), $record).' de '.$record->lessons_count)
                    ->badge()
                    ->color(fn (Course $record) => $record->lessons_count > 0 && self::completedBy(auth()->id(), $record) >= $record->lessons_count ? 'success' : 'gray'),
            ])
            ->recordUrl(fn (Course $record) => static::getUrl('view', ['record' => $record]))
            ->actions([Tables\Actions\ViewAction::make()->label(fn (Course $record) => $record->lessons_count > 0 && self::completedBy(auth()->id(), $record) >= $record->lessons_count ? 'Rever' : 'Abrir')])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCourses::route('/'),
            'view' => Pages\ViewCourse::route('/{record}'),
        ];
    }
}
