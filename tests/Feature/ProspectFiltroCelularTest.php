<?php

namespace Tests\Feature;

use App\Models\Prospect;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/** Filtro "Só com celular / WhatsApp" do grid de Prospects. Banco: sqlite em memória, nunca o DEV. */
class ProspectFiltroCelularTest extends TestCase
{
    public function test_filtra_so_quem_tem_celular(): void
    {
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        app('db')->purge();
        Schema::create('prospects', function ($t) {
            $t->increments('id');
            $t->string('nome');
            $t->string('telefone')->nullable();
        });
        foreach ([['A cel', '(19) 99933-2615'], ['B fixo', '(19) 3333-4444'], ['C sem', null], ['D cel SP', '(11) 98888-7777'], ['E fixo 9x', '(19) 2999-0000']] as [$n, $tel]) {
            Prospect::query()->insert(['nome' => $n, 'telefone' => $tel]);
        }

        $nomes = Prospect::query()->comCelular()->orderBy('nome')->pluck('nome')->all();

        $this->assertSame(['A cel', 'D cel SP'], $nomes);
    }
}
