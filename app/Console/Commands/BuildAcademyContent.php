<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * FERRAMENTA DE QUEM MANTEM A ACADEMIA (nao roda em producao). Monta o conteudo versionado em
 * database/data/academy/ a partir do site academy.oravel.com.br (nav-data.js + paginas) e dos
 * "visuais" de cada aula (telas reais com destaques e mockups, escritos a mao):
 *
 *   courses.json            -> cursos, aulas, resumo, modulo do contrato de cada aula
 *   lessons/{slug}.html     -> corpo da aula = visual + texto da pagina, limpo
 *
 * O texto e' limpo pra o aluno: sem links pra fora, sem titulo/breadcrumb/"veja tambem", e
 * qualquer trecho com assunto interno (FORBIDDEN) e' descartado. Depois e' so' rodar
 * `php artisan academy:import`, que le esses arquivos (nao depende do site).
 */
class BuildAcademyContent extends Command
{
    protected $signature = 'academy:build-content
        {--site= : Pasta com as paginas do site (espelho do academy.oravel.com.br)}
        {--visuals= : Pasta com os visuais (HTML) de cada aula, nome = slug da pagina}
        {--out= : Pasta de saida (padrão: database/data/academy)}';

    /** Grupos internos da Oravel: nao entram na Academia dos clientes. */
    private const SKIP_GROUPS = ['Painel Central (Time Oravel)'];

    /** Paginas que NAO entram na Academia (assunto interno/tecnico). */
    private const SKIP_PAGES = ['/multi-tenant.html'];

    /** Termos que nunca podem aparecer pro cliente: o trecho (paragrafo, item, linha, secao) e' descartado. */
    private const FORBIDDEN = '/tenant|painel central|\/central\b|munkmaq|isolament/iu';

    /** Aulas escritas 100% a mao (a pagina original e' vitrine de links ou nota tecnica interna): o texto dela nao entra. */
    private const VISUAL_ONLY = ['/index.html', '/suprimentos/processo-suprimentos.html'];

    /** Trechos do texto original que o sistema real desmente: o bloco que contem a frase e' descartado. */
    private const DROP_BLOCKS = [
        '/modulos/ciclo-compras.html' => ['O estoque só entra pelo Recebimento', 'Ativos e Materiais'],
        '/modulos/contratos.html' => ['bug conhecido', 'Não existe botão pra mudar o status', 'time técnico'],
        '/modulos/clientes.html' => ['bug conhecido', 'ficha completa de integração ERP'],
        '/modulos/materiais.html' => ['Ativos e Materiais'],
        '/modulos/fornecedores.html' => ['Ativos e Materiais'],
        '/modulos/solicitacao-pecas.html' => ['Ativos e Materiais'],
    ];

    /** Palavras internas trocadas por um equivalente de cliente ANTES da limpeza (evita perder o trecho inteiro). */
    private const REWORD = [
        '/o time técnico conduz o processo/u' => 'a equipe de manutenção conduz o processo', '/pelo time técnico\/via sistema/u' => 'pela equipe ou pelo sistema',
        '/\bdo seu tenant\b/iu' => 'da sua empresa', '/\bdo tenant\b/iu' => 'da empresa', '/\bno tenant\b/iu' => 'na empresa',
        '/\bseu tenant\b/iu' => 'sua empresa', '/\bo tenant\b/iu' => 'a empresa',
        '/as 4 abas: Identificação e Faturamento, Entrega e Contatos, Legal e Documentação, e Análise de Risco/u' => 'as abas: Identificação e Faturamento, Entrega e Contatos, Legal e Documentação, Análise de Risco e Resumo Financeiro',
    ];

    private const DESCRIPTIONS = [
        'Comece Aqui' => 'Entenda como a Oravel funciona e como as peças do sistema se encaixam.',
        'Painel Admin' => 'O painel do dia a dia: dashboard, quadro do pátio e agenda dos técnicos.',
        'Ativos e Frota' => 'Cadastre e acompanhe equipamentos, grupos de checklist e o dossiê rápido por QR Code.',
        'Manutenção' => 'Ordens de serviço, modo campo, preventiva, checklist, mobilização, avarias e substituição de equipamento.',
        'Processo de Manutenção' => 'Da abertura da OS ao equipamento de volta: como a manutenção funciona de ponta a ponta.',
        'Conformidade' => 'NR-13, NR-6 (EPI) e PMOC: documentos, inspeções e alertas de vencimento.',
        'Logística' => 'Frota leve, motoristas e chegadas no pátio.',
        'Processo Logístico' => 'Como funciona a logística: frota, motoristas, disponibilidade em tempo real e chegadas no pátio.',
        'Comercial' => 'Contratos, clientes, propostas, orçamentos, solicitações de locação e CRM (leads, funil, agenda e mapa).',
        'Processo Comercial' => 'Da prospecção do lead ao contrato fechado: funil, proposta, pedido de locação e cliente.',
        'Suprimentos' => 'Materiais, peças, fornecedores, solicitações de compra e o ciclo de compras.',
        'Processo de Suprimentos' => 'Do pedido de peça à requisição, ordem de compra e recebimento.',
        'Gestão e Equipe' => 'Departamentos, perfis de acesso, usuários e chat interno.',
        'Departamento Pessoal' => 'Colaboradores, certificações, alocação de equipamento e ponto eletrônico.',
        'Processo Financeiro e Administrativo' => 'Contas a pagar e a receber, fluxo de caixa projetado, estoque, departamentos e equipe.',
        'Ferramentas' => 'Impressão e exportação para Excel.',
        'Comunicação' => 'Fotos, evidências e descrição em áudio: como a equipe registra e troca informação em campo.',
        'Guias Práticos' => 'Passo a passo para as tarefas mais comuns do dia a dia.',
    ];

    /** Pagina da base de conhecimento -> modulo do contrato que ela ensina (null = todos). */
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
        '/modulos/orcamentos.html' => 'tabela_quotes',
        '/comercial/processo-comercial.html' => 'tabela_contracts',
        '/modulos/materiais.html' => 'tabela_materials',
        '/modulos/fornecedores.html' => 'tabela_suppliers',
        '/modulos/solicitacao-pecas.html' => 'tabela_parts_requests',
        '/modulos/ciclo-compras.html' => 'tabela_purchase_orders',
        '/suprimentos/processo-suprimentos.html' => 'tabela_material_requests',
        '/modulos/departamentos.html' => 'tabela_departments',
        '/modulos/perfis-acesso.html' => 'tabela_roles',
        '/modulos/usuarios.html' => 'tabela_users',
        '/modulos/colaboradores.html' => 'tabela_employees',
        '/modulos/alocacao-equipamento.html' => 'tabela_equipment_allocations',
        '/modulos/ponto-eletronico.html' => 'tabela_time_clocks',
        '/financeiro/processo-financeiro.html' => 'tabela_account_payables',
        '/modulos/chat.html' => 'modulo_chat',
        '/guias/abrir-os.html' => 'tabela_maintenance_orders',
        '/guias/registrar-avaria.html' => 'tabela_equipment_damages',
        '/guias/configurar-preventiva.html' => 'tabela_preventive_maintenance_executions',
        '/guias/usar-qrcode.html' => 'tabela_assets',
    ];

    public function handle(): int
    {
        $site = rtrim((string) $this->option('site'), '/');
        $visuals = rtrim((string) $this->option('visuals'), '/');
        $out = rtrim((string) ($this->option('out') ?: database_path('data/academy')), '/');

        if (! is_file($site.'/assets/js/nav-data.js')) {
            $this->error("nav-data.js não encontrado em {$site}/assets/js/");

            return self::FAILURE;
        }

        @mkdir($out.'/lessons', 0775, true);
        $groups = $this->parseNav((string) file_get_contents($site.'/assets/js/nav-data.js'));
        $courses = [];
        $missingVisual = [];

        foreach ($groups as $g) {
            if (in_array($g['title'], self::SKIP_GROUPS, true)) {
                continue;
            }
            $lessons = [];
            foreach ($g['items'] as $item) {
                if (in_array($item['href'], self::SKIP_PAGES, true)) {
                    continue;
                }
                $slug = $this->slug($item['href']);
                $visual = is_file("{$visuals}/{$slug}.html") ? (string) file_get_contents("{$visuals}/{$slug}.html") : '';
                $text = in_array($item['href'], self::VISUAL_ONLY, true) ? '' : $this->bodyOf($site.$item['href'], self::DROP_BLOCKS[$item['href']] ?? []);
                if (str_contains($visual, 'mk-menu')) {
                    // o visual ja traz o caminho de menu correto: tira a etiqueta (possivelmente desatualizada) do texto
                    $text = trim((string) preg_replace('/<div class="mk-menu">.*?<\/div>/su', '', $text));
                }
                $body = trim($visual."\n".$text);
                if ($visual === '') {
                    $missingVisual[] = $item['href'];
                }
                file_put_contents("{$out}/lessons/{$slug}.html", $body);
                $lessons[] = [
                    'title' => $item['title'],
                    'href' => $item['href'],
                    'slug' => $slug,
                    'summary' => $this->summaryOf($site.$item['href']),
                    'feature' => self::FEATURES[$item['href']] ?? null,
                ];
            }
            if ($lessons) {
                $courses[] = ['title' => $g['title'], 'description' => self::DESCRIPTIONS[$g['title']] ?? null, 'lessons' => $lessons];
            }
        }

        file_put_contents($out.'/courses.json', json_encode($courses, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $n = collect($courses)->sum(fn ($c) => count($c['lessons']));
        $this->info(count($courses)." cursos, {$n} aulas gravadas em {$out}");
        if ($missingVisual) {
            $this->warn('Sem visual (só texto): '.count($missingVisual));
            foreach ($missingVisual as $h) {
                $this->line("  - {$h}");
            }
        }

        return self::SUCCESS;
    }

    private function slug(string $href): string
    {
        return str_replace(['/', '.html'], ['__', ''], trim($href, '/'));
    }

    /** @return array<int, array{title:string, items:array<int, array{title:string, href:string}>}> */
    private function parseNav(string $js): array
    {
        $groups = [];
        foreach (preg_split('/\n\s*\{\s*\n\s*group:/', $js) as $i => $chunk) {
            if ($i === 0 || ! preg_match('/^\s*"([^"]+)"/', $chunk, $g)) {
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

    /** Resumo = meta description da pagina. */
    private function summaryOf(string $file): ?string
    {
        if (! is_file($file)) {
            return null;
        }

        return preg_match('/<meta name="description" content="([^"]+)"/', (string) file_get_contents($file), $m)
            ? html_entity_decode($m[1], ENT_QUOTES)
            : null;
    }

    /**
     * Texto da pagina pronto pra aula: so' o miolo (sem titulo, breadcrumb, "veja tambem" nem
     * vitrines de links); "Menu: ..." vira uma etiqueta; callouts viram avisos coloridos; todos
     * os links viram texto puro (a aula e' autossuficiente); trechos com assunto interno saem.
     */
    private function bodyOf(string $file, array $dropContaining = []): string
    {
        if (! is_file($file) || ! preg_match('/<main[^>]*class="content"[^>]*>(.*?)<\/main>/s', (string) file_get_contents($file), $m)) {
            return '';
        }

        $content = (string) preg_replace(array_keys(self::REWORD), array_values(self::REWORD), $m[1]);
        $dom = new \DOMDocument;
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8" ?><div id="root">'.$content.'</div>');
        libxml_clear_errors();
        $xp = new \DOMXPath($dom);
        $remove = fn (string $query) => collect(iterator_to_array($xp->query($query)))->each(fn ($n) => $n->parentNode?->removeChild($n));
        $cls = fn (string $c) => "//*[contains(concat(' ', normalize-space(@class), ' '), ' {$c} ')]";

        foreach (['script', 'style', 'footer', 'h1', 'figure', 'img'] as $tag) {
            $remove('//'.$tag);
        }
        foreach (['breadcrumb', 'eyebrow', 'see-also', 'stat-row', 'card-grid', 'guide-list', 'hero', 'search-hero', 'home-section', 'lede'] as $c) {
            $remove($cls($c));
        }

        // "Menu: ..." -> uma etiqueta por pill (cada uma checada contra os termos internos)
        foreach (iterator_to_array($xp->query($cls('pill-row'))) as $row) {
            $pills = iterator_to_array($xp->query('.//*[contains(@class,"pill")]', $row));
            $texts = $pills ? array_map(fn ($n) => trim(preg_replace('/\s+/', ' ', $n->textContent)), $pills) : [trim(preg_replace('/\s+/', ' ', $row->textContent))];
            foreach (array_filter($texts, fn ($t) => $t !== '' && ! preg_match(self::FORBIDDEN, $t)) as $t) {
                $div = $dom->createElement('div', $t);
                $div->setAttribute('class', 'mk-menu');
                $row->parentNode->insertBefore($div, $row);
            }
            $row->parentNode->removeChild($row);
        }

        // callouts -> <div class="mk-note tip|warn|who"><strong>titulo</strong><p>...</p></div>
        foreach (iterator_to_array($xp->query($cls('callout'))) as $callout) {
            $kind = str_contains((string) $callout->getAttribute('class'), 'warn') ? 'warn' : (str_contains((string) $callout->getAttribute('class'), 'who') ? 'who' : 'tip');
            $box = $dom->createElement('div');
            $box->setAttribute('class', 'mk-note '.$kind);
            foreach (iterator_to_array($callout->childNodes) as $child) {
                $isTitle = $child instanceof \DOMElement && str_contains((string) $child->getAttribute('class'), 'callout-title');
                if ($isTitle) {
                    $box->appendChild($dom->createElement('strong', trim($child->textContent)));
                } else {
                    $box->appendChild($child);
                }
            }
            $callout->parentNode->replaceChild($box, $callout);
        }

        foreach (iterator_to_array($xp->query('//a')) as $a) {
            while ($a->firstChild) {
                $a->parentNode->insertBefore($a->firstChild, $a);
            }
            $a->parentNode->removeChild($a);
        }

        // assuntos internos: a secao inteira se for o titulo, senao so' o bloco
        foreach (iterator_to_array($xp->query('//h2|//h3')) as $h) {
            if (! $h->parentNode || ! preg_match(self::FORBIDDEN, $h->textContent)) {
                continue;
            }
            $stop = $h->nodeName === 'h2' ? ['h2'] : ['h2', 'h3'];
            $next = $h->nextSibling;
            while ($next && ! ($next instanceof \DOMElement && in_array($next->nodeName, $stop, true))) {
                $after = $next->nextSibling;
                $h->parentNode->removeChild($next);
                $next = $after;
            }
            $h->parentNode->removeChild($h);
        }
        foreach (iterator_to_array($xp->query('//li|//p|//tr|//div[contains(@class,"mk-note")]')) as $n) {
            $dropped = collect($dropContaining)->contains(fn ($s) => str_contains($n->textContent, $s));
            if ($n->parentNode && ($dropped || preg_match(self::FORBIDDEN, $n->textContent))) {
                $n->parentNode->removeChild($n);
            }
        }
        foreach (iterator_to_array($xp->query('//ul|//ol|//tbody|//table')) as $n) {
            if ($n->parentNode && ! trim($n->textContent)) {
                $n->parentNode->removeChild($n);
            }
        }

        // ids de ancora nao servem pra nada na aula (e podem carregar palavras internas)
        foreach (iterator_to_array($xp->query('//*[@id]')) as $n) {
            if ($n->getAttribute('id') !== 'root') {
                $n->removeAttribute('id');
            }
        }
        // titulos que ficaram sem conteudo embaixo (a secao foi descartada)
        foreach (iterator_to_array($xp->query('//h2|//h3')) as $h) {
            $next = $h->nextSibling;
            while ($next && $next instanceof \DOMText && ! trim($next->textContent)) {
                $next = $next->nextSibling;
            }
            if (! $next || ($next instanceof \DOMElement && in_array($next->nodeName, ['h2', 'h3'], true))) {
                $h->parentNode->removeChild($h);
            }
        }

        $html = '';
        foreach ($dom->getElementById('root')->childNodes as $child) {
            $html .= $dom->saveHTML($child);
        }

        return trim(preg_replace('/\s+/', ' ', preg_replace('/<span[^>]*status-dot[^>]*><\/span>/', '', $html)));
    }
}
