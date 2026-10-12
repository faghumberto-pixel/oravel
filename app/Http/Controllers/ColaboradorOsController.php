<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\MaintenanceOrder;
use App\Support\Colaborador\SituacaoOs;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * OS no app do colaborador. O caminho é sempre PELO EQUIPAMENTO (QR, patrimônio, placa, TAG ou nome): acha o ativo,
 * lista as OS dele (abertas primeiro) e abre o dossiê da OS dali. Não se digita número de OS.
 */
class ColaboradorOsController extends Controller
{
    /** Minhas OS: as do técnico logado (technician_id), em aberto ou concluídas. */
    public function minhas(Request $request): View
    {
        $aba = $request->query('aba') === 'concluidas' ? 'concluidas' : 'abertas';
        $base = fn () => MaintenanceOrder::query()->where('technician_id', $request->user()->id);

        $query = $base()->with('asset');
        $aba === 'abertas'
            ? $query->whereNotIn('status', SituacaoOs::FECHADAS)->orderByRaw('is_prazo_fatal desc nulls last')->orderByRaw('scheduled_at asc nulls last')->orderBy('created_at')
            : $query->whereIn('status', SituacaoOs::FECHADAS)->orderByDesc('updated_at')->limit(20);

        $ordens = $query->get()->filter(fn ($os) => Gate::allows('view', $os))->values()->map(fn (MaintenanceOrder $os) => [
            'id' => $os->id,
            'numero' => $os->os_number,
            'equipamento' => $os->asset?->name,
            'identificacao' => collect([$os->asset?->patrimonio ? 'PAT '.$os->asset->patrimonio : null, $os->asset?->placa ? 'Placa '.$os->asset->placa : null])->filter()->implode(' · '),
            'situacao' => SituacaoOs::daOs($os),
            'aberta_em' => $os->created_at,
            'dias' => SituacaoOs::diasEmAberto($os),
            'agendada' => $os->scheduled_at,
            'prazo_fatal' => (bool) $os->is_prazo_fatal,
            'pode_executar' => ! in_array($os->status, SituacaoOs::FECHADAS, true) && Gate::allows('update', $os),
            'problema' => $os->description,
        ]);

        return view('colaborador.os-minhas', [
            'aba' => $aba,
            'ordens' => $ordens,
            'totalAbertas' => $base()->whereNotIn('status', SituacaoOs::FECHADAS)->count(),
        ]);
    }

    /** Busca do equipamento. Um só resultado vai direto para as OS dele. */
    public function buscar(Request $request): View|RedirectResponse
    {
        $termo = trim((string) ($request->query('q') ?? $request->query('busca')));

        if ($termo === '') {
            return view('colaborador.os-buscar', ['erro' => null, 'ativos' => collect(), 'termo' => '']);
        }

        $ativos = Asset::search($termo)->filter(fn (Asset $a) => Gate::allows('view', $a))->values();

        if ($ativos->count() === 1) {
            return redirect()->route('colab.os.ativo', ['id' => $ativos->first()->id]);
        }

        return view('colaborador.os-buscar', [
            'erro' => $ativos->isEmpty() ? 'Nenhum equipamento encontrado. Confira o patrimônio ou a placa.' : null,
            'ativos' => $ativos,
            'termo' => $termo,
        ]);
    }

    /** OS do equipamento: situação geral, as abertas e as últimas concluídas. */
    public function doAtivo(string $id): View
    {
        $ativo = Asset::query()->findOrFail($id);

        Gate::authorize('view', $ativo);

        $ordens = MaintenanceOrder::query()->where('asset_id', $ativo->id)->with('technician')->orderByDesc('created_at')->limit(30)->get()
            ->filter(fn ($os) => Gate::allows('view', $os))->values();

        $linha = fn (MaintenanceOrder $os) => [
            'id' => $os->id,
            'numero' => $os->os_number,
            'situacao' => SituacaoOs::daOs($os),
            'aberta_em' => $os->created_at,
            'dias' => SituacaoOs::diasEmAberto($os),
            'tecnico' => $os->technician?->name,
            'problema' => $os->description,
        ];

        $abertas = $ordens->reject(fn ($os) => in_array($os->status, SituacaoOs::FECHADAS, true))->sortBy('created_at')->map($linha)->values();
        $fechadas = $ordens->filter(fn ($os) => in_array($os->status, SituacaoOs::FECHADAS, true))->take(10)->map($linha)->values();

        return view('colaborador.os-ativo', [
            'ativo' => $ativo,
            'situacao' => SituacaoOs::doAtivo($ativo),
            'abertas' => $abertas,
            'fechadas' => $fechadas,
        ]);
    }

    public function mostrar(string $id): View
    {
        $os = MaintenanceOrder::query()->with(['asset', 'client', 'technician', 'reportedProblem', 'pendencias'])->findOrFail($id);

        Gate::authorize('view', $os);

        return view('colaborador.os-dossie', [
            'os' => $os,
            'situacao' => SituacaoOs::daOs($os),
            'dias' => SituacaoOs::diasEmAberto($os),
            'fechada' => in_array($os->status, SituacaoOs::FECHADAS, true),
            'podeExecutar' => ! in_array($os->status, SituacaoOs::FECHADAS, true) && Gate::allows('update', $os),
        ]);
    }
}
