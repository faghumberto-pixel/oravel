<?php

namespace App\Services;

use App\Models\MaintenanceOrder;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Fluxo "folha impressa pelo sistema": a O.S. e' criada na empresa, impressa ja com cliente, equipamento e problema, o mecanico
 * completa a mao e devolve; a IA le a foto e ATUALIZA a mesma O.S. (servico, horimetro, horas, datas, pecas, observacoes).
 * Nunca cria outra O.S., nunca troca cliente nem equipamento e so' grava depois que a pessoa revisa e confirma.
 */
class OsFolhaAtualizacaoService
{
    public const MARCA = 'Folha lida pela IA em ';

    public function __construct(private OsPhotoOcrService $ocr) {}

    /**
     * Le a foto e monta a proposta de atualizacao (sem gravar nada).
     *
     * @return array{ok: bool, preparo: ?array<string, mixed>, error: ?string}
     */
    public function preparar(MaintenanceOrder $os, string $fotoDataUrl): array
    {
        $r = $this->ocr->extract($fotoDataUrl);
        if (! $r['ok']) {
            return ['ok' => false, 'preparo' => null, 'error' => $r['error']];
        }

        $preparo = $this->ocr->resolveUpdate($r['data'], $os->loadMissing(['asset', 'technician']));
        if (! $preparo['campos'] && ! $preparo['pecas']) {
            return ['ok' => false, 'preparo' => null, 'error' => 'Não consegui ler nada preenchido à mão nesta folha. Tire a foto de novo, com a folha inteira, reta e bem iluminada.'];
        }

        return ['ok' => true, 'preparo' => $preparo, 'error' => null];
    }

    /** Data/hora em que uma folha ja foi aplicada nesta O.S. (para evitar somar as pecas duas vezes sem querer). */
    public function jaAplicadaEm(MaintenanceOrder $os): ?string
    {
        return preg_match('/'.preg_quote(self::MARCA, '/').'(\d{2}\/\d{2}\/\d{4} \d{2}:\d{2})/', (string) $os->technical_notes, $m) ? $m[1] : null;
    }

    /**
     * Grava na O.S. o que a pessoa aprovou.
     *
     * @param  array<string, mixed>  $preparo  retorno de preparar()['preparo']
     * @param  array{campos?: list<string>, pecas?: bool, status?: ?string}  $escolhas
     * @return array{campos: list<string>, pecas: int, foto: bool}
     */
    public function aplicar(MaintenanceOrder $os, array $preparo, array $escolhas, ?string $fotoDataUrl, ?User $usuario): array
    {
        $aprovados = array_intersect_key($preparo['campos'], array_flip($escolhas['campos'] ?? []));
        // horas, tempo e mao de obra andam juntos: nunca aplicar um sem os outros
        if (isset($aprovados['hours_spent'])) {
            foreach (['total_time_seconds', 'labor_cost'] as $k) {
                if (array_key_exists($k, $preparo['campos'])) {
                    $aprovados[$k] = $preparo['campos'][$k];
                }
            }
        }

        return DB::transaction(function () use ($os, $preparo, $escolhas, $fotoDataUrl, $usuario, $aprovados) {
            $nota = self::MARCA.now()->format('d/m/Y H:i').($usuario ? ' (confirmada por '.$usuario->name.')' : '');
            $notas = $aprovados['technical_notes'] ?? (string) $os->technical_notes;
            $aprovados['technical_notes'] = trim($notas."\n".$nota);

            if (! empty($escolhas['status']) && in_array($escolhas['status'], ['Em Andamento', 'Concluída'], true)) {
                $aprovados['status'] = $escolhas['status'];
                if ($escolhas['status'] === 'Concluída' && empty($aprovados['finished_at']) && ! $os->finished_at) {
                    $aprovados['finished_at'] = now();
                }
            }

            $os->forceFill($aprovados)->save();

            $lancadas = 0;
            if (! empty($escolhas['pecas'])) {
                foreach ($preparo['pecas'] as $linha) {
                    // o observador baixa o estoque (quando ha material cadastrado) e recalcula o custo, como no lancamento normal
                    $os->materials()->create([
                        'material_id' => $linha['material_id'] ?? null,
                        'name' => $linha['material_id'] ? null : ($linha['name'] ?? null),
                        'quantity' => $linha['quantity'] ?? 1,
                        'unit_price' => $linha['unit_price'] ?? null,
                    ]);
                    $lancadas++;
                }
            }

            return ['campos' => array_keys($aprovados), 'pecas' => $lancadas, 'foto' => $this->guardarFoto($os, $fotoDataUrl)];
        });
    }

    /** A foto da folha fica anexada a O.S. como evidencia. Falha de armazenamento nao desfaz a atualizacao. */
    private function guardarFoto(MaintenanceOrder $os, ?string $fotoDataUrl): bool
    {
        if (! $fotoDataUrl) {
            return false;
        }

        try {
            $os->addMediaFromBase64($fotoDataUrl)->usingFileName('folha-'.preg_replace('/[^A-Za-z0-9-]/', '', (string) $os->os_number).'-'.now()->format('Ymd-His').'.jpg')->toMediaCollection('default');

            return true;
        } catch (\Throwable $e) {
            Log::warning('Nao foi possivel anexar a foto da folha de O.S.', ['os' => $os->id, 'erro' => $e->getMessage()]);

            return false;
        }
    }
}
