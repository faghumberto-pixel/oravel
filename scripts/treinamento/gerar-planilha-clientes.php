<?php

/**
 * Gera docs/treinamento/onda-1/planilha-exercicio-clientes.xlsx: a planilha do exercício de importação de clientes
 * (Onda 1.2 do roadmap de treinamento). 10 linhas, com 2 erros propositais para o aluno treinar a validação:
 *   - linha 7 sem Razão Social (obrigatória);
 *   - linha 9 com o mesmo CNPJ da linha 3 (cliente repetido na planilha).
 * Usa as mesmas colunas do modelo de importação (App\Support\ClientImport\ClientImportColumns), então acompanha o sistema.
 * Todos os dados são fictícios. Uso: php scripts/treinamento/gerar-planilha-clientes.php
 */

use App\Support\ClientImport\ClientImportColumns;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;

require __DIR__.'/../../vendor/autoload.php';

$linhas = [
    ['Razão Social' => 'Construtora Alfa Ltda', 'Nome Fantasia' => 'Alfa Construções', 'CNPJ' => '11.222.333/0001-81', 'Nicho' => 'Construção Civil', 'Logradouro e Nº' => 'Av. das Obras, 100', 'Cidade' => 'Campinas', 'UF' => 'SP', 'CEP' => '13010-000', 'Pessoa de Contato' => 'Maria Souza', 'E-mail Principal' => 'contato@alfaconstrutora.com.br', 'Telefone Comercial' => '(19) 3333-1001', 'Endereço Completo da Obra' => 'Rua do Canteiro, 55, Campinas/SP', 'Responsável na Obra' => 'Carlos Lima'],
    ['Razão Social' => 'Eventos Beta Produções ME', 'Nome Fantasia' => 'Beta Eventos', 'CNPJ' => '22.333.444/0001-92', 'Nicho' => 'Eventos', 'Logradouro e Nº' => 'Rua dos Palcos, 200', 'Cidade' => 'São Paulo', 'UF' => 'SP', 'CEP' => '01310-100', 'Pessoa de Contato' => 'João Pereira', 'E-mail Principal' => 'producao@betaeventos.com.br', 'Telefone Comercial' => '(11) 3333-2002'],
    ['Razão Social' => 'Indústria Gama S.A.', 'Nome Fantasia' => 'Gama Indústria', 'CNPJ' => '33.444.555/0001-03', 'Nicho' => 'Industrial / Hospitalar', 'Logradouro e Nº' => 'Rod. Industrial, km 12', 'Cidade' => 'Jundiaí', 'UF' => 'SP', 'CEP' => '13200-000', 'Pessoa de Contato' => 'Ana Ribeiro', 'E-mail Principal' => 'compras@gamaindustria.com.br', 'Telefone Comercial' => '(11) 3333-3003', 'E-mail Financeiro' => 'financeiro@gamaindustria.com.br'],
    ['Razão Social' => 'Locadora Delta Equipamentos Ltda', 'Nome Fantasia' => 'Delta Locações', 'CNPJ' => '44.555.666/0001-14', 'Nicho' => 'Locação de Equipamentos', 'Logradouro e Nº' => 'Av. do Comércio, 300', 'Cidade' => 'Sorocaba', 'UF' => 'SP', 'CEP' => '18010-000', 'Pessoa de Contato' => 'Pedro Alves', 'E-mail Principal' => 'contato@deltalocacoes.com.br', 'Telefone Comercial' => '(15) 3333-4004'],
    // ERRO 1: sem Razão Social (obrigatória).
    ['Razão Social' => '', 'Nome Fantasia' => 'Épsilon Obras', 'CNPJ' => '55.666.777/0001-25', 'Nicho' => 'Construção Civil', 'Cidade' => 'Santos', 'UF' => 'SP', 'E-mail Principal' => 'contato@epsilonobras.com.br'],
    ['Razão Social' => 'Zeta Engenharia Ltda', 'Nome Fantasia' => 'Zeta Engenharia', 'CNPJ' => '66.777.888/0001-36', 'Nicho' => 'Construção Civil', 'Logradouro e Nº' => 'Rua dos Engenheiros, 400', 'Cidade' => 'Santos', 'UF' => 'SP', 'CEP' => '11010-000', 'Pessoa de Contato' => 'Lúcia Martins', 'E-mail Principal' => 'obras@zetaengenharia.com.br', 'Telefone Comercial' => '(13) 3333-6006'],
    // ERRO 2: mesmo CNPJ da linha 3 (Construtora Alfa), repetido na planilha.
    ['Razão Social' => 'Eta Montagens Industriais Ltda', 'Nome Fantasia' => 'Eta Montagens', 'CNPJ' => '11.222.333/0001-81', 'Nicho' => 'Industrial / Hospitalar', 'Cidade' => 'Campinas', 'UF' => 'SP', 'E-mail Principal' => 'contato@etamontagens.com.br'],
    ['Razão Social' => 'Teta Transportes Ltda', 'Nome Fantasia' => 'Teta Transportes', 'CNPJ' => '77.888.999/0001-47', 'Nicho' => 'Outro', 'Logradouro e Nº' => 'Av. dos Caminhões, 500', 'Cidade' => 'Piracicaba', 'UF' => 'SP', 'CEP' => '13400-000', 'Pessoa de Contato' => 'Rogério Dias', 'E-mail Principal' => 'frota@tetatransportes.com.br', 'Telefone Comercial' => '(19) 3333-8008'],
    ['Razão Social' => 'Iota Hospitalar Ltda', 'Nome Fantasia' => 'Iota Hospitalar', 'CNPJ' => '88.999.000/0001-58', 'Nicho' => 'Industrial / Hospitalar', 'Logradouro e Nº' => 'Rua da Saúde, 600', 'Cidade' => 'Ribeirão Preto', 'UF' => 'SP', 'CEP' => '14010-000', 'Pessoa de Contato' => 'Dra. Helena Costa', 'E-mail Principal' => 'engenharia@iotahospitalar.com.br', 'Telefone Comercial' => '(16) 3333-9009'],
    ['Razão Social' => 'Kappa Shows e Eventos Ltda', 'Nome Fantasia' => 'Kappa Shows', 'CNPJ' => '99.000.111/0001-69', 'Nicho' => 'Eventos', 'Logradouro e Nº' => 'Rua da Música, 700', 'Cidade' => 'Campinas', 'UF' => 'SP', 'CEP' => '13015-000', 'Pessoa de Contato' => 'Bruno Faria', 'E-mail Principal' => 'palco@kappashows.com.br', 'Telefone Comercial' => '(19) 3333-1010'],
];

$cols = ClientImportColumns::all();
$titulos = array_column($cols, 'title');
$destino = __DIR__.'/../../docs/treinamento/onda-1/planilha-exercicio-clientes.xlsx';

$w = new Writer;
$w->openToFile($destino);
$w->getCurrentSheet()->setName(ClientImportColumns::SHEET);
$w->addRow(Row::fromValues(array_column($cols, 'group')));
$w->addRow(Row::fromValues(array_map(fn (array $c) => $c['title'].($c['required'] ? ' *' : ''), $cols)));

foreach ($linhas as $linha) {
    $w->addRow(Row::fromValues(array_map(fn (string $t) => $linha[$t] ?? null, $titulos)));
}

$w->close();

echo "Gerada: {$destino}\n";
