<?php

namespace App\Domain\Intelligence\Suprimentos;

use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Color;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Options;
use OpenSpout\Writer\XLSX\Writer;

/**
 * Modelo de importação de SUPRIMENTOS (.xlsx), diferente do modelo geral do Intelligence: abas Dados, Inventário, Compras e
 * Movimentações (as três últimas aceitam várias linhas) e Instruções. As colunas vêm de Catalogo; o importador lê as mesmas.
 * Linhas de exemplo começam com "(exemplo)" no 1º campo e são ignoradas na importação.
 */
class ModeloSuprimentos
{
    public const ARQUIVO = 'modelo-suprimentos-intelligence.xlsx';

    public static function escrever(string $destino): void
    {
        $options = new Options;
        $options->setColumnWidth(24, ...range(1, 10));
        $writer = new Writer($options);
        $writer->openToFile($destino);

        $cab = (new Style)->setFontBold()->setFontColor(Color::WHITE)->setBackgroundColor('1F3A5F');
        $nota = (new Style)->setFontItalic()->setFontColor(Color::rgb(90, 90, 90));

        $linhaTitulos = fn (array $campos) => new Row(array_map(
            fn ($c) => Cell::fromValue($c['title'].($c['required'] ? ' *' : ''), $cab),
            $campos
        ));

        // Dados: uma linha de valores logo abaixo do cabeçalho.
        $writer->getCurrentSheet()->setName(Catalogo::ABA_DADOS);
        $writer->addRow($linhaTitulos(Catalogo::dados()));
        $writer->addRow(Row::fromValues(array_map(fn ($c) => $c['exemplo'], Catalogo::dados())));

        foreach ([
            Catalogo::ABA_INVENTARIO => Catalogo::inventario(),
            Catalogo::ABA_COMPRAS => Catalogo::compras(),
            Catalogo::ABA_MOVIMENTOS => Catalogo::movimentos(),
        ] as $aba => $campos) {
            $writer->addNewSheetAndMakeItCurrent()->setName($aba);
            $writer->addRow($linhaTitulos($campos));
            $exemplo = array_map(fn ($c) => $c['exemplo'], $campos);
            $exemplo[0] = '(exemplo) '.$exemplo[0];
            $writer->addRow(Row::fromValues($exemplo, $nota));
        }

        $writer->addNewSheetAndMakeItCurrent()->setName(Catalogo::ABA_INSTRUCOES);
        foreach (self::instrucoes() as $linha) {
            $writer->addRow(Row::fromValues($linha));
        }
        $writer->close();
    }

    /** @return list<list<string>> */
    private static function instrucoes(): array
    {
        $l = [
            ['Modelo de importação de Suprimentos, Oravel Intelligence'],
            [''],
            ['Como preencher'],
            ['1. Aba Dados: a data da conferência do inventário (e, se quiser, o período de análise em dias).'],
            ['2. Aba Inventário (obrigatória): uma linha por item que você tem em estoque, com a categoria de cada um.'],
            ['3. Aba Compras (opcional): os pedidos de compra recentes, com o prazo prometido e a data em que chegaram.'],
            ['4. Aba Movimentações (opcional): as entradas e saídas, dizendo para onde cada saída foi.'],
            ['5. Apague a linha de exemplo (a que começa com "(exemplo)") ou deixe: ela é ignorada.'],
            ['Campos com * são obrigatórios. Datas no formato dd/mm/aaaa. Valores com vírgula ou ponto.'],
            ['Quanto mais abas preencher, mais indicadores o sistema calcula. Só o Inventário é obrigatório.'],
            [''],
            ['Categorias aceitas (aba Inventário): '.implode(', ', Catalogo::categorias()).'. Outra categoria entra como "'.config('intelligence.suprimentos.categoria_padrao').'".'],
            [''],
        ];
        foreach ([Catalogo::ABA_DADOS => Catalogo::dados(), Catalogo::ABA_INVENTARIO => Catalogo::inventario(), Catalogo::ABA_COMPRAS => Catalogo::compras(), Catalogo::ABA_MOVIMENTOS => Catalogo::movimentos()] as $aba => $campos) {
            $l[] = ['Aba '.$aba];
            foreach ($campos as $c) {
                $l[] = ['  '.$c['title'].($c['required'] ? ' *' : '').': '.$c['help']];
            }
            $l[] = [''];
        }

        return $l;
    }
}
