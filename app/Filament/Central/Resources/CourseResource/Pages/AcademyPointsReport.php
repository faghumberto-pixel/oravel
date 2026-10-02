<?php

namespace App\Filament\Central\Resources\CourseResource\Pages;

use App\Filament\Central\Resources\CourseResource;
use App\Models\Tenant;
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
 * Participacao dos colaboradores na Academia, de TODOS os clientes (visao da operacao Oravel),
 * com filtro de periodo e de cliente. Mesmos calculos da tela do admin do cliente.
 */
class AcademyPointsReport extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string $resource = CourseResource::class;

    protected static string $view = 'filament.central.academy-points';

    protected static ?string $title = 'Academia: participação dos clientes';

    public string $period = '30';

    public ?string $tenantId = null;

    public function tenantOptions(): array
    {
        return Tenant::withoutGlobalScopes()->orderBy('name')->pluck('name', 'id')->all();
    }

    private function tenant(): ?string
    {
        return $this->tenantId ?: null;
    }

    private function since()
    {
        return app(AcademyParticipation::class)->since($this->period);
    }

    public function summary(): array
    {
        return app(AcademyParticipation::class)->summary($this->tenant(), $this->since());
    }

    public function courses(): Collection
    {
        return app(AcademyParticipation::class)->courses($this->tenant(), $this->since());
    }

    public function weekly(): array
    {
        return app(AcademyParticipation::class)->weekly($this->tenant());
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => User::query()->withoutGlobalScopes()->fromSub(
                app(AcademyParticipation::class)->usersQuery($this->tenant(), $this->since()), 'users'
            ))
            ->defaultSort('points', 'desc')
            ->columns(self::columns(true))
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
