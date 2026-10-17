<?php

namespace App\Console\Commands;

use App\Support\Pacotes;
use Illuminate\Console\Command;

class CriarPlanosDeVitrine extends Command
{
    protected $signature = 'pacotes:vitrine {--pacote= : Código de um pacote (padrão: todos)} {--sincronizar-modulos : Reaplica os módulos do pacote em planos de vitrine já existentes}';

    protected $description = 'Cria o plano de vitrine de cada pacote (o que /assinar/{pacote} vende). Preço e "à venda" são cadastrados na Central; o plano nasce inativo e sem preço.';

    public function handle(): int
    {
        $codigos = $this->option('pacote') ? [(string) $this->option('pacote')] : array_keys(Pacotes::opcoes());

        $linhas = [];
        foreach ($codigos as $codigo) {
            if (! array_key_exists((string) Pacotes::normalizar($codigo), Pacotes::todos())) {
                $this->error("Pacote desconhecido: {$codigo}");

                return self::FAILURE;
            }

            $plano = Pacotes::planoDeVitrine($codigo, (bool) $this->option('sincronizar-modulos'));
            $aVenda = $plano->is_active && $plano->price > 0;
            $linhas[] = [$plano->pacote, $plano->name, $aVenda ? 'à venda' : 'FORA DE VENDA (defina preço e ative na Central)', $plano->price > 0 ? $plano->price : '-', route('checkout.pacote', ['pacote' => $plano->pacote])];
        }

        $this->table(['Pacote', 'Plano', 'Situação', 'Preço', 'Link de assinatura'], $linhas);

        return self::SUCCESS;
    }
}
