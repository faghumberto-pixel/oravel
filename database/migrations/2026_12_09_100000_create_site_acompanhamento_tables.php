<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Acompanhamento da remodelagem do site institucional (painel Central, so super admin):
 * notas por data, status por pagina e pendencias/decisoes. Tabelas GLOBAIS (sem tenant_id):
 * e' controle interno da Oravel, nao dado de cliente. Idempotente: so' semeia se estiver vazio.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_acompanhamento_notas', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->date('data');
            $table->string('titulo')->nullable();
            $table->text('texto');
            $table->string('autor')->nullable();
            $table->timestamps();
            $table->index('data');
        });

        Schema::create('site_acompanhamento_paginas', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('slug')->unique();
            $table->string('nome');
            $table->string('grupo')->nullable();
            $table->string('status')->default('rascunho'); // nao_convertida | rascunho | em_revisao | aprovada | publicada
            $table->text('observacao')->nullable();
            $table->timestamps();
            $table->index('status');
        });

        Schema::create('site_acompanhamento_pendencias', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('titulo');
            $table->text('detalhe')->nullable();
            $table->string('tipo')->default('decisao');   // decisao | correcao | teste | tarefa
            $table->string('status')->default('aberta');  // aberta | em_andamento | concluida
            $table->string('responsavel')->nullable();
            $table->date('prazo')->nullable();
            $table->timestamps();
            $table->index('status');
        });

        $agora = now();
        $paginas = [
            ['Principais', [['/', 'Home'], ['/recursos/', 'Recursos'], ['/software-manutencao-industrial/', 'Manutenção (CMMS)'], ['/processo-logistica/', 'Logística'], ['/erp-cmms/', 'Locação (ERP)'], ['/solucoes/', 'Soluções'], ['/contato.php', 'Contato']]],
            ['Equipamentos', [['/software-manutencao-guindastes/', 'Guindastes'], ['/software-manutencao-munks/', 'Munks'], ['/software-manutencao-plataformas-elevatorias/', 'Plataformas elevatórias'], ['/software-manutencao-empilhadeiras/', 'Empilhadeiras'], ['/software-manutencao-geradores/', 'Geradores'], ['/software-manutencao-compressores/', 'Compressores'], ['/software-manutencao-outros-equipamentos/', 'Outros equipamentos'], ['/guindastes-e-plataformas/', 'Índice de equipamentos']]],
            ['Processos', [['/processo-manutencao/', 'Processo de manutenção'], ['/processo-gestao-de-equipes/', 'Gestão de equipes'], ['/processo-gestao-de-estoques/', 'Gestão de estoques'], ['/processo-cobranca-automatica/', 'Cobrança automática'], ['/nr-13-inspecao-equipamentos/', 'Inspeções NR-13'], ['/equipamento-devolvido-com-defeito/', 'Devolução com defeito']]],
            ['Produtos', [['/wms/', 'Estoque (WMS)'], ['/crm/', 'Vendas (CRM)'], ['/departamento-pessoal/', 'Departamento Pessoal'], ['/os/', 'Ordens de serviço'], ['/software-montagem-industrial/', 'Montagem industrial'], ['/software-gestao-frota-logistica/', 'Frota e logística'], ['/software-gestao-de-servicos/', 'Gestão de serviços']]],
            ['Institucional', [['/sobre/', 'Sobre'], ['/inteligencia-artificial/', 'Inteligência artificial'], ['/academia/', 'Academia'], ['/faq.php', 'Perguntas frequentes'], ['/novidades.php', 'Novidades'], ['/central-de-conhecimento/', 'Central de conhecimento'], ['/central-de-conhecimento/horimetro.php', 'Artigo: horímetro'], ['/central-de-conhecimento/manutencao-preventiva-equipamentos-locacao/', 'Artigo: manutenção preventiva'], ['/politica-de-privacidade/', 'Política de privacidade'], ['/termos-de-uso/', 'Termos de uso']]],
            ['Campanha', [['/locadoras/', 'Locadoras (formulário)'], ['/pequenas-locadoras.html', 'Pequenas locadoras'], ['/gestao-locadoras/', 'Gestão de locadoras'], ['/para-quem-tem-sistema.php', 'Para quem já tem sistema'], ['/para-quem-nao-tem-sistema.php', 'Para quem não tem sistema']]],
        ];
        $rows = [];
        foreach ($paginas as [$grupo, $itens]) {
            foreach ($itens as [$slug, $nome]) {
                $rows[] = ['id' => (string) Str::uuid(), 'slug' => $slug, 'nome' => $nome, 'grupo' => $grupo, 'status' => 'publicada', 'observacao' => null, 'created_at' => $agora, 'updated_at' => $agora];
            }
        }
        foreach ([['/index-en.html', 'Home em inglês', 'Ainda no padrão antigo.'], ['/planos/', 'Planos', 'Tem preços; fora do menu e do rodapé. Decidir o destino.'], ['/proposta/', 'Proposta', 'Formulário próprio; decidir se converte.']] as [$slug, $nome, $obs]) {
            $rows[] = ['id' => (string) Str::uuid(), 'slug' => $slug, 'nome' => $nome, 'grupo' => 'Fora do novo padrão', 'status' => 'nao_convertida', 'observacao' => $obs, 'created_at' => $agora, 'updated_at' => $agora];
        }
        DB::table('site_acompanhamento_paginas')->insert($rows);

        $pend = [
            ['decisao', 'Dizer quais funções ajustar ou tirar dos textos', 'Hoje os textos usam só o que o sistema faz. Falta o dono indicar quais funções entram, saem ou mudam.'],
            ['teste', 'Testar o formulário de /contato.php com envio real', 'Conferir a mensagem de sucesso, o e-mail recebido e o lead no CRM.'],
            ['teste', 'Testar o formulário de /locadoras/ e o evento de conversão', 'Conferir o lead por e-mail e no CRM e o evento oravelLead(locadoras) no Google Ads.'],
            ['tarefa', 'Cloudflare: limpar o cache dos favicons', 'favicon.ico, apple-touch-icon.png, favicon.svg e a pasta /nova/assets/ ainda saem do cache antigo (até 7 dias).'],
            ['tarefa', 'Search Console: reindexar a home e remover /erp/locadoras', 'Pedir a indexação da home e remover a URL antiga, que hoje dá 404.'],
            ['decisao', 'Decidir o destino de /planos/ e /proposta/', '/planos/ tem preços e está fora do menu e do rodapé; /proposta/ tem formulário próprio.'],
            ['tarefa', 'Converter a home em inglês (index-en.html)', 'Ainda está no padrão antigo.'],
            ['decisao', 'Validar o porte ideal e a frase sobre compressores e NR-13', 'Há comentários VALIDAR no HTML das páginas de equipamentos.'],
            ['decisao', 'Manter ou não o convite de 30 dias grátis nos textos', 'O botão "Testar 30 dias" saiu; o convite continua em alguns textos.'],
            ['decisao', 'Nota fiscal e contabilidade nos textos', 'A Oravel não emite nota fiscal nem faz contabilidade. Os cartões de Fiscal e Contabilidade foram retirados de /locadoras/.'],
        ];
        DB::table('site_acompanhamento_pendencias')->insert(array_map(fn ($p) => [
            'id' => (string) Str::uuid(), 'tipo' => $p[0], 'titulo' => $p[1], 'detalhe' => $p[2], 'status' => 'aberta',
            'responsavel' => null, 'prazo' => null, 'created_at' => $agora, 'updated_at' => $agora,
        ], $pend));

        DB::table('site_acompanhamento_notas')->insert([
            'id' => (string) Str::uuid(), 'data' => $agora->toDateString(), 'titulo' => 'Novo site no ar',
            'texto' => 'Novo padrão publicado em oravel.com.br: home, Recursos, Manutenção (CMMS), Logística, Soluções, equipamentos, processos, produtos, institucionais, FAQ, central de conhecimento, páginas legais e páginas de campanha. Logotipo Oravel com as 3 bolinhas, barra de menus azul-escura, botão único "Agendar uma demonstração", WhatsApp flutuante e o mesmo formulário de contato. Fora do padrão: home em inglês, planos e proposta.',
            'autor' => 'Claude', 'created_at' => $agora, 'updated_at' => $agora,
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('site_acompanhamento_pendencias');
        Schema::dropIfExists('site_acompanhamento_paginas');
        Schema::dropIfExists('site_acompanhamento_notas');
    }
};
