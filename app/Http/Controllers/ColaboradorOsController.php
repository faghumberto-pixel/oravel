<?php

namespace App\Http\Controllers;

use App\Models\MaintenanceOrder;
use App\Support\Colaborador\SituacaoOs;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/** Dossiê da OS no app do colaborador (par do dossiê do ativo): busca pelo número e tela da ordem. */
class ColaboradorOsController extends Controller
{
    public function buscar(Request $request): View|RedirectResponse
    {
        $numero = trim((string) $request->query('numero'));

        if ($numero === '') {
            return view('colaborador.os-buscar', ['erro' => null, 'resultados' => collect()]);
        }

        // Busca flexível ("123" acha "OS-2026-00123"); BelongsToTenant já limita ao tenant do usuário.
        $achadas = MaintenanceOrder::query()->where('os_number', 'ilike', '%'.$numero.'%')->orderByDesc('created_at')->limit(15)->get()
            ->filter(fn ($os) => Gate::allows('view', $os))->values();

        if ($achadas->count() === 1) {
            return redirect()->route('colab.os', ['id' => $achadas->first()->id]);
        }

        return view('colaborador.os-buscar', [
            'erro' => $achadas->isEmpty() ? 'Nenhuma ordem de serviço encontrada.' : null,
            'resultados' => $achadas,
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
        ]);
    }
}
