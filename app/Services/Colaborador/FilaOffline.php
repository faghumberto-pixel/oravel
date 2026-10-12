<?php

namespace App\Services\Colaborador;

use App\Models\ColabFilaProcessada;
use App\Models\MaintenanceOrder;
use App\Models\User;
use App\Support\Colaborador\SituacaoOs;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

/**
 * Aplica no servidor as ações que o celular juntou offline. Cada ação tem um UUID criado no aparelho: reenviar a mesma
 * ação (queda de rede no meio do envio) nunca aplica duas vezes. Só repete o que o Modo Campo já faz: iniciar/pausar,
 * horímetro/combustível/notas, checklist, fotos, assinaturas e concluir. Peças e avarias continuam online.
 */
class FilaOffline
{
    private const STATUS_CHECKLIST = ['conforme', 'nao_conforme', 'nao_aplicavel'];

    private const NIVEIS_COMBUSTIVEL = ['0', '25', '50', '75', '100'];

    /** @return array{uuid: string, status: string, mensagem: ?string} */
    public function processar(User $user, array $acao, ?UploadedFile $arquivo = null): array
    {
        $uuid = (string) ($acao['uuid'] ?? '');
        $tipo = (string) ($acao['tipo'] ?? '');

        if (! Str::isUuid($uuid) || $tipo === '') {
            return ['uuid' => $uuid, 'status' => 'erro', 'mensagem' => 'Ação inválida.'];
        }

        if ($anterior = ColabFilaProcessada::withoutGlobalScopes()->where('user_id', $user->id)->where('acao_uuid', $uuid)->first()) {
            return ['uuid' => $uuid, 'status' => $anterior->status === 'erro' ? 'erro' : 'ok', 'mensagem' => $anterior->mensagem ?: 'Já recebida antes.'];
        }

        try {
            [$status, $mensagem] = DB::transaction(fn () => $this->aplicar($user, $tipo, (array) ($acao['dados'] ?? []), $arquivo, $acao['criado_em'] ?? null));
        } catch (\Throwable $e) {
            report($e);
            [$status, $mensagem] = ['erro', 'Falha ao aplicar no servidor.'];
        }

        ColabFilaProcessada::withoutGlobalScopes()->create([
            'tenant_id' => $user->tenant_id, 'user_id' => $user->id, 'acao_uuid' => $uuid,
            'tipo' => substr($tipo, 0, 40), 'status' => $status, 'mensagem' => $mensagem,
        ]);

        return ['uuid' => $uuid, 'status' => $status === 'erro' ? 'erro' : 'ok', 'mensagem' => $mensagem];
    }

    /** @return array{0: string, 1: ?string} [status ok|ignorada|erro, mensagem] */
    private function aplicar(User $user, string $tipo, array $d, ?UploadedFile $arquivo, ?string $criadoEm): array
    {
        $os = MaintenanceOrder::query()->find($d['os_id'] ?? null);

        if (! $os) {
            return ['erro', 'OS não encontrada.'];
        }

        $encerrada = in_array($os->status, SituacaoOs::FECHADAS, true);

        // O técnico dono da OS perde a edição depois de concluída (MaintenanceOrderPolicy::update): para ele o certo é o
        // aviso de "já encerrada", não "sem permissão".
        if ($encerrada && $os->technician_id === $user->id) {
            return ['ignorada', 'A OS já estava encerrada no servidor.'];
        }

        if (! Gate::forUser($user)->allows('update', $os)) {
            return ['erro', 'Sem permissão para esta OS.'];
        }

        if ($encerrada) {
            return ['ignorada', 'A OS já estava encerrada no servidor.'];
        }

        return match ($tipo) {
            'os_iniciar' => $this->mudarStatus($os, 'Em Andamento', 'Iniciada offline'.($criadoEm ? ' em '.$criadoEm : '')),
            'os_pausar' => $os->status === 'Em Andamento' ? $this->mudarStatus($os, 'Pausada', 'Pausada offline') : ['ok', 'Já estava parada.'],
            'os_dados' => $this->dados($os, $d),
            'os_checklist' => $this->checklist($os, $d),
            'os_assinaturas' => $this->assinaturas($os, $d),
            'os_foto' => $this->foto($os, $d, $arquivo, $criadoEm),
            'os_concluir' => $this->concluir($os),
            default => ['erro', 'Tipo de ação desconhecido.'],
        };
    }

    private function mudarStatus(MaintenanceOrder $os, string $novo, string $obs): array
    {
        if ($os->status === $novo) {
            return ['ok', 'Já estava assim.'];
        }

        $antigo = $os->status;
        $os->update(['status' => $novo]);
        $os->logStatusChange($novo, $antigo, $obs);

        return ['ok', null];
    }

    private function dados(MaintenanceOrder $os, array $d): array
    {
        $campos = [];

        if (array_key_exists('horimetro_entry', $d) && filled($d['horimetro_entry'])) {
            if (! is_numeric($d['horimetro_entry']) || (float) $d['horimetro_entry'] < 0) {
                return ['erro', 'Horímetro inválido.'];
            }
            $campos['horimetro_entry'] = $d['horimetro_entry'];
        }

        if (array_key_exists('fuel_level', $d) && filled($d['fuel_level'])) {
            if (! in_array((string) $d['fuel_level'], self::NIVEIS_COMBUSTIVEL, true)) {
                return ['erro', 'Nível de combustível inválido.'];
            }
            $campos['fuel_level'] = (string) $d['fuel_level'];
        }

        if (array_key_exists('technical_notes', $d)) {
            $campos['technical_notes'] = Str::limit((string) $d['technical_notes'], 5000, '');
        }

        // horimetro_entry alimenta Asset.horimetro_atual via hook em MaintenanceOrder::booted() -- não replicar aqui.
        $campos && $os->update($campos);

        return ['ok', null];
    }

    private function checklist(MaintenanceOrder $os, array $d): array
    {
        $item = $os->checklists()->whereKey($d['item_id'] ?? null)->first();

        if (! $item) {
            return ['erro', 'Item do checklist não encontrado.'];
        }

        if (! in_array($d['status'] ?? null, self::STATUS_CHECKLIST, true)) {
            return ['erro', 'Status do item inválido.'];
        }

        $item->status = $d['status'];
        if (array_key_exists('notas', $d)) {
            $item->notes = Str::limit((string) $d['notas'], 2000, '');
        }
        $item->save();

        return ['ok', null];
    }

    private function assinaturas(MaintenanceOrder $os, array $d): array
    {
        $campos = [];

        foreach (['technician_signature', 'client_signature'] as $campo) {
            if (blank($d[$campo] ?? null)) {
                continue;
            }
            if (! str_starts_with($d[$campo], 'data:image/png;base64,') || strlen($d[$campo]) > 600_000) {
                return ['erro', 'Assinatura inválida.'];
            }
            $campos[$campo] = $d[$campo];
        }

        $campos && $os->update($campos);

        return ['ok', null];
    }

    private function foto(MaintenanceOrder $os, array $d, ?UploadedFile $arquivo, ?string $criadoEm): array
    {
        if (! $arquivo || ! $arquivo->isValid() || ! str_starts_with((string) $arquivo->getMimeType(), 'image/') || $arquivo->getSize() > 8 * 1024 * 1024) {
            return ['erro', 'Foto inválida.'];
        }

        if (filled($d['item_id'] ?? null)) {
            $item = $os->checklists()->whereKey($d['item_id'])->first();
            if (! $item) {
                return ['erro', 'Item do checklist não encontrado.'];
            }
            $item->addMedia($arquivo->getRealPath())->usingFileName($arquivo->getClientOriginalName() ?: 'foto.jpg')->toMediaCollection('photos');

            return ['ok', null];
        }

        $os->evidences()->create([
            'file_path' => $arquivo->store("attachments/os_{$os->id}/campo", 'public'),
            'file_name' => $arquivo->getClientOriginalName(),
            'mime_type' => $arquivo->getMimeType(),
            'evidence_type' => 'campo',
            'captured_at' => $criadoEm ? Carbon::parse($criadoEm) : now(),
        ]);

        return ['ok', null];
    }

    private function concluir(MaintenanceOrder $os): array
    {
        $os->refresh();

        // Mesmas exigências do Modo Campo: horímetro na etapa 1 e as duas assinaturas na etapa 5.
        if ($os->horimetro_entry === null) {
            return ['erro', 'Falta informar o horímetro.'];
        }

        if (blank($os->technician_signature) || blank($os->client_signature)) {
            return ['erro', 'Faltam as assinaturas do técnico e do cliente.'];
        }

        return $this->mudarStatus($os, 'Concluída', 'Concluída offline');
    }
}
