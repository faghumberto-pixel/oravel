<?php

/**
 * Gera docs/treinamento/onda-1/planilha-exercicio-ativos.xlsx: a planilha do exercício de importação de ativos
 * (Onda 1.1 do roadmap de treinamento). 10 linhas, com 2 erros propositais para o aluno treinar a validação:
 *   - linha 7 (TRE-0005) sem Nome/Modelo (obrigatório);
 *   - linha 9 (TRE-0002) com o Nº Patrimônio repetido na planilha.
 * Usa as mesmas colunas do modelo de importação (App\Support\AssetImport\AssetImportColumns), então acompanha o sistema.
 * Uso: php scripts/treinamento/gerar-planilha-ativos.php
 */

use App\Support\AssetImport\AssetImportColumns;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;

require __DIR__.'/../../vendor/autoload.php';

$linhas = [
    ['Nº Patrimônio' => 'TRE-0001', 'Nome/Modelo' => 'Empilhadeira Elétrica 2,5t', 'Fabricante' => 'Hyster', 'Categoria e Tipo' => 'Empilhadeira', 'Capacidade' => '2,5', 'Unidade da Capacidade' => 'toneladas', 'Status Operacional' => 'Disponível', 'Propulsão (Empilhadeira)' => 'Elétrica', 'Voltagem da Bateria' => '48V', 'Capacidade de Carga (kg)' => '2500'],
    ['Nº Patrimônio' => 'TRE-0002', 'Nome/Modelo' => 'Gerador Cummins 500 kVA', 'Fabricante' => 'Cummins', 'Categoria e Tipo' => 'Gerador', 'Capacidade' => '500', 'Unidade da Capacidade' => 'kVA', 'Status Operacional' => 'Disponível', 'Tipo de Tensão' => 'Trifásico', 'Voltagem' => '220V / 380V'],
    ['Nº Patrimônio' => 'TRE-0003', 'Nome/Modelo' => 'Plataforma Tesoura 10m', 'Fabricante' => 'Genie', 'Categoria e Tipo' => 'Plataforma Elevatória Tesoura', 'Status Operacional' => 'Disponível', 'Criticidade' => 'Média'],
    ['Nº Patrimônio' => 'TRE-0004', 'Nome/Modelo' => 'Guindaste Hidráulico 40t', 'Fabricante' => 'Liebherr', 'Categoria e Tipo' => 'Guindaste', 'Capacidade' => '40', 'Unidade da Capacidade' => 'toneladas', 'Status Operacional' => 'Locado', 'Criticidade' => 'Alta'],
    // ERRO 1: sem Nome/Modelo (obrigatório).
    ['Nº Patrimônio' => 'TRE-0005', 'Nome/Modelo' => '', 'Fabricante' => 'Atlas Copco', 'Categoria e Tipo' => 'Compressor', 'Capacidade' => '750', 'Unidade da Capacidade' => 'PCM'],
    ['Nº Patrimônio' => 'TRE-0006', 'Nome/Modelo' => 'Compressor 750 PCM', 'Fabricante' => 'Atlas Copco', 'Categoria e Tipo' => 'Compressor', 'Capacidade' => '750', 'Unidade da Capacidade' => 'PCM', 'Status Operacional' => 'Disponível'],
    // ERRO 2: Nº Patrimônio repetido na planilha (TRE-0002 já aparece na linha 4).
    ['Nº Patrimônio' => 'TRE-0002', 'Nome/Modelo' => 'Gerador Stemac 180 kVA', 'Fabricante' => 'Stemac', 'Categoria e Tipo' => 'Gerador', 'Capacidade' => '180', 'Unidade da Capacidade' => 'kVA'],
    ['Nº Patrimônio' => 'TRE-0008', 'Nome/Modelo' => 'Caminhão Munck 12t', 'Fabricante' => 'Mercedes-Benz', 'Categoria e Tipo' => 'Caminhão Munck', 'É veículo?' => 'Sim', 'Odômetro Atual (km)' => '85400', 'Status Operacional' => 'Disponível'],
    ['Nº Patrimônio' => 'TRE-0009', 'Nome/Modelo' => 'Retroescavadeira 4x4', 'Fabricante' => 'Case', 'Categoria e Tipo' => 'Retroescavadeira', 'Horímetro Atual' => '3200', 'Status Operacional' => 'Em Manutenção'],
    ['Nº Patrimônio' => 'TRE-0010', 'Nome/Modelo' => 'Torre de Iluminação 4 refletores', 'Fabricante' => 'Pramac', 'Categoria e Tipo' => 'Torre de Iluminação', 'Status Operacional' => 'Disponível'],
];

$cols = AssetImportColumns::all();
$titulos = array_column($cols, 'title');
$destino = __DIR__.'/../../docs/treinamento/onda-1/planilha-exercicio-ativos.xlsx';

$w = new Writer;
$w->openToFile($destino);
$w->getCurrentSheet()->setName(AssetImportColumns::SHEET);
$w->addRow(Row::fromValues(array_column($cols, 'group')));
$w->addRow(Row::fromValues(array_map(fn (array $c) => $c['title'].($c['required'] ? ' *' : ''), $cols)));

foreach ($linhas as $linha) {
    $w->addRow(Row::fromValues(array_map(fn (string $t) => $linha[$t] ?? null, $titulos)));
}

$w->close();

echo "Gerada: {$destino}\n";
