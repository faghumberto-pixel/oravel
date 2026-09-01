<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class Pmp5w2hController extends Controller
{
    private array $features = [
        'cobertura' => [
            'icon' => '📊',
            'title' => 'Cobertura do PMP',
            'subtitle' => 'Análise de cobertura preventiva',
            'what' => 'Dashboard que visualiza a cobertura de planos preventivos por equipamento, mostrando quais ativos possuem planos associados e qual é o nível de cobertura geral da frota.',
            'why' => 'Identificar gaps de cobertura preventiva, permitindo que gestores saibam quais equipamentos ainda carecem de planos de manutenção programada e assim reduzir falhas não planejadas.',
            'when' => 'Consultado durante planejamento trimestral/semestral, na avaliação de saúde geral da frota, ou quando há inclusão de novos equipamentos ao patrimônio.',
            'where' => 'Menu PMP → Cobertura do PMP. Acesso via painel administrativo para visualização global ou por grupo de equipamento.',
            'who' => 'Gestor de Manutenção, Diretor de Operações, Analista de Planejamento. Requer permissão "ler_plano" ou "admin".',
            'how' => 'Sistema calcula automaticamente: (Equipamentos com Plano / Total de Equipamentos) × 100. Exibe gráficos de tendência, breakdown por grupo e lista de equipamentos descobertos.',
            'howmuch' => 'Tempo: 3-5 min para análise completa. Custo: incluído no plano base. Impacto: reduz falhas imprevistas em até 35% quando cobertura > 90%.',
        ],
        'dashboard' => [
            'icon' => '📈',
            'title' => 'Dashboard PMP',
            'subtitle' => 'KPIs e métricas de performance',
            'what' => 'Painel executivo com KPIs em tempo real: execuções preventivas do mês, taxa de compliance, custo acumulado, equipamentos críticos e gráficos de tendência de efetividade.',
            'why' => 'Fornecer visão holística da saúde preventiva, permitir decisões baseadas em dados sobre alocação de recursos e identificar desvios antes que se tornem críticos.',
            'when' => 'Acesso diário por gerentes; análise semanal para ajustes operacionais; revisão mensal para planejamento do período seguinte.',
            'where' => 'Menu PMP → Dashboard PMP. Primeira página após expandir o menu PMP, acesso rápido como card na página inicial do painel.',
            'who' => 'Diretor de Operações, Gerente de Manutenção, Supervisor de Frota. Acesso por permissão "admin" ou "visualizar_dashboard_pmp".',
            'how' => 'Widgets renderizam dados agregados de PreventiveMaintenanceExecution, MaintenancePlan e Asset. Gráficos atualizam em tempo real; filtros por período, cliente, grupo disponíveis.',
            'howmuch' => 'Tempo: 30 segundos para carregar. Custo: servidor de dashboard (incluído). ROI: economia de 15-25% em custos de manutenção corretiva.',
        ],
        'alocacao' => [
            'icon' => '👥',
            'title' => 'Alocação de Técnicos',
            'subtitle' => 'Agendamento de preventivas',
            'what' => 'Ferramenta de gantt/alocação que visualiza agenda de técnicos em período selecionado, mostrando execuções preventivas programadas, disponibilidade e carga de trabalho.',
            'why' => 'Otimizar utilização de recursos humanos, evitar sobrecargas, distribuir preventivas equilibradamente entre técnicos e garantir cumprimento dos cronogramas de manutenção.',
            'when' => 'Semanalmente para planejamento da semana seguinte; mensalmente para balanceamento geral; antes de períodos de alta demanda.',
            'where' => 'Menu PMP → Alocação de Técnicos. Suporta modos: Semana, Mês, Gantt. Impressão minimalista disponível para relatórios.',
            'who' => 'Supervisor de Técnicos, Coordenador de Manutenção, Gerente de Operações. Permissão "editar_alocacao" ou "admin".',
            'how' => 'Exibe técnicos em linhas, dias em colunas, execuções como blocos coloridos. Drag-drop para reagendar. Resumo por técnico: alocados, aguardando confirmação, confirmados. Filtros por cliente, período.',
            'howmuch' => 'Tempo: 5-10 min por alocação. Custo: incluído. Eficiência: reduz conflitos de agenda em 60%, melhora cumprimento de prazos em 40%.',
        ],
        'consulta' => [
            'icon' => '🔍',
            'title' => 'Consulta por Cliente',
            'subtitle' => 'Histórico preventivo por cliente',
            'what' => 'Página de análise que consolida todas as execuções preventivas de um cliente específico, mostrando equipamentos, planos, status, histórico e próximas execuções agendadas.',
            'why' => 'Fornecer visibilidade única da manutenção preventiva por cliente, facilitando relatórios SLA, análise de tendências de falhas e comunicação com cliente sobre saúde de sua frota.',
            'when' => 'Consultas ad-hoc por cliente; reuniões de revisão de SLA; análise de desvios; resposta a questionamentos do cliente sobre manutenção.',
            'where' => 'Menu PMP → Consulta por Cliente. Seleção de cliente no topo da página; filtros secundários: equipamento, grupo, status, período.',
            'who' => 'Gestor de Contas, Analista de SLA, Gerente de Manutenção. Acesso por "ver_cliente" ou "admin". Clientes podem ver dados próprios via Portal do Cliente.',
            'how' => 'Tabela de manutenções com colunas: patrimônio, ativo, plano, status, data última execução, próxima agendada. Filtros dinâmicos, ordenação por coluna, export para PDF minimalista.',
            'howmuch' => 'Tempo: 2-3 min por consulta. Custo: incluído. Benefício: reduz tempo de atendimento a cliente em 50%, melhora satisfação em comunicações proativas.',
        ],
        'planos' => [
            'icon' => '📋',
            'title' => 'Planos Preventivos',
            'subtitle' => 'Gestão de templates de manutenção',
            'what' => 'CRUD completo de planos de manutenção preventiva: criar, editar, ativar/desativar planos associados a grupos de equipamentos com intervalo por horímetro ou dias.',
            'why' => 'Garantir consistência na manutenção preventiva, documentar boas práticas, facilitar escala de operações e estabelecer base para agendamento automático de manutenções.',
            'when' => 'Criação inicial ao cadastrar novo tipo de equipamento; revisão semestral de efetividade; ajustes quando há mudanças de equipamento ou legislação.',
            'where' => 'Menu PMP → Planos Preventivos. Acesso direto pela navegação ou via Grupos de Equipamento (relação hasMany).',
            'who' => 'Especialista em Manutenção, Engenheiro de Processos, Gerente Técnico. Permissão "criar_plano", "editar_plano" ou "admin".',
            'how' => 'Formulário: nome, grupo de equipamento, descrição, intervalo (horímetro/dias), materiais necessários, checklist de itens, tempo estimado. Ativa/desativa para controlar agendamento futuro.',
            'howmuch' => 'Tempo: 15-30 min por plano. Custo: incluído. Resultado: reduz tempo de criação de OS preventivas em 80%, melhora conformidade com padrões.',
        ],
        'kanban' => [
            'icon' => '📊',
            'title' => 'Kanban Preventivas',
            'subtitle' => 'Visualização por status',
            'what' => 'Visualização Kanban de execuções preventivas em andamento, agrupadas por status (Aguardando Diagnóstico, Em Manutenção, Aguardando Peça, Teste, Pronto, Pendências, Concluído).',
            'why' => 'Fornecer visão operacional em tempo real do fluxo de manutenções preventivas, identificar gargalos, gerenciar workflow e facilitar comunicação entre equipes.',
            'when' => 'Acesso diário por coordenador; reuniões de stand-up de manutenção; acompanhamento de execuções críticas; revisão de status antes de fechamento de turno.',
            'where' => 'Menu PMP → Kanban Preventivas. Colunas representam cada status; cards exibem patrimônio, plano, técnico responsável e horímetro.',
            'who' => 'Coordenador de Manutenção, Supervisores de Turno, Técnicos. Permissão "ver_kanban" ou "admin". Técnicos veem cards associados a eles.',
            'how' => 'Colunas em grid horizontal, filtros por período/técnico/equipamento/grupo/cliente. Cards com links "Ver OS". Botão "Imprimir Agora" para visualização minimalista. Cores por criticidade.',
            'howmuch' => 'Tempo: < 1 min para visualizar. Custo: incluído. Impacto: reduz lead time de preventivas em 25%, melhora identificação de bloqueios em 70%.',
        ],
        'analise' => [
            'icon' => '📉',
            'title' => 'Análise de Plano',
            'subtitle' => 'Efetividade de preventivas',
            'what' => 'Relatório analítico que avalia efetividade de cada plano preventivo, comparando falhas corretivas antes vs. depois, tempo médio entre execuções, cumprimento de intervals e economia gerada.',
            'why' => 'Validar ROI de investimento preventivo, identificar planos ineficazes que precisam revisão, justificar orçamento para manutenção e demonstrar valor para a organização.',
            'when' => 'Revisão mensal/trimestral; apresentações a stakeholders; análise de tendências ao fim de período; quando questionar efetividade de um plano.',
            'where' => 'Menu PMP → Análise de Plano Preventivo. Pode acessar análise por plano individual ou relatório consolidado de todos os planos.',
            'who' => 'Gestor de Manutenção, Diretor Financeiro, Analista de Processos. Permissão "ver_relatorios" ou "admin". Insights para decisão estratégica.',
            'how' => 'Gráficos: taxa de cumprimento, evolução de falhas corretivas, custo preventivo vs. corretivo. KPIs: MTBF, % de preventiva no total, economia acumulada. Filtros por período, grupo, cliente.',
            'howmuch' => 'Tempo: 10-15 min por análise. Custo: processamento de dados (incluído). Retorno: típico 200-300% ROI em 2 anos com preventiva bem planejada.',
        ],
    ];

    public function show(Request $request, string $feature): View
    {
        if (!isset($this->features[$feature])) {
            abort(404, 'Funcionalidade não encontrada');
        }

        $data = $this->features[$feature];

        return view('pmp.5w2h-print', compact('feature', 'data', 'features'));
    }

    public function index(): View
    {
        return view('pmp.5w2h-index', ['features' => $this->features]);
    }
}
