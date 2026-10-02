<?php

namespace App\Filament\Resources\CourseResource\Pages;

use App\Filament\Resources\CourseResource;
use App\Models\User;
use App\Services\AcademyParticipation;
use Filament\Resources\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Participacao da equipe na Academia, so' pro ADMIN do cliente. O cliente (tenant) vem SEMPRE
 * do usuario logado -- nunca de parametro da tela -- entao um admin nao enxerga outra empresa.
 */
class Team extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string $resource = CourseResource::class;

    protected static string $view = 'filament.academy.team';

    protected static ?string $title = 'Participação da equipe';

    public string $period = '30';

    public function mount(): void
    {
        abort_unless(CourseResource::canViewAny() && auth()->user()?->isAdmin() && auth()->user()->tenant_id, 403);
    }

    private function tenantId(): string
    {
        return (string) auth()->user()->tenant_id;
    }

    private function since()
    {
        return app(AcademyParticipation::class)->since($this->period);
    }

    public function summary(): array
    {
        return app(AcademyParticipation::class)->summary($this->tenantId(), $this->since());
    }

    public function courses(): Collection
    {
        return app(AcademyParticipation::class)->courses($this->tenantId(), $this->since());
    }

    public function weekly(): array
    {
        return app(AcademyParticipation::class)->weekly($this->tenantId());
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => User::query()->withoutGlobalScopes()->fromSub(
                app(AcademyParticipation::class)->usersQuery($this->tenantId(), $this->since()), 'users'
            ))
            ->defaultSort('points', 'desc')
            ->columns(self::columns(false))
            ->filters(self::filters())
            ->paginated([25, 50, 100])
            ->emptyStateHeading('Nenhum colaborador encontrado');
    }

    /** Colunas compartilhadas com a tela da Central (la com a coluna de cliente). */
    public static function columns(bool $withTenant): array
    {
        return array_values(array_filter([
            Tables\Columns\TextColumn::make('name')->label('Colaborador')->searchable()->sortable()->weight('bold'),
            $withTenant ? Tables\Columns\TextColumn::make('tenant_name')->label('Cliente')->searchable()->sortable() : null,
            Tables\Columns\ViewColumn::make('completion')->label('Conteúdo concluído')->view('filament.academy.columns.completion')
                ->sortable(query: fn ($query, string $direction) => $query->orderByRaw('(case when lessons_available > 0 then lessons_done_all::float / lessons_available else 0 end) '.$direction)),
            Tables\Columns\ViewColumn::make('quiz_rate')->label('Acerto no quiz')->view('filament.academy.columns.quiz')
                ->sortable(query: fn ($query, string $direction) => $query->orderByRaw('(case when quiz_total > 0 then quiz_correct::float / quiz_total else -1 end) '.$direction)),
            Tables\Columns\TextColumn::make('points')->label('Pontos')->numeric()->sortable()->alignEnd(),
            Tables\Columns\TextColumn::make('certificates')->label('Certificados')->numeric()->sortable()->alignEnd(),
            Tables\Columns\TextColumn::make('minutes')->label('Tempo de estudo')->sortable()->alignEnd()
                ->formatStateUsing(fn ($state) => (int) round($state) >= 60 ? intdiv((int) round($state), 60).' h '.((int) round($state) % 60).' min' : (int) round($state).' min'),
            Tables\Columns\TextColumn::make('last_activity')->label('Última atividade')->sortable()
                ->formatStateUsing(fn ($state) => $state ? Carbon::parse($state)->diffForHumans() : 'Nunca')
                ->color(fn ($state) => $state ? null : 'danger'),
        ]));
    }

    public static function filters(): array
    {
        return [
            Tables\Filters\SelectFilter::make('situacao')
                ->label('Situação')
                ->options([
                    'ativos' => 'Estudando (com atividade no período)',
                    'inativos' => 'Sem atividade no período',
                    'certificados' => 'Já têm certificado',
                ])
                ->query(fn ($query, array $data) => match ($data['value'] ?? null) {
                    'ativos' => $query->where(fn ($q) => $q->where('points', '>', 0)->orWhere('lessons_done', '>', 0)),
                    'inativos' => $query->where('points', 0)->where('lessons_done', 0),
                    'certificados' => $query->where('certificates', '>', 0),
                    default => $query,
                }),
        ];
    }
}
