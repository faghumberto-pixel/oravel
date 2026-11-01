<?php

namespace App\Console\Commands;

use App\Models\Prospect;
use Illuminate\Console\Command;

/**
 * Importa uma lista de prospects (JSON exportado do artefato "Lista Oravel SP": n,l,c,s,k,a,t,tel,em,p,pr,cnpj,sc,rg,nv).
 * Idempotente por CNPJ: nao sobrescreve status/observacoes de quem ja existe. Dados de contato ficam fora do git.
 */
class ImportarProspects extends Command
{
    protected $signature = 'prospects:importar {arquivo : JSON com a lista} {--lote= : JSON com [{"id": "cnpj"}] do lote a marcar} {--lote-numero=1} {--origem=Lista Oravel SP}';

    protected $description = 'Importa prospects de um JSON (idempotente por CNPJ)';

    public function handle(): int
    {
        $lista = json_decode((string) @file_get_contents($this->argument('arquivo')), true);
        if (! is_array($lista)) {
            $this->error('Arquivo inválido.');

            return self::FAILURE;
        }

        $novos = $atualizados = 0;
        foreach ($lista as $x) {
            if (blank($x['cnpj'] ?? null)) {
                continue;
            }
            $dados = [
                'nome' => $x['n'], 'razao_social' => $x['l'] ?? null, 'cidade' => $x['c'] ?? null, 'porte' => $x['s'] ?? null,
                'capital' => (int) ($x['k'] ?? 0), 'ano_abertura' => $x['a'] ?? null, 'atividade' => $x['t'] ?? null,
                'telefone' => $x['tel'] ?? null, 'email' => ($x['em'] ?? '') ?: null, 'socio' => ($x['p'] ?? '') ?: null,
                'socio_cargo' => ($x['pr'] ?? '') ?: null, 'nota' => (int) ($x['sc'] ?? 0),
                'regiao_campinas' => (bool) ($x['rg'] ?? false), 'empresa_nova' => (bool) ($x['nv'] ?? false),
            ];
            $p = Prospect::firstWhere('cnpj', $x['cnpj']);
            if ($p) {
                $p->update($dados);
                $atualizados++;
            } else {
                Prospect::create($dados + ['cnpj' => $x['cnpj'], 'origem' => $this->option('origem')]);
                $novos++;
            }
        }

        if ($this->option('lote')) {
            $ids = collect(json_decode((string) @file_get_contents($this->option('lote')), true))->pluck('id')->filter()->all();
            $n = Prospect::whereIn('cnpj', $ids)->update(['lote' => (int) $this->option('lote-numero')]);
            $this->info("Lote {$this->option('lote-numero')}: {$n} empresas marcadas.");
        }

        $this->info("Novos: {$novos} · atualizados: {$atualizados}.");

        return self::SUCCESS;
    }
}
