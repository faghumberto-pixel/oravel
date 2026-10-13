<?php

namespace App\Filament\Pages;

use App\Models\Absence;
use App\Models\Employee;
use Filament\Pages\Page;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Histórico de faltas e ausências: filtros por período, colaborador, tipo, status e CID, com totais e CSV. */
class RelatorioFaltas extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationGroup = 'Departamento Pessoal';

    protected static ?string $navigationLabel = 'Relatório de Faltas';

    protected static ?int $navigationSort = 2;

    protected static ?string $title = 'Relatório de Faltas e Ausências';

    protected static string $view = 'filament.pages.relatorio-faltas';

    public ?string $de = null;

    public ?string $ate = null;

    public ?string $colaborador = null;

    public ?string $tipo = null;

    public ?string $status = null;

    public ?string $cid = null;

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can('viewAny', Absence::class);
    }

    public function mount(): void
    {
        $this->de = now()->startOfYear()->toDateString();
        $this->ate = now()->toDateString();
    }

    private function consulta(): Builder
    {
        return Absence::query()->with('employee')
            ->when($this->de, fn ($q) => $q->whereDate('end_date', '>=', $this->de))
            ->when($this->ate, fn ($q) => $q->whereDate('start_date', '<=', $this->ate))
            ->when($this->colaborador, fn ($q) => $q->where('employee_id', $this->colaborador))
            ->when($this->tipo, fn ($q) => $q->where('type', $this->tipo))
            ->when($this->status, fn ($q) => $q->where('status', $this->status))
            ->when(filled($this->cid), fn ($q) => $q->where('cid_code', 'like', strtoupper(str_replace('.', '', trim($this->cid))).'%'));
    }

    /** @return Collection<int, Absence> */
    public function registros(): Collection
    {
        return $this->consulta()->orderByDesc('start_date')->get();
    }

    /** @return array<string, mixed> */
    public function dados(): array
    {
        $r = $this->registros();
        $validos = $r->where('status', '!=', Absence::STATUS_REJEITADO);

        return [
            'registros' => $r,
            'total' => $r->count(),
            'dias' => $validos->sum(fn (Absence $a) => $a->days),
            'medicos' => $validos->where('type', Absence::TIPO_ATESTADO)->count(),
            'semAnexo' => $r->where('type', Absence::TIPO_ATESTADO)->filter(fn (Absence $a) => $a->attachmentPaths() === [])->count(),
            'pendentes' => $r->where('status', Absence::STATUS_PENDENTE)->count(),
            'porColaborador' => $validos->groupBy('employee_id')->map(fn (Collection $g) => [
                'nome' => $g->first()->employee?->name ?? '—',
                'registros' => $g->count(),
                'dias' => $g->sum(fn (Absence $a) => $a->days),
                'ultima' => $g->max('start_date'),
            ])->sortByDesc('dias')->values(),
            'porCid' => $validos->whereNotNull('cid_code')->groupBy('cid_code')->map(fn (Collection $g, string $c) => ['cid' => $c, 'registros' => $g->count(), 'dias' => $g->sum(fn (Absence $a) => $a->days)])->sortByDesc('registros')->values(),
            'porMes' => $validos->groupBy(fn (Absence $a) => $a->start_date->format('Y-m'))->map(fn (Collection $g, string $m) => ['mes' => Carbon::createFromFormat('Y-m', $m)->format('m/Y'), 'registros' => $g->count(), 'dias' => $g->sum(fn (Absence $a) => $a->days)])->sortKeysDesc()->values(),
        ];
    }

    /** @return array<string, string> */
    public function colaboradores(): array
    {
        return Employee::query()->orderBy('name')->pluck('name', 'id')->all();
    }

    public function exportar(): StreamedResponse
    {
        $registros = $this->registros();

        return response()->streamDownload(function () use ($registros) {
            $f = fopen('php://output', 'w');
            fwrite($f, "\xEF\xBB\xBF");
            fputcsv($f, ['Colaborador', 'Tipo', 'De', 'Até', 'Dias', 'CID', 'Motivo', 'Status', 'Anexos', 'Registrado em'], ';');
            foreach ($registros as $a) {
                fputcsv($f, [$a->employee?->name, Absence::typeLabels()[$a->type] ?? $a->type, $a->start_date->format('d/m/Y'), $a->end_date->format('d/m/Y'), $a->days, $a->cid_code, $a->reason, Absence::statusLabels()[$a->status] ?? $a->status, count($a->attachmentPaths()), $a->created_at->format('d/m/Y H:i')], ';');
            }
            fclose($f);
        }, 'faltas-e-ausencias-'.now()->format('Ymd').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
