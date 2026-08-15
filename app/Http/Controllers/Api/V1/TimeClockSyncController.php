<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreTimeClockRequest;
use App\Models\Employee;
use App\Models\Role;
use App\Models\TimeClock;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Recebe o lote de batidas de ponto capturadas offline no device do
 * colaborador e sincroniza com o banco -- mesmo padrão de
 * HourMeterSyncController: cada item processado isoladamente (um item
 * ruim não derruba o lote), idempotente por client_uuid.
 */
class TimeClockSyncController extends Controller
{
    public function sync(StoreTimeClockRequest $request): JsonResponse
    {
        $user = $request->user();
        $tenantId = $user->tenant_id;
        $results = [];

        foreach ($request->validated('batidas') as $payload) {
            $results[] = $this->syncOne($tenantId, $user, $payload);
        }

        return response()->json([
            'synced' => collect($results)->where('status', 'synced')->count(),
            'failed' => collect($results)->where('status', 'failed')->count(),
            'results' => $results,
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function syncOne(string $tenantId, User $user, array $payload): array
    {
        $clientUuid = $payload['client_uuid'];

        $existing = TimeClock::query()
            ->where('tenant_id', $tenantId)
            ->where('client_uuid', $clientUuid)
            ->first();

        if ($existing && $existing->sync_status === TimeClock::SYNC_SYNCED) {
            return $this->result($clientUuid, 'synced', $existing->id);
        }

        $employee = Employee::query()->find($payload['employee_id']);

        if (! $employee || $employee->tenant_id !== $tenantId) {
            return $this->result($clientUuid, 'failed', null, 'Colaborador não encontrado.');
        }

        // Um colaborador só pode registrar o próprio ponto -- exceto quem
        // tem nível mínimo de Supervisor no setor do colaborador (registro
        // assistido, ex: encarregado batendo ponto de quem não tem device).
        $isSelf = $employee->user_id === $user->id;
        $canRegisterForOthers = $user->hasMinimumLevelInSector(
            $employee->department?->sector_key ?? '',
            Role::LEVEL_SUPERVISOR
        );

        if (! $isSelf && ! $canRegisterForOthers) {
            return $this->result($clientUuid, 'failed', null, 'Sem permissão para registrar ponto deste colaborador.');
        }

        try {
            $record = DB::transaction(function () use ($tenantId, $payload, $clientUuid, $existing) {
                $attributes = [
                    'tenant_id' => $tenantId,
                    'employee_id' => $payload['employee_id'],
                    'tipo' => $payload['tipo'],
                    'recorded_at' => now(),
                    'device_recorded_at' => $payload['device_recorded_at'],
                    'latitude' => $payload['latitude'] ?? null,
                    'longitude' => $payload['longitude'] ?? null,
                    'sync_status' => TimeClock::SYNC_SYNCED,
                    'synced_at' => now(),
                ];

                if ($existing) {
                    $existing->update($attributes);

                    return $existing;
                }

                $attributes['client_uuid'] = $clientUuid;

                return TimeClock::create($attributes);
            });

            return $this->result($clientUuid, 'synced', $record->id);
        } catch (Throwable $e) {
            report($e);

            return $this->result($clientUuid, 'failed', null, 'Erro interno ao sincronizar esta batida.');
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function result(string $clientUuid, string $status, ?string $id = null, ?string $error = null): array
    {
        return [
            'client_uuid' => $clientUuid,
            'status' => $status,
            'id' => $id,
            'error' => $error,
        ];
    }
}
