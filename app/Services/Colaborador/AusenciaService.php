<?php

namespace App\Services\Colaborador;

use App\Models\Absence;
use App\Models\Employee;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/** Regras e gravação da falta/ausência comunicada pelo colaborador (tela online e fila offline usam o mesmo caminho). */
class AusenciaService
{
    public const MAX_ANEXOS = 5;

    /** @return array<string, mixed> */
    public static function regras(): array
    {
        return [
            'type' => ['required', 'in:'.implode(',', array_keys(Absence::typeLabels()))],
            'start_date' => ['required', 'date', 'before_or_equal:today'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'reason' => ['required', 'string', 'max:191'],
            'cid_code' => ['nullable', 'string', 'max:10', 'regex:/^[A-Za-z]\d{2}(\.?\w{1,2})?$/'],
            'details' => ['nullable', 'string', 'max:3000'],
        ];
    }

    /** @return array<string, string> */
    public static function mensagens(): array
    {
        return [
            'cid_code.regex' => 'CID inválido. Exemplo: J11 ou M54.5.',
            'start_date.before_or_equal' => 'A data de início não pode estar no futuro.',
            'end_date.after_or_equal' => 'A data final não pode ser antes da inicial.',
        ];
    }

    /**
     * @param  array<string, mixed>  $entrada
     * @param  array<int, string>  $caminhos  anexos já guardados no disco local
     * @param  string|null  $id  UUID vindo do celular (fila offline): reenviar a mesma ação não duplica
     *
     * @throws ValidationException
     */
    public function registrar(Employee $colaborador, array $entrada, array $caminhos = [], ?string $id = null): Absence
    {
        $dados = Validator::make($entrada, self::regras(), self::mensagens())->validate();

        $falta = new Absence([
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
        $id && $falta->id = $id;
        $falta->save();

        return $falta;
    }

    /** Acrescenta um arquivo (foto do atestado) a uma ausência já registrada. */
    public function anexar(Absence $falta, UploadedFile $arquivo): void
    {
        $caminho = $arquivo->store("absences/{$falta->tenant_id}", 'local');
        $lista = [...($falta->attachments ?? []), $caminho];

        $falta->update(['attachments' => $lista, 'attachment_path' => $falta->attachment_path ?: $caminho]);
    }
}
