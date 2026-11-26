<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Completa a "Gestão à Vista" de uma empresa de demonstração que já tem ativos e ordens de serviço: causas de falha nas OS corretivas,
 * paradas fechadas por quebra/corretiva (tempo de parada e MTBF) e leituras de horímetro. Só ADICIONA (e preenche categoria de falha onde está
 * vazia): anota o que mexeu em storage/app/demo-gav-ids.json e --remover desfaz exatamente isso. Insere direto nas tabelas (sem observers).
 * Sem Faker: roda em PROD.
 */
class DemoPopularGestaoAVista extends Command
{
    protected $signature = 'demo:popular-gestao-a-vista {--tenant=Locadora Demo : nome da empresa} {--remover : Desfaz o que este comando fez}';

    protected $description = 'Completa a Gestão à Vista da empresa demo (causas de falha, paradas e horímetro). Só adiciona; reversível com --remover';

    /** Distribuição das causas de falha (categoria => peso). */
    private const CAUSAS = ['hidraulico' => 30, 'eletrico' => 24, 'pneu_esteira' => 18, 'motor' => 14, 'estrutural' => 9, 'outro' => 5];

    private string $tid;

    public function handle(): int
    {
        $tenant = Tenant::withoutGlobalScopes()->where('name', $this->option('tenant'))->first();
        if (! $tenant) {
            $this->error('Empresa "'.$this->option('tenant').'" não encontrada.');

            return self::FAILURE;
        }
        $this->tid = (string) $tenant->id;

        if ($this->option('remover')) {
            return $this->remover();
        }
        if (File::exists($this->arquivo())) {
            $this->warn('Os dados já foram criados. Use --remover antes para recriar.');

            return self::FAILURE;
        }

        $ativos = DB::table('maintenance_orders')->where('tenant_id', $this->tid)->where('maintenance_type', 'Corretiva')->whereNotNull('asset_id')
            ->select('asset_id')->distinct()->pluck('asset_id')->take(14)->all();
        if (! $ativos) {
            $this->error('Esta empresa não tem ordens corretivas com ativo: nada a completar.');

            return self::FAILURE;
        }

        mt_srand(2026);
        $feito = ['asset_downtime_events' => [], 'horimeter_readings' => [], 'maintenance_orders' => []];

        DB::transaction(function () use ($ativos, &$feito) {
            $feito['maintenance_orders'] = $this->causasDeFalha();
            foreach ($ativos as $i => $assetId) {
                $this->horimetro($assetId, $i, $feito);
                $this->paradas($assetId, $feito);
            }
        });

        File::put($this->arquivo(), json_encode($feito));
        $this->info('Gestão à Vista completada: '.count($feito['maintenance_orders']).' OS com causa de falha, '.count($feito['asset_downtime_events']).' paradas e '.count($feito['horimeter_readings']).' leituras de horímetro.');
        $this->line('Para desfazer: php artisan demo:popular-gestao-a-vista --remover');

        return self::SUCCESS;
    }

    private function arquivo(): string
    {
        return storage_path('app/demo-gav-ids.json');
    }

    private function id(): string
    {
        return (string) Str::uuid();
    }

    /** Preenche a categoria de falha das OS corretivas dos últimos 12 meses que estão sem (nunca troca uma já preenchida). @return list<string> ids alterados */
    private function causasDeFalha(): array
    {
        $ids = DB::table('maintenance_orders')->where('tenant_id', $this->tid)->where('maintenance_type', 'Corretiva')->whereNull('failure_category')
            ->where('created_at', '>=', now()->subMonths(12))->orderBy('created_at')->pluck('id')->all();

        $pesos = [];
        foreach (self::CAUSAS as $categoria => $peso) {
            $pesos = array_merge($pesos, array_fill(0, $peso, $categoria));
        }
        foreach ($ids as $i => $id) {
            DB::table('maintenance_orders')->where('id', $id)->where('tenant_id', $this->tid)->update(['failure_category' => $pesos[($i * 7 + mt_rand(0, 9)) % count($pesos)]]);
        }

        return $ids;
    }

    /** Duas leituras (há 7 meses e hoje), só se o ativo ainda não tem nenhuma: é o que o MTBF precisa. */
    private function horimetro(string $assetId, int $i, array &$feito): void
    {
        if (DB::table('horimeter_readings')->where('tenant_id', $this->tid)->where('asset_id', $assetId)->exists()) {
            return;
        }
        $inicio = 1200 + $i * 85;
        foreach ([[now()->subMonths(7), $inicio], [now()->subDay(), $inicio + 700 + mt_rand(0, 900)]] as [$quando, $leitura]) {
            $id = $this->id();
            DB::table('horimeter_readings')->insert([
                'id' => $id, 'tenant_id' => $this->tid, 'asset_id' => $assetId, 'reading' => $leitura, 'recorded_at' => $quando, 'source' => 'manual',
                'reset_confirmed' => false, 'notes' => 'Leitura de demonstração', 'created_at' => now(), 'updated_at' => now(),
            ]);
            $feito['horimeter_readings'][] = $id;
        }
    }

    /** De 2 a 4 paradas fechadas por ativo nos últimos 6 meses (quebra ou corretiva), de 3 a 40 horas cada. */
    private function paradas(string $assetId, array &$feito): void
    {
        $os = DB::table('maintenance_orders')->where('tenant_id', $this->tid)->where('asset_id', $assetId)->where('maintenance_type', 'Corretiva')->pluck('id')->all();
        $quantas = mt_rand(2, 4);
        for ($k = 0; $k < $quantas; $k++) {
            $inicio = now()->subDays(mt_rand(5, 175))->setTime(mt_rand(6, 15), 0);
            $id = $this->id();
            $linha = [
                'id' => $id, 'tenant_id' => $this->tid, 'asset_id' => $assetId, 'started_at' => $inicio, 'ended_at' => $inicio->copy()->addHours(mt_rand(3, 40)),
                'reason' => $k % 2 === 0 ? 'quebra' : 'manutencao_corretiva', 'maintenance_order_id' => $os ? $os[array_rand($os)] : null,
                'notes' => 'Parada de demonstração', 'created_at' => now(), 'updated_at' => now(),
            ];
            $colunas = Schema::getColumnListing('asset_downtime_events');
            DB::table('asset_downtime_events')->insert(array_intersect_key($linha, array_flip($colunas)));
            $feito['asset_downtime_events'][] = $id;
        }
    }

    private function remover(): int
    {
        if (! File::exists($this->arquivo())) {
            $this->warn('Não há lista de ids: nada foi feito por este comando (ou já foi desfeito).');

            return self::SUCCESS;
        }

        $feito = json_decode(File::get($this->arquivo()), true) ?: [];
        DB::transaction(function () use ($feito) {
            foreach (['asset_downtime_events', 'horimeter_readings'] as $tabela) {
                if (! empty($feito[$tabela])) {
                    DB::table($tabela)->where('tenant_id', $this->tid)->whereIn('id', $feito[$tabela])->delete();
                }
            }
            if (! empty($feito['maintenance_orders'])) {
                DB::table('maintenance_orders')->where('tenant_id', $this->tid)->whereIn('id', $feito['maintenance_orders'])->update(['failure_category' => null]);
            }
        });
        File::delete($this->arquivo());
        $this->info('Desfeito: '.collect($feito)->map(fn ($ids, $t) => count($ids).' '.$t)->implode(', ').'.');

        return self::SUCCESS;
    }
}
