<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\Catalogo\CatalogoInstalador;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CatalogoInstalar extends Command
{
    protected $signature = 'catalogo:instalar {tenant : id (uuid) do tenant} {--dry-run : mostra o que seria criado e desfaz}';

    protected $description = 'G1: instala o catalogo padrao de grupos de equipamento no tenant (idempotente, nunca sobrescreve ajustes do tenant)';

    public function handle(CatalogoInstalador $instalador): int
    {
        $tenant = Tenant::query()->find($this->argument('tenant'));

        if (! $tenant) {
            $this->error('Tenant nao encontrado.');

            return self::FAILURE;
        }

        $dry = (bool) $this->option('dry-run');

        DB::beginTransaction();

        try {
            $resultado = $instalador->instalar($tenant);
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $dry ? DB::rollBack() : DB::commit();

        $this->info(($dry ? '[dry-run, nada gravado] ' : '')."Tenant {$tenant->name}");
        $this->line('Criados:   '.json_encode($resultado['criados'], JSON_UNESCAPED_UNICODE));
        $this->line('Ignorados: '.json_encode($resultado['ignorados'], JSON_UNESCAPED_UNICODE));

        foreach ($resultado['conflitos'] as $slug) {
            $this->warn("Conflito: o tenant ja tem uma categoria com slug '{$slug}' que nao veio do template; nao foi adotada nem duplicada.");
        }

        return self::SUCCESS;
    }
}
