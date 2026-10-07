<?php

namespace App\Support\AssetImport;

use App\Domain\Fleet\Models\ForkliftSpecification;
use App\Domain\Fleet\Models\GeneratorSpecification;
use App\Domain\Fleet\Models\PlatformSpecification;
use App\Models\Asset;
use App\Models\AssetNr13Specification;
use Illuminate\Support\Str;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\CellAlignment;
use OpenSpout\Common\Entity\Style\Color;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Options;
use OpenSpout\Writer\XLSX\Writer;

/**
 * Fonte única das colunas da planilha de importação de ativos: o modelo para download
 * (aba "Ativos" + aba "Instruções") e o AssetExcelImporter leem daqui. Dados financeiros
 * (valor de aquisição, residual, vida útil, custo por km, data de aquisição) ficam de fora de propósito.
 *
 * Cada coluna: key, group, title, required, help, example, target (asset|forklift|platform|generator|nr13),
 * field (coluna no banco), type (text|int|decimal|bool|enum|ref), enum (label => valor) opcional.
 */
class AssetImportColumns
{
    public const SHEET = 'Ativos';

    /** @return list<array<string, mixed>> */
    public static function all(): array
    {
        $c = fn (string $group, string $title, string $target, string $field, string $type = 'text', string $help = '', string $example = '', bool $required = false, ?array $enum = null) => [
            'group' => $group, 'title' => $title, 'target' => $target, 'field' => $field, 'type' => $type,
            'help' => $help, 'example' => $example, 'required' => $required, 'enum' => $enum,
        ];

        $capUnits = ['kVA' => 'kVA', 'HP' => 'HP', 'A' => 'A', 'Ampères (A)' => 'A', 'toneladas' => 'toneladas', 'kg' => 'kg', 'm³' => 'm³', 'PCM' => 'PCM', 'L' => 'L', 'outro' => 'outro'];
        $status = [
            'Disponível' => Asset::STATUS_DISPONIVEL, 'Locado' => Asset::STATUS_LOCADO, 'Em Manutenção' => Asset::STATUS_MANUTENCAO,
            'Em Operação' => Asset::STATUS_OPERANDO, 'Aguardando Triagem' => Asset::STATUS_AGUARDANDO_TRIAGEM,
        ];
        $crit = ['Baixa' => 'low', 'Média' => 'medium', 'Alta' => 'high'];
        $yn = 'Sim ou Não.';

        $G1 = 'Identificação';
        $G2 = 'Operação';
        $G3 = 'Localização';
        $G4 = 'Empilhadeira';
        $G5 = 'Plataforma Elevatória';
        $G6 = 'Gerador';
        $G7 = 'NR-13';

        return [
            $c($G1, 'Nº Patrimônio', 'asset', 'patrimonio', 'text', 'Código único do equipamento na empresa (não pode repetir).', 'EMP-0001', true),
            $c($G1, 'Nome/Modelo', 'asset', 'name', 'text', 'Nome comercial ou modelo do equipamento.', 'Empilhadeira Elétrica 2,5t', true),
            $c($G1, 'Fabricante', 'asset', 'fabricante', 'text', 'Marca / fabricante.', 'Hyster'),
            $c($G1, 'Categoria e Tipo', 'asset', 'category', 'ref', 'Categoria do equipamento (ex.: Empilhadeira, Plataforma Elevatória, Gerador, Caminhão Munck). Se ainda não existir, é criada.', 'Empilhadeira'),
            $c($G1, 'Asset Tag (Etiqueta)', 'asset', 'tag', 'text', 'Código da etiqueta/plaqueta física, se houver.', 'TAG-0001'),
            $c($G1, 'Número de Série', 'asset', 'serial_number', 'text', 'Nº de série do fabricante (chassi, para veículos).', 'SN123456'),
            $c($G1, 'Ano de Fabricação', 'asset', 'manufacturing_year', 'int', 'Ano com 4 dígitos.', '2021'),
            $c($G1, 'Capacidade', 'asset', 'capacity_value', 'decimal', 'Valor numérico da capacidade principal.', '2.5'),
            $c($G1, 'Unidade da Capacidade', 'asset', 'capacity_unit', 'enum', 'kVA, HP, A, toneladas, kg, m³, PCM, L ou outro.', 'toneladas', false, $capUnits),
            $c($G1, 'Especificação Adicional', 'asset', 'specification', 'text', 'Texto livre com detalhes que não cabem nos outros campos.', 'Mastro triplex, garfos 1,2m'),
            $c($G1, 'Descrição', 'asset', 'description', 'text', 'Observações gerais sobre o equipamento.'),
            $c($G2, 'Status Operacional', 'asset', 'status', 'enum', 'Disponível, Locado, Em Manutenção, Em Operação ou Aguardando Triagem. Se vazio: Disponível.', 'Disponível', false, $status),
            $c($G2, 'Criticidade', 'asset', 'criticality', 'enum', 'Baixa, Média ou Alta. Se vazio: Média.', 'Média', false, $crit),
            $c($G2, 'É veículo?', 'asset', 'is_vehicle', 'bool', $yn.' Veículos usam odômetro.', 'Não'),
            $c($G2, 'Horímetro de Aquisição', 'asset', 'horimetro_inicial', 'decimal', 'Horas do equipamento quando entrou na frota (número). Para veículo, informe o km de aquisição.', '0'),
            $c($G2, 'Horímetro Atual', 'asset', 'horimetro_atual', 'decimal', 'Leitura atual do horímetro, em horas.', '1250'),
            $c($G2, 'Odômetro Atual (km)', 'asset', 'odometro_atual', 'decimal', 'Só para veículos, em km.'),
            $c($G2, 'Ciclos de Bateria Atual', 'asset', 'battery_cycles_atual', 'int', 'Ciclos de carga já realizados (equipamentos elétricos).'),
            $c($G3, 'Unidade/Filial Base', 'asset', 'internal_unit', 'ref', 'Onde o equipamento fica quando não está locado (matriz, filial, pátio). Deve estar cadastrada, com o nome igual.', 'Matriz'),
            $c($G3, 'Posição no Pátio', 'asset', 'storage_location', 'ref', 'Código da posição na planta baixa do pátio (deve existir), se usar.'),
            $c($G3, 'CEP do Equipamento', 'asset', 'cep', 'text', 'Usado para plotar no Mapa de Equipamentos.', '13010-000'),
            $c($G3, 'Endereço do Equipamento', 'asset', 'endereco', 'text', 'Endereço completo, se estiver em um local fixo.'),
            $c($G4, 'Subtipo / Classe', 'forklift', 'forklift_type', 'enum', 'Só empilhadeira. Valores: '.implode('; ', array_values(ForkliftSpecification::forkliftTypeLabels())).'.', '', false, array_flip(ForkliftSpecification::forkliftTypeLabels())),
            $c($G4, 'Capacidade de Carga (kg)', 'forklift', 'load_capacity_kg', 'decimal', 'Só empilhadeira. Número, em kg.', '2500'),
            $c($G4, 'Altura Máxima de Elevação (m)', 'forklift', 'lift_height_m', 'decimal', 'Só empilhadeira. Número, em metros.', '4.5'),
            $c($G4, 'Propulsão (Empilhadeira)', 'forklift', 'energy_type', 'enum', 'Só empilhadeira. Elétrica, GLP, Diesel, Gasolina ou Manual (sem motor).', 'Elétrica', false, array_flip(ForkliftSpecification::energyTypeLabels())),
            $c($G4, 'Tipo de Torre / Elevação', 'forklift', 'mast_type', 'enum', 'Só empilhadeira. Dupla, Tripla, Dupla com Duplex ou Retrátil.', '', false, array_flip(ForkliftSpecification::mastTypeLabels())),
            $c($G4, 'Tipo de Pneu', 'forklift', 'tire_type', 'enum', 'Só empilhadeira. Super Elástico, Pneumático, Cushion, Non-marking ou Poliuretano.', '', false, array_flip(ForkliftSpecification::tireTypeLabels())),
            $c($G4, 'Voltagem da Bateria', 'forklift', 'battery_voltage', 'text', 'Só empilhadeira. Ex.: 48V.', '48V'),
            $c($G4, 'Amperagem da Bateria (Ah)', 'forklift', 'battery_amperage_ah', 'decimal', 'Só empilhadeira. Número.', '600'),
            $c($G4, 'Nº de Série da Bateria', 'forklift', 'battery_serial_number', 'text', 'Só empilhadeira.'),
            $c($G4, 'Modelo do Carregador', 'forklift', 'charger_model', 'text', 'Só empilhadeira.'),
            $c($G4, 'Ciclos de Carga da Bateria', 'forklift', 'battery_cycles', 'int', 'Só empilhadeira. Número.'),
            $c($G5, 'Tipo de Plataforma', 'platform', 'platform_type', 'enum', 'Só plataforma. Tesoura (Scissor Lift), Articulada (Boom Lift), Telescópica ou Mastro Vertical.', '', false, array_flip(PlatformSpecification::platformTypeLabels())),
            $c($G5, 'Propulsão (Plataforma)', 'platform', 'energy_type', 'enum', 'Só plataforma. Elétrica, Diesel ou Híbrida.', '', false, array_flip(PlatformSpecification::energyTypeLabels())),
            $c($G5, 'Capacidade de Carga na Plataforma (kg)', 'platform', 'platform_capacity_kg', 'decimal', 'Só plataforma. Número.'),
            $c($G5, 'Altura de Trabalho (m)', 'platform', 'working_height_m', 'decimal', 'Só plataforma. Número.'),
            $c($G5, 'Altura da Plataforma - Piso (m)', 'platform', 'platform_height_m', 'decimal', 'Só plataforma. Número.'),
            $c($G5, 'Alcance Horizontal (m)', 'platform', 'horizontal_outreach_m', 'decimal', 'Só plataforma. Número.'),
            $c($G5, 'Peso Operacional (kg)', 'platform', 'operational_weight_kg', 'decimal', 'Só plataforma. Número.'),
            $c($G6, 'Tipo de Tensão', 'generator', 'voltage_type', 'enum', 'Só gerador. Monofásico ou Trifásico.', '', false, array_flip(GeneratorSpecification::voltageTypeLabels())),
            $c($G6, 'Voltagem', 'generator', 'voltage', 'text', 'Só gerador. Ex.: 220V / 380V.'),
            $c($G6, 'Tipo de Partida', 'generator', 'starter_type', 'enum', 'Só gerador. Elétrica ou Manual.', '', false, array_flip(GeneratorSpecification::starterTypeLabels())),
            $c($G6, 'Capacidade do Tanque (L)', 'generator', 'fuel_tank_capacity_l', 'decimal', 'Só gerador. Número, em litros.'),
            $c($G7, 'Sujeito à NR-13?', 'nr13', 'subject_to_nr13', 'bool', $yn, 'Não'),
            $c($G7, 'Tipo de Equipamento NR-13', 'nr13', 'tipo_equipamento', 'enum', 'Caldeira, Vaso de Pressão, Tubulação ou Tanque.', '', false, array_flip(AssetNr13Specification::tipoEquipamentoLabels())),
            $c($G7, 'Categoria de Risco', 'nr13', 'categoria_risco', 'text'),
            $c($G7, 'TAG NR-13', 'nr13', 'tag_nr13', 'text'),
            $c($G7, 'Observações NR-13', 'nr13', 'observacoes', 'text', 'Texto livre.'),
        ];
    }

    /** Normaliza um título/valor para comparação: sem acento, minúsculo, sem "*" nem espaços extras. */
    public static function normalize(?string $value): string
    {
        $v = trim(str_replace('*', '', (string) $value));
        $v = Str::ascii($v);

        return preg_replace('/\s+/', ' ', mb_strtolower($v));
    }

    /** Gera o modelo .xlsx (aba "Ativos" vazia pra preencher + aba "Instruções") no caminho ou stream informado. */
    /**
     * @param  list<array<string, mixed>>|null  $cols  padrão: colunas de ativos (o importador de clientes reaproveita este gerador)
     * @param  list<string>|null  $intro  linhas da aba Instruções (a primeira é o título)
     */
    public static function writeTemplate(string $target, ?array $cols = null, string $sheetName = self::SHEET, ?array $intro = null): void
    {
        $cols ??= self::all();

        $options = new Options;
        $options->setColumnWidth(26, ...range(1, count($cols)));
        $writer = new Writer($options);
        $writer->openToFile($target);

        $head = (new Style)->setFontBold()->setFontColor(Color::WHITE)->setBackgroundColor('0B1F3A')->setShouldWrapText(true)->setCellAlignment(CellAlignment::CENTER);
        $req = (new Style)->setFontBold()->setFontColor(Color::WHITE)->setBackgroundColor('2563EB')->setShouldWrapText(true)->setCellAlignment(CellAlignment::CENTER);
        $grp = (new Style)->setFontBold()->setBackgroundColor('E8EFFF')->setFontColor('0B1F3A');
        $wrap = (new Style)->setShouldWrapText(true);

        $writer->getCurrentSheet()->setName($sheetName);
        $writer->addRow(Row::fromValues(array_column($cols, 'group'), $grp));
        $writer->addRow(new Row(array_map(
            fn (array $c) => Cell::fromValue($c['title'].($c['required'] ? ' *' : ''), $c['required'] ? $req : $head),
            $cols
        )));

        $sheet = $writer->addNewSheetAndMakeItCurrent();
        $sheet->setName('Instruções');
        foreach ($intro ?? [
            'Modelo de cadastro de ativos - Oravel',
            'Preencha a aba "Ativos" a partir da linha 3, uma linha por equipamento. Não altere os títulos das colunas.',
            'Colunas com * são obrigatórias. Campos sem informação podem ficar em branco.',
            'Preencha só os blocos do tipo do equipamento (Empilhadeira, Plataforma, Gerador); os demais ficam em branco.',
            'Dados financeiros (valor de aquisição, valor residual, vida útil, custo por km) não fazem parte deste modelo.',
            'Números com ponto ou vírgula decimal, sem R$ nem unidades. Valores de lista (status, tipos) devem ser escritos como na coluna "O que preencher".',
            '',
        ] as $i => $line) {
            $writer->addRow(Row::fromValues([$line], $i === 0 ? $grp : null));
        }
        $writer->addRow(Row::fromValues(['Grupo', 'Coluna', 'Obrigatório', 'O que preencher / valores aceitos', 'Exemplo'], $head));
        foreach ($cols as $c) {
            $writer->addRow(Row::fromValues([$c['group'], $c['title'], $c['required'] ? 'SIM' : 'Não', $c['help'], $c['example']], $wrap));
        }

        $writer->close();
    }
}
