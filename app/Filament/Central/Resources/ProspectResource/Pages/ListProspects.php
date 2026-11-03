<?php

namespace App\Filament\Central\Resources\ProspectResource\Pages;

use App\Filament\Central\Resources\ProspectResource;
use App\Models\Prospect;
use App\Support\CrmPalette;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListProspects extends ListRecords
{
    protected static string $resource = ProspectResource::class;

    protected static string $view = 'filament.central.prospects-list';

    /** Cards clicaveis acima da tabela: cada um aplica um conjunto de filtros (chave => valor normalizado). */
    public const CARDS = [
        'total' => ['Total', []],
        'nao_enviado' => ['Não enviei', ['status' => 'nao_enviado']],
        'enviado' => ['Enviei', ['status' => 'enviado']],
        'respondeu' => ['Respondeu', ['status' => 'respondeu']],
        'em_teste' => ['Em teste', ['status' => 'em_teste']],
        'sem_interesse' => ['Sem interesse', ['status' => 'sem_interesse']],
        'nova' => ['Empresas novas', ['empresa_nova' => '1']],
        'campinas' => ['Região de Campinas', ['regiao_campinas' => '1']],
        'nota_alta' => ['Nota 8 ou mais', ['nota_alta' => '1']],
        'retorno' => ['Retorno vencido', ['retorno_vencido' => '1']],
    ];

    /** Cor por significado de cada card (nome da paleta de App\Support\CrmPalette::cor). */
    public const CORES = [
        'total' => 'gray', 'nao_enviado' => 'gray', 'enviado' => 'blue', 'respondeu' => 'emerald',
        'em_teste' => 'purple', 'sem_interesse' => 'red', 'nova' => 'teal', 'campinas' => 'cyan',
        'nota_alta' => 'amber', 'retorno' => 'orange',
    ];

    /** Cor de cada card "por estagio / resultado" (nome da paleta de CrmPalette::cor). */
    public const CORES_RESULTADO = [
        'nao_atendeu' => 'gray', 'nao_respondeu' => 'amber', 'numero_invalido' => 'gray', 'ja_tem_sistema' => 'pink',
        'sem_interesse_momento' => 'orange', 'sem_interesse' => 'red', 'pediu_email' => 'indigo', 'quer_reuniao' => 'cyan',
        'reuniao_agendada' => 'teal', 'proposta_enviada' => 'purple', 'contrato_andamento' => 'blue', 'fechado' => 'emerald',
    ];

    /** Filtros que os cards controlam; os demais (lote, porte, atividade, origem, ano) sao "base" e valem para todos os cards. */
    private const CHAVES_CARD = ['status', 'resultado', 'empresa_nova', 'regiao_campinas', 'nota_alta', 'retorno_vencido'];

    /** Cards de situacao/recortes + um card por estagio / resultado (chave "res_<resultado>"). */
    public static function todosCards(): array
    {
        $cards = self::CARDS;
        foreach (Prospect::RESULTADOS as $k => $rotulo) {
            $cards['res_'.$k] = [$rotulo, ['resultado' => $k]];
        }

        return $cards;
    }

    public function filtrarCard(string $chave): void
    {
        $filtros = self::todosCards()[$chave][1] ?? [];

        // Mantem os filtros base; so troca o recorte do card.
        $estado = $this->tableFilters ?? [];
        foreach (self::CHAVES_CARD as $k) {
            $estado[$k] = in_array($k, ['nota_alta', 'retorno_vencido'], true) ? ['isActive' => false] : ['value' => null];
        }
        foreach ($filtros as $k => $valor) {
            $estado[$k] = in_array($k, ['nota_alta', 'retorno_vencido'], true) ? ['isActive' => true] : ['value' => $valor];
        }
        $this->tableFilters = $estado;
        // Atribuir pelo servidor nao dispara o gancho do Filament que grava na sessao.
        $this->updatedTableFilters();
        $this->resetPage();
    }

    public function cards(): array
    {
        $atuais = $this->tableFilters ?? [];
        $ativosCard = array_intersect_key(Prospect::filtrosAtivos($atuais), array_flip(self::CHAVES_CARD));

        // Filtros base + busca atuais, sem os filtros que o proprio card controla.
        $base = array_diff_key($atuais, array_flip(self::CHAVES_CARD));

        $cards = [];
        foreach (self::todosCards() as $chave => [$rotulo, $filtros]) {
            $estado = $base;
            foreach ($filtros as $k => $valor) {
                $estado[$k] = in_array($k, ['nota_alta', 'retorno_vencido'], true) ? ['isActive' => true] : ['value' => $valor];
            }
            $cards[] = [
                'chave' => $chave,
                'grupo' => str_starts_with($chave, 'res_') ? 'resultado' : 'geral',
                'rotulo' => $rotulo,
                'total' => Prospect::filtrar($estado)->busca($this->tableSearch)->count(),
                'ativo' => $ativosCard == $filtros,
                'paleta' => CrmPalette::cor(self::CORES[$chave] ?? self::CORES_RESULTADO[substr($chave, 4)] ?? 'gray'),
            ];
        }

        return $cards;
    }

    /** Abre o PDF (gerado em PHP) com os filtros, a busca e a ordem que estão na tela. */
    public function urlImpressao(): string
    {
        return route('central.prospects.imprimir', array_filter([
            'filtros' => $this->tableFilters,
            'busca' => $this->tableSearch,
        ]));
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('imprimir')->label('Imprimir')->icon('heroicon-o-printer')->color('gray')
                ->url(fn (): string => $this->urlImpressao())->openUrlInNewTab(),
            Actions\CreateAction::make()->label('Novo prospect'),
        ];
    }
}
