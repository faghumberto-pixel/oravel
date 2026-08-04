<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreHourMeterRequest;
use App\Models\Asset;
use App\Models\EquipmentHourMeter;
use App\Models\HorimeterReading;
use App\Support\HourMeterReadingWriter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Recebe o lote de apontamentos de horímetro capturados offline pelo app
 * mobile do técnico e sincroniza com o banco. Cada item do lote é
 * processado em transação isolada: um item inválido/rejeitado não derruba
 * o resto do lote, e o app recebe de volta o client_uuid de cada item pra
 * saber quais já pode apagar do storage local.
 */
class HourMeterSyncController extends Controller
{
    public function __construct(private HourMeterReadingWriter $writer) {}

    public function sync(StoreHourMeterRequest $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $results = [];

        foreach ($request->validated('readings') as $index => $payload) {
            $photo = $request->file("readings.{$index}.photo");

            $results[] = $this->syncOne($tenantId, $request->user()->id, $payload, $photo);
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
    private function syncOne(string $tenantId, string $userId, array $payload, ?UploadedFile $photo): array
    {
        $clientUuid = $payload['client_uuid'];

        // Reenvio do mesmo lote (ex: app perdeu a resposta de rede) --
        // não duplica, apenas confirma o que já foi sincronizado.
        $existing = EquipmentHourMeter::query()
            ->where('tenant_id', $tenantId)
            ->where('client_uuid', $clientUuid)
            ->first();

        if ($existing && $existing->sync_status === EquipmentHourMeter::STATUS_SYNCED) {
            return $this->result($clientUuid, 'synced', $existing->id);
        }

        $asset = Asset::query()->find($payload['asset_id']);

        if (! $asset || Gate::forUser(request()->user())->denies('view', $asset)) {
            return $this->result($clientUuid, 'failed', null, 'Equipamento não encontrado ou sem permissão de acesso.');
        }

        $photoPath = $photo ? $this->writer->uploadPhoto($photo, $tenantId) : null;

        try {
            $record = DB::transaction(function () use ($tenantId, $userId, $payload, $clientUuid, $photoPath, $existing) {
                $reading = HorimeterReading::create([
                    'tenant_id' => $tenantId,
                    'asset_id' => $payload['asset_id'],
                    'reading' => $payload['reading'],
                    'recorded_at' => $payload['recorded_at'],
                    'recorded_by' => $userId,
                    'source' => HorimeterReading::SOURCE_MOBILE_SYNC,
                    'reset_confirmed' => $payload['reset_confirmed'] ?? false,
                    'photo_path' => $photoPath,
                ]);

                $attributes = [
                    'tenant_id' => $tenantId,
                    'asset_id' => $payload['asset_id'],
                    'recorded_by' => $userId,
                    'horimeter_reading_id' => $reading->id,
                    'reading' => $payload['reading'],
                    'photo_path' => $photoPath,
                    'recorded_at' => $payload['recorded_at'],
                    'latitude' => $payload['latitude'] ?? null,
                    'longitude' => $payload['longitude'] ?? null,
                    'reset_confirmed' => $payload['reset_confirmed'] ?? false,
                    'sync_status' => EquipmentHourMeter::STATUS_SYNCED,
                    'synced_at' => now(),
                    'sync_error' => null,
                ];

                if ($existing) {
                    $existing->update($attributes);

                    return $existing;
                }

                $attributes['client_uuid'] = $clientUuid;

                return EquipmentHourMeter::create($attributes);
            });

            return $this->result($clientUuid, 'synced', $record->id);
        } catch (ValidationException $e) {
            if ($photoPath) {
                $this->writer->photoDisk()->delete($photoPath);
            }

            $this->recordFailure($tenantId, $userId, $payload, $clientUuid, $existing, implode(' ', $e->validator->errors()->all()));

            return $this->result($clientUuid, 'failed', null, implode(' ', $e->validator->errors()->all()));
        } catch (Throwable $e) {
            if ($photoPath) {
                $this->writer->photoDisk()->delete($photoPath);
            }

            $this->recordFailure($tenantId, $userId, $payload, $clientUuid, $existing, $e->getMessage());

            report($e);

            return $this->result($clientUuid, 'failed', null, 'Erro interno ao sincronizar este apontamento.');
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function recordFailure(
        string $tenantId,
        string $userId,
        array $payload,
        string $clientUuid,
        ?EquipmentHourMeter $existing,
        string $error
    ): void {
        $attributes = [
            'tenant_id' => $tenantId,
            'asset_id' => $payload['asset_id'],
            'recorded_by' => $userId,
            'reading' => $payload['reading'],
            'recorded_at' => $payload['recorded_at'],
            'latitude' => $payload['latitude'] ?? null,
            'longitude' => $payload['longitude'] ?? null,
            'reset_confirmed' => $payload['reset_confirmed'] ?? false,
            'sync_status' => EquipmentHourMeter::STATUS_FAILED,
            'sync_error' => Str::limit($error, 2000),
        ];

        if ($existing) {
            $existing->update($attributes);

            return;
        }

        $attributes['client_uuid'] = $clientUuid;

        EquipmentHourMeter::create($attributes);
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
