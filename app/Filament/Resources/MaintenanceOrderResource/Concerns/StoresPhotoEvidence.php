<?php

namespace App\Filament\Resources\MaintenanceOrderResource\Concerns;

use App\Models\EquipmentDamage;
use App\Models\MaintenanceOrder;
use Illuminate\Support\Facades\Storage;

trait StoresPhotoEvidence
{
    protected array $pendingPhotoEvidences = [];

    protected array $photoEvidenceFields = [
        'photo_before' => 'antes',
        'photo_after' => 'depois',
    ];

    protected function extractPhotoEvidences(array &$data): void
    {
        // Os dois slots fixos (antes/depois) continuam com no maximo 1 registro
        // cada (updateOrCreate), igual ja funcionava.
        foreach ($this->photoEvidenceFields as $field => $evidenceType) {
            $payload = $data[$field] ?? null;
            unset($data[$field]);

            if (! empty($payload['image'] ?? null)) {
                $this->pendingPhotoEvidences[] = [
                    'evidence_type' => $evidenceType,
                    'singleton' => true,
                    'category' => null,
                    'severity' => 'ok',
                    'observation' => null,
                    'image' => $payload['image'],
                    'latitude' => $payload['latitude'] ?? null,
                    'longitude' => $payload['longitude'] ?? null,
                    'captured_at' => $payload['captured_at'] ?? null,
                ];
            }
        }

        // Evidencias adicionais (repeater): trilha de auditoria que so cresce --
        // uma vez salvas, nao sao recarregadas de volta pro formulario pra edicao
        // (ver fillPhotoEvidences), entao aqui sempre cria um Attachment novo.
        $extras = $data['extra_evidences'] ?? [];
        unset($data['extra_evidences']);

        foreach ($extras as $item) {
            $photo = $item['photo'] ?? null;

            if (empty($photo['image'] ?? null)) {
                continue;
            }

            $this->pendingPhotoEvidences[] = [
                'evidence_type' => 'adicional',
                'singleton' => false,
                'category' => $item['category'] ?? null,
                'severity' => $item['severity'] ?? 'ok',
                'observation' => $item['observation'] ?? null,
                'image' => $photo['image'],
                'latitude' => $photo['latitude'] ?? null,
                'longitude' => $photo['longitude'] ?? null,
                'captured_at' => $photo['captured_at'] ?? null,
                'damage_severity' => $item['damage_severity'] ?? null,
                'damage_type' => $item['damage_type'] ?? null,
            ];
        }
    }

    protected function persistPhotoEvidences(MaintenanceOrder $order): void
    {
        foreach ($this->pendingPhotoEvidences as $item) {
            $imageData = $item['image'];

            if (! preg_match('/^data:(image\/\w+);base64,/', $imageData, $matches)) {
                // Já é uma URL de uma evidência existente (não foi retirada foto nova).
                continue;
            }

            $mimeType = $matches[1];
            $extension = str($mimeType)->after('/')->toString();
            $binary = base64_decode(substr($imageData, strpos($imageData, ',') + 1));

            $fileName = "{$item['evidence_type']}-".now()->format('YmdHis').'-'.substr(md5($imageData), 0, 8).'.'.$extension;
            $path = "attachments/os_{$order->id}/{$item['evidence_type']}/{$fileName}";

            Storage::disk('public')->put($path, $binary);

            $attributes = [
                'file_path' => $path,
                'file_name' => $fileName,
                'mime_type' => $mimeType,
                'category' => $item['category'],
                'severity' => $item['severity'],
                'observation' => $item['observation'],
                'latitude' => $item['latitude'],
                'longitude' => $item['longitude'],
                'captured_at' => $item['captured_at'] ?? now(),
            ];

            if ($item['singleton']) {
                $order->evidences()->updateOrCreate(['evidence_type' => $item['evidence_type']], $attributes);
            } else {
                $order->evidences()->create($attributes + ['evidence_type' => $item['evidence_type']]);

                // "Avaria" OU "Mau Uso" (locadoras de construcao civil, evidencia
                // pra cobranca/contestacao com o cliente da obra) numa evidencia
                // adicional da propria OS agora cria um EquipmentDamage de verdade
                // (nao so' marca a foto) -- unico jeito de registrar avaria antes
                // disso era pelo checklist mobile de mobilizacao/desmobilizacao, que
                // nao cobre atendimento externo corretivo sem nenhuma movimentacao em
                // andamento. Sem etapa de assinatura do cliente aqui (registro interno
                // da OS, nao despacho presencial) -- nasce direto em aguardando_supervisor.
                if (in_array($item['severity'], ['avaria', 'mau_uso'], true)) {
                    $isMauUso = $item['severity'] === 'mau_uso';

                    $damage = EquipmentDamage::create([
                        'tenant_id' => $order->tenant_id,
                        'maintenance_order_id' => $order->id,
                        'asset_id' => $order->asset_id,
                        'reported_by_user_id' => auth()->id(),
                        'severity' => $item['damage_severity'] ?? EquipmentDamage::SEVERITY_MODERADA,
                        'damage_type' => $item['damage_type'] ?? EquipmentDamage::DAMAGE_TYPE_OUTRO,
                        'description' => $item['observation'] ?: (
                            $isMauUso
                                ? "Mau uso detectado na OS #{$order->os_number}"
                                : "Avaria detectada na OS #{$order->os_number}"
                        ),
                        'status' => EquipmentDamage::STATUS_AGUARDANDO_SUPERVISOR,
                    ]);

                    $damage->addMediaFromDisk($path, 'public')->toMediaCollection('photos');
                }
            }
        }

        $this->pendingPhotoEvidences = [];
    }

    protected function fillPhotoEvidences(MaintenanceOrder $order, array $data): array
    {
        foreach ($this->photoEvidenceFields as $field => $evidenceType) {
            $evidence = $order->evidences()
                ->where('evidence_type', $evidenceType)
                ->latest('captured_at')
                ->first();

            if ($evidence) {
                $data[$field] = [
                    'image' => Storage::disk('public')->url($evidence->file_path),
                    'latitude' => $evidence->latitude,
                    'longitude' => $evidence->longitude,
                    'captured_at' => optional($evidence->captured_at)->format('Y-m-d H:i:s'),
                ];
            }
        }

        // extra_evidences (repeater) não é recarregado de volta -- é uma trilha de
        // auditoria somente-adição; as evidências já salvas ficam visíveis no
        // Dossiê Operacional, não editáveis por aqui.
        return $data;
    }
}
