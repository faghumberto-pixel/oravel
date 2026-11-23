<?php

namespace App\Domain\Intelligence\Support;

use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\CellAlignment;
use OpenSpout\Common\Entity\Style\Color;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Options;
use OpenSpout\Writer\XLSX\Writer;

/**
 * Modelo de importação do Intelligence (.xlsx): aba "Dados" (a empresa no mês), aba "Equipamentos" (uma linha por equipamento,
 * opcional) e aba "Instruções". As colunas vêm de Catalogo (fonte única); o importador lê as mesmas.
 */
class ModeloExcel
{
    public static function escrever(string $destino): void
    {
        $dados = Catalogo::campos();
        $equip = Catalogo::equipamentos();

        $options = new Options;
        $options->setColumnWidth(26, ...range(1, max(count($dados), count($equip))));
        $writer = new Writer($options);
        $writer->openToFile($destino);

        $head = (new Style)->setFontBold()->setFontColor(Color::WHITE)->setBackgroundColor('0B1F3A')->setShouldWrapText(true)->setCellAlignment(CellAlignment::CENTER);
        $req = (new Style)->setFontBold()->setFontColor(Color::WHITE)->setBackgroundColor('4F46E5')->setShouldWrapText(true)->setCellAlignment(CellAlignment::CENTER);
        $grp = (new Style)->setFontBold()->setBackgroundColor('E0E7FF')->setFontColor('0B1F3A');
        $ex = (new Style)->setFontItalic()->setFontColor('6B7280')->setBackgroundColor('F3F4F6');
        $wrap = (new Style)->setShouldWrapText(true);

        // ---- Dados ----
        $writer->getCurrentSheet()->setName(Catalogo::SHEET);
        $writer->addRow(Row::fromValues(array_column($dados, 'group'), $grp));
        $writer->addRow(new Row(array_map(fn ($c) => Cell::fromValue($c['title'].($c['required'] ? ' *' : ''), $c['required'] ? $req : $head), $dados)));
        $writer->addRow(new Row(array_map(fn ($c, $i) => Cell::fromValue($i === 0 ? '(EXEMPLO - apague esta linha) '.$c['example'] : $c['example'], $ex), $dados, array_keys($dados))));

        // ---- Equipamentos ----
        $writer->addNewSheetAndMakeItCurrent()->setName(Catalogo::SHEET_EQUIPAMENTOS);
        $writer->addRow(new Row(array_map(fn ($c) => Cell::fromValue($c['title'].($c['required'] ? ' *' : ''), $c['required'] ? $req : $head), $equip)));
        $writer->addRow(new Row(array_map(fn ($c, $i) => Cell::fromValue($i === 0 ? '(EXEMPLO - apague esta linha) '.$c['example'] : $c['example'], $ex), $equip, array_keys($equip))));

        // ---- Instruções ----
        $writer->addNewSheetAndMakeItCurrent()->setName('Instruções');
        $linhas = [
            'Modelo de importação do Oravel Intelligence',
            'ABA "Dados": preencha UMA linha (a fotografia da empresa no mês) a partir da linha 3. Só a "Data de Referência" é obrigatória; quanto mais campos, mais indicadores e áreas do diagnóstico.',
            'ABA "Equipamentos" (opcional): uma linha por equipamento, com os números do MÊS da data de referência. Com ela, a Oravel mostra quais equipamentos explicam os números (revisar, trocar ou vender, subutilizados, reincidência).',
            'Se você preencher a aba "Equipamentos", vários campos da aba "Dados" são CALCULADOS dela (total, locados, indisponíveis, valor da frota, receita, custo de manutenção e OS): pode deixá-los em branco. O que você digitar na aba "Dados" prevalece.',
            'Mantenha o MESMO Nº Patrimônio em todos os meses: é ele que forma o histórico de cada equipamento.',
            'Não altere os títulos das colunas e apague as linhas de exemplo (se ficarem, são ignoradas). Números com ponto ou vírgula decimal, sem R$ nem %.',
            'Para acompanhar a evolução, envie uma planilha nova a cada mês. Nada que já foi importado é alterado.',
            '',
        ];
        foreach ($linhas as $i => $l) {
            $writer->addRow(Row::fromValues([$l], $i === 0 ? $grp : $wrap));
        }
        foreach ([['Aba "Dados"', $dados], ['Aba "Equipamentos"', $equip]] as [$titulo, $cols]) {
            $writer->addRow(Row::fromValues([$titulo], $grp));
            $writer->addRow(Row::fromValues(['Grupo', 'Coluna', 'Obrigatório', 'O que preencher', 'Exemplo'], $head));
            foreach ($cols as $c) {
                $writer->addRow(Row::fromValues([$c['group'], $c['title'], $c['required'] ? 'SIM' : 'Não', $c['help'], $c['example']], $wrap));
            }
            $writer->addRow(Row::fromValues(['']));
        }

        $writer->close();
    }
}
