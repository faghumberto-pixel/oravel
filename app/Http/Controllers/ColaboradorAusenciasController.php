<?php

namespace App\Http\Controllers;

use App\Models\Absence;
use App\Models\Employee;
use App\Services\Colaborador\AusenciaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Faltas e ausências no app do colaborador: registrar (com foto do atestado, CID e explicação) e ver o próprio histórico. */
class ColaboradorAusenciasController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Absence::class);

        $colaborador = $this->colaborador($request);
        $faltas = $colaborador ? Absence::where('employee_id', $colaborador->id)->latest('start_date')->get() : collect();

        return view('colaborador.ausencias', [
            'semFicha' => ! $colaborador,
            'faltas' => $faltas,
            'diasNoAno' => $faltas->where('status', '!=', Absence::STATUS_REJEITADO)->filter(fn (Absence $a) => $a->start_date->year === now()->year)->sum(fn (Absence $a) => $a->days),
        ]);
    }

    public function nova(Request $request): View
    {
        Gate::authorize('create', Absence::class);

        return view('colaborador.ausencia-nova', ['semFicha' => ! $this->colaborador($request)]);
    }

    public function salvar(Request $request, AusenciaService $servico): RedirectResponse
    {
        Gate::authorize('create', Absence::class);

        $colaborador = $this->colaborador($request);
        abort_unless($colaborador, 422, 'Nenhum colaborador do Departamento Pessoal vinculado a este usuário.');

        $request->validate([
            'anexos' => ['nullable', 'array', 'max:'.AusenciaService::MAX_ANEXOS],
            'anexos.*' => ['file', 'mimes:pdf,jpg,jpeg,png,webp,heic', 'max:8192'],
        ]);

        try {
            $caminhos = collect($request->file('anexos', []))->map(fn ($f) => $f->store("absences/{$colaborador->tenant_id}", 'local'))->all();
            $servico->registrar($colaborador, $request->all(), $caminhos);
        } catch (ValidationException $e) {
            isset($caminhos) && Storage::disk('local')->delete($caminhos);
            throw $e;
        }

        return redirect()->route('colab.ausencias')->with('ok', 'Ausência enviada. O RH vai analisar.');
    }

    /** Foto/PDF do atestado: só o próprio colaborador ou quem pode ver faltas (RH) abre; nunca por URL pública. */
    public function anexo(Request $request, string $id, int $indice): StreamedResponse
    {
        $falta = Absence::findOrFail($id);
        $dono = $falta->employee?->user_id === $request->user()->id;

        abort_unless($dono || Gate::allows('update', $falta), 403);

        $caminho = $falta->attachmentPaths()[$indice] ?? null;
        abort_unless($caminho && Storage::disk('local')->exists($caminho), 404);

        return Storage::disk('local')->response($caminho);
    }

    private function colaborador(Request $request): ?Employee
    {
        return Employee::where('user_id', $request->user()->id)->first();
    }
}
