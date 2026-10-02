<?php

namespace App\Console\Commands;

use App\Models\Course;
use App\Models\Lesson;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Importa os cursos da Academia a partir do menu (nav-data.js) do site
 * academy.oravel.com.br: cada grupo vira um curso, cada pagina uma aula que
 * aponta pra pagina da base de conhecimento. Idempotente (mesmo grupo/pagina =
 * atualiza, nao duplica) e NUNCA mexe em vinculos manuais: video, texto, anexo,
 * ordem e "publicado" so' sao gravados na criacao.
 */
class ImportAcademyCourses extends Command
{
    protected $signature = 'academy:import
        {path=/home/oravel/oravel-academy/assets/js/nav-data.js : Caminho do nav-data.js}
        {--base=https://academy.oravel.com.br : URL base do site}
        {--publish : Publica os cursos NOVOS (padrão: rascunho)}
        {--publish-only= : Títulos (separados por vírgula) dos cursos a PUBLICAR, novos ou já existentes}
        {--site= : Pasta do site (oravel-academy) para ler o resumo de cada aula (meta description)}
        {--dry-run : Só mostra o que seria importado}';

    /** Grupos internos da Oravel: nao entram na Academia dos clientes. */
    private const SKIP_GROUPS = ['Painel Central (Time Oravel)'];

    /**
     * Pagina da base de conhecimento -> modulo do contrato que ela ensina (aula so' aparece
     * pra quem tem o modulo). Paginas fora daqui (introducao, multi-tenant...) valem pra todos.
     */
    private const FEATURES = [
        '/painel-admin/dashboard.html' => 'modulo_dashboard',
        '/painel-admin/kanban.html' => 'tabela_maintenance_orders',
        '/painel-admin/agenda-tecnica.html' => 'tabela_maintenance_orders',
        '/modulos/ativos.html' => 'tabela_assets',
        '/modulos/dossie-ativo.html' => 'tabela_assets',
        '/modulos/grupos-checklist.html' => 'tabela_checklist_groups',
        '/modulos/ordens-servico.html' => 'tabela_maintenance_orders',
        '/modulos/modo-campo.html' => 'tabela_maintenance_orders',
        '/modulos/manutencao-preventiva.html' => 'tabela_preventive_maintenance_executions',
        '/modulos/checklist-inspecao.html' => 'tabela_checklist_templates',
        '/modulos/mobilizacao.html' => 'tabela_equipment_movements',
        '/modulos/avarias.html' => 'tabela_equipment_damages',
        '/modulos/substituicao-equipamento.html' => 'tabela_equipment_replacements',
        '/manutencao/processo-manutencao.html' => 'tabela_maintenance_orders',
        '/modulos/conformidade-nr13.html' => 'tabela_asset_nr13_specifications',
        '/modulos/conformidade-nr6-epi.html' => 'tabela_epi_deliveries',
        '/modulos/pmoc.html' => 'tabela_pmocs',
        '/modulos/frota-leve.html' => 'tabela_fleet_vehicles',
        '/modulos/motoristas.html' => 'tabela_fleet_drivers',
        '/modulos/chegada-patio.html' => 'tabela_fleet_statuses',
        '/logistica/processo-logistico.html' => 'tabela_fleet_vehicles',
        '/modulos/clientes.html' => 'tabela_clients',
        '/modulos/contratos.html' => 'tabela_contracts',
        '/modulos/solicitacoes-locacao.html' => 'tabela_solicitacao_locacao',
        '/modulos/leads.html' => 'tabela_crm_leads',
        '/modulos/funil-vendas.html' => 'tabela_crm_leads',
        '/modulos/mapa-leads.html' => 'tabela_crm_leads',
        '/modulos/agenda-comercial.html' => 'tabela_crm_leads',
        '/modulos/proposta-comercial.html' => 'tabela_proposta_comercial',
        '/comercial/processo-comercial.html' => 'tabela_contracts',
        '/modulos/materiais.html' => 'tabela_materials',
        '/modulos/fornecedores.html' => 'tabela_suppliers',
        '/modulos/solicitacao-pecas.html' => 'tabela_parts_requests',
        '/suprimentos/processo-suprimentos.html' => 'tabela_material_requests',
        '/modulos/departamentos.html' => 'tabela_departments',
        '/modulos/perfis-acesso.html' => 'tabela_roles',
        '/modulos/usuarios.html' => 'tabela_users',
        '/modulos/colaboradores.html' => 'tabela_employees',
        '/financeiro/processo-financeiro.html' => 'tabela_account_payables',
        '/modulos/chat.html' => 'modulo_chat',
        '/guias/abrir-os.html' => 'tabela_maintenance_orders',
        '/guias/registrar-avaria.html' => 'tabela_equipment_damages',
        '/guias/configurar-preventiva.html' => 'tabela_preventive_maintenance_executions',
        '/guias/usar-qrcode.html' => 'tabela_assets',
    ];

    /** Descricao de cada curso (a partir dos textos da home da Academy). So' preenche se estiver vazia. */
    private const DESCRIPTIONS = [
        'Comece Aqui' => 'Entenda como a Oravel funciona e como os dados de cada empresa ficam isolados.',
        'Painel Admin' => 'O painel do dia a dia: dashboard, quadro do pátio e agenda dos técnicos.',
        'Ativos e Frota' => 'Cadastre e acompanhe equipamentos, grupos de checklist e o dossiê rápido por QR Code.',
        'Manutenção' => 'Ordens de serviço, preventiva, checklist de inspeção, mobilização, avarias e substituição de equipamento.',
        'Processo de Manutenção' => 'Da abertura da OS ao equipamento de volta: como a manutenção funciona de ponta a ponta.',
        'Logística' => 'Frota leve, motoristas e chegadas no pátio.',
        'Processo Logístico' => 'Como funciona a logística: frota, motoristas, disponibilidade em tempo real e chegadas no pátio.',
        'Comercial' => 'Contratos, clientes, solicitações de locação e CRM (leads, funil de vendas, agenda e mapa).',
        'Processo Comercial' => 'Da prospecção do lead ao contrato fechado: funil, proposta, pedido de locação e cliente.',
        'Suprimentos' => 'Materiais, peças, fornecedores e solicitações de compra.',
        'Processo de Suprimentos' => 'Do pedido de peça à requisição, ordem de compra e recebimento.',
        'Gestão e Equipe' => 'Departamentos, perfis de acesso, usuários e chat interno.',
        'Processo Financeiro e Administrativo' => 'Contas a pagar e a receber, fluxo de caixa projetado, estoque, departamentos e equipe.',
        'Ferramentas' => 'Impressão e exportação para Excel.',
        'Comunicação' => 'Fotos, evidências e descrição em áudio: como a equipe registra e troca informação em campo.',
        'Guias Práticos' => 'Passo a passo para as tarefas mais comuns do dia a dia.',
    ];

    public function handle(): int
    {
        $path = (string) $this->argument('path');
        if (! is_file($path)) {
            $this->error("Arquivo não encontrado: {$path}");

            return self::FAILURE;
        }

        $groups = $this->parse((string) file_get_contents($path));
        if (! $groups) {
            $this->error('Nenhum grupo encontrado no arquivo.');

            return self::FAILURE;
        }

        $base = rtrim((string) $this->option('base'), '/');
        $dry = (bool) $this->option('dry-run');
        $position = 0;
        $publishOnly = array_filter(array_map('trim', explode(',', (string) $this->option('publish-only'))));
        $site = $this->option('site') ? rtrim((string) $this->option('site'), '/') : null;

        foreach ($groups as $group) {
            if (in_array($group['title'], self::SKIP_GROUPS, true)) {
                $this->line("— ignorado (interno): {$group['title']}");

                continue;
            }

            $position++;
            $this->info("{$group['title']} ({$this->count($group['items'])} aulas)");
            foreach ($group['items'] as $item) {
                $this->line('    '.str_pad($item['title'], 52).' → '.(self::FEATURES[$item['href']] ?? 'todos'));
            }

            if ($dry) {
                continue;
            }

            $course = Course::firstOrCreate(
                ['slug' => Str::slug($group['title'])],
                ['title' => $group['title'], 'position' => $position, 'is_published' => (bool) $this->option('publish')],
            );
            // so' preenche a descricao se estiver vazia: nao desfaz texto escrito na Central
            if (blank($course->description) && isset(self::DESCRIPTIONS[$group['title']])) {
                $course->description = self::DESCRIPTIONS[$group['title']];
            }
            if (in_array($group['title'], $publishOnly, true)) {
                $course->is_published = true;
            }
            $course->save();

            foreach ($group['items'] as $i => $item) {
                $lesson = Lesson::firstOrNew(['course_id' => $course->id, 'page_url' => $base.$item['href']]);
                $lesson->title = $item['title'];
                if (! $lesson->exists) {
                    $lesson->position = $i + 1;
                }
                // so' preenche o modulo se ainda estiver vazio: nao desfaz ajuste feito na Central
                $lesson->feature_key ??= self::FEATURES[$item['href']] ?? null;
                if (blank($lesson->summary) && $site) {
                    $lesson->summary = $this->summaryOf($site.$item['href']);
                }
                $lesson->save();
            }
        }

        $this->newLine();
        $this->info($dry ? 'Simulação concluída (nada gravado).' : 'Importação concluída.');

        return self::SUCCESS;
    }

    /** Resumo = meta description da pagina (texto que a propria Academy ja mantem). */
    private function summaryOf(string $file): ?string
    {
        if (! is_file($file)) {
            return null;
        }

        return preg_match('/<meta name="description" content="([^"]+)"/', (string) file_get_contents($file), $m)
            ? html_entity_decode($m[1], ENT_QUOTES)
            : null;
    }

    private function count(array $items): int
    {
        return count($items);
    }

    /** @return array<int, array{title:string, items:array<int, array{title:string, href:string}>}> */
    private function parse(string $js): array
    {
        $groups = [];

        foreach (preg_split('/\n\s*\{\s*\n\s*group:/', $js) as $i => $chunk) {
            if ($i === 0) {
                continue; // cabecalho do arquivo
            }
            if (! preg_match('/^\s*"([^"]+)"/', $chunk, $g)) {
                continue;
            }
            preg_match_all('/\{\s*title:\s*"([^"]+)",\s*href:\s*"([^"]+)"/', $chunk, $m, PREG_SET_ORDER);
            $items = array_map(fn ($x) => ['title' => $x[1], 'href' => $x[2]], $m);
            if ($items) {
                $groups[] = ['title' => $g[1], 'items' => $items];
            }
        }

        return $groups;
    }
}
