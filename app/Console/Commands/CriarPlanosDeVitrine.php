<?php

namespace App\Console\Commands;

use App\Models\Plan;
use App\Support\Pacotes;
use Illuminate\Console\Command;

class CriarPlanosDeVitrine extends Command
{
    protected $signature = 'pacotes:vitrine {--pacote= : Código de um pacote (padrão: todos)}';

    protected $description = 'Cria/atualiza o plano de vitrine de cada pacote (o que o checkout /assinar vende). Sem preço no config, o plano fica inativo.';

    public function handle(): int
    {
        $codigos = $this->option('pacote') ? [(string) $this->option('pacote')] : array_keys(Pacotes::opcoes());

        $linhas = [];
        foreach ($codigos as $codigo) {
            if (! array_key_exists(Pacotes::normalizar($codigo), Pacotes::todos())) {
                $this->error("Pacote desconhecido: {$codigo}");

                return self::FAILURE;
            }

            /** @var Plan $plano */
            $plano = Pacotes::planoDeVitrine($codigo);
            $linhas[] = [$plano->pacote, $plano->name, $plano->is_active ? 'ativo' : 'INATIVO (sem preço)', $plano->price, route('checkout.create', ['plano' => $plano->id])];
        }

        $this->table(['Pacote', 'Plano', 'Situação', 'Preço', 'Link de assinatura'], $linhas);

        return self::SUCCESS;
    }
}
