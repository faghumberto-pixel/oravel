<?php

namespace App\Http\Controllers;

use App\Models\Absence;
use App\Models\Employee;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Faltas e ausências no app do colaborador: registrar (com foto do atestado, CID e explicação) e ver o próprio histórico. */
class ColaboradorAusenciasController extends Controller
{
    private const MAX_ANEXOS = 5;

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

    public function salvar(Request $request): RedirectResponse
    {
        Gate::authorize('create', Absence::class);

        $colaborador = $this->colaborador($request);
        abort_unless($colaborador, 422, 'Nenhum colaborador do Departamento Pessoal vinculado a este usuário.');

        $dados = $request->validate([
            'type' => ['required', 'in:'.implode(',', array_keys(Absence::typeLabels()))],
            'start_date' => ['required', 'date', 'before_or_equal:today'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'reason' => ['required', 'string', 'max:191'],
            'cid_code' => ['nullable', 'string', 'max:10', 'regex:/^[A-Za-z]\d{2}(\.?\w{1,2})?$/'],
            'details' => ['nullable', 'string', 'max:3000'],
            'anexos' => ['nullable', 'array', 'max:'.self::MAX_ANEXOS],
            'anexos.*' => ['file', 'mimes:pdf,jpg,jpeg,png,webp,heic', 'max:8192'],
        ], [
            'cid_code.regex' => 'CID inválido. Exemplo: J11 ou M54.5.',
            'start_date.before_or_equal' => 'A data de início não pode estar no futuro.',
            'end_date.after_or_equal' => 'A data final não pode ser antes da inicial.',
        ]);

        $caminhos = collect($request->file('anexos', []))
            ->map(fn ($arquivo) => $arquivo->store("absences/{$colaborador->tenant_id}", 'local'))
            ->all();

        Absence::create([
            'tenant_id' => $colaborador->tenant_id,
            'employee_id' => $colaborador->id,
            'type' => $dados['type'],
            'start_date' => $dados['start_date'],
            'end_date' => $dados['end_date'],
            'reason' => $dados['reason'],
            'cid_code' => filled($dados['cid_code'] ?? null) ? strtoupper(str_replace('.', '', $dados['cid_code'])) : null,
            'details' => $dados['details'] ?? null,
            'attachments' => $caminhos ?: null,
            'attachment_path' => $caminhos[0] ?? null,
            'status' => Absence::STATUS_PENDENTE,
        ]);

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
