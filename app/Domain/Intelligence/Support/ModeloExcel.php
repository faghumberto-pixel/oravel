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
    /**
     * @param  ?array{data_referencia: string, linhas: list<array<string, mixed>>, aviso?: string}  $exportacao  planilha PREENCHIDA (gerada pelo ERP);
     *                                                                                                           null = modelo em branco com linhas de exemplo
     */
    public static function escrever(string $destino, ?array $exportacao = null): void
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
        if ($exportacao !== null) {
            // só a data: o resto da aba "Dados" é calculado da aba "Equipamentos" (ou o cliente completa o que o ERP não tem)
            $writer->addRow(new Row(array_map(fn ($c) => Cell::fromValue($c['key'] === 'data_referencia' ? $exportacao['data_referencia'] : null), $dados)));
        } else {
            $writer->addRow(new Row(array_map(fn ($c, $i) => Cell::fromValue($i === 0 ? '(EXEMPLO - apague esta linha) '.$c['example'] : $c['example'], $ex), $dados, array_keys($dados))));
        }

        // ---- Equipamentos ----
        $writer->addNewSheetAndMakeItCurrent()->setName(Catalogo::SHEET_EQUIPAMENTOS);
        $writer->addRow(new Row(array_map(fn ($c) => Cell::fromValue($c['title'].($c['required'] ? ' *' : ''), $c['required'] ? $req : $head), $equip)));
        if ($exportacao !== null) {
            foreach ($exportacao['linhas'] as $linha) {
                $writer->addRow(new Row(array_map(fn ($c) => Cell::fromValue($linha[$c['title']] ?? null), $equip)));
            }
        } else {
            $writer->addRow(new Row(array_map(fn ($c, $i) => Cell::fromValue($i === 0 ? '(EXEMPLO - apague esta linha) '.$c['example'] : $c['example'], $ex), $equip, array_keys($equip))));
        }

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
        if ($exportacao !== null) {
            array_splice($linhas, 1, 0, [
                'PLANILHA GERADA PELO ERP DA ORAVEL: a aba "Equipamentos" já vem preenchida com os números do mês. Confira os valores antes de importar em Intelligence › Importações.',
                'Na aba "Dados" só a data está preenchida: frota, receita, custos e OS são calculados dos equipamentos. Se quiser, complete o que o ERP não tem (valor em atraso, propostas, técnicos, entregas no prazo...).',
                ...(isset($exportacao['aviso']) && $exportacao['aviso'] !== '' ? ['ATENÇÃO: '.$exportacao['aviso']] : []),
            ]);
        }
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
