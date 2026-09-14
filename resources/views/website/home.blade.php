@extends('layouts.website')

@section('title', 'Oravel - Gestão Integrada para Locadoras, Frota e Estoque')

@section('content')

<!-- HERO -->
<section class="py-20 md:py-28 bg-gradient-to-b from-blue-50 to-white">
    <div class="wrap text-center">
        <h1 class="text-4xl md:text-5xl lg:text-6xl font-bold mb-6 leading-tight">
            Chega de planilha e sistema desconectado
        </h1>
        <p class="text-lg md:text-xl text-ink-soft max-w-2xl mx-auto mb-10 leading-relaxed">
            Uma plataforma integrada para quem opera equipamento, frota ou estoque no dia a dia. Gestão de verdade, feita por quem sentiu na pele a dor da operação.
        </p>
        <div class="flex gap-4 justify-center flex-wrap">
            <a href="/contato" class="bg-accent text-white px-8 py-4 rounded-lg font-semibold hover:bg-orange-600 transition">
                Solicitar Demonstração
            </a>
            <a href="https://oravel.com.br/segmentos/" class="border-2 border-accent text-accent px-8 py-4 rounded-lg font-semibold hover:bg-orange-50 transition">
                Conheça os Segmentos
            </a>
        </div>
    </div>
</section>

<!-- SOBRE NÓS -->
<section class="py-20 md:py-28 bg-white">
    <div class="wrap">
        <div class="grid md:grid-cols-2 gap-12 items-center">
            <div>
                <h2 class="text-4xl font-bold mb-6">
                    A Oravel nasceu da vivência real
                </h2>
                <p class="text-ink-soft text-lg mb-6 leading-relaxed">
                    Não é um sistema feito por quem nunca pisou numa operação. A Oravel foi criada por quem administrou uma locadora de equipamentos por anos e depois atuou como consultor no setor.
                </p>
                <p class="text-ink-soft text-lg mb-6 leading-relaxed">
                    Sentimos na pele a dor da planilha, do sistema desconectado, do controle manual. Por isso cada funcionalidade do Oravel resolve um problema real — não inventamos necessidades que não existem.
                </p>
                <p class="text-ink-soft text-lg leading-relaxed">
                    Gestão integrada é mais que um slogan. É a realidade de quem precisa rastrear frota, estoque, vendas e pessoas em um só lugar, sem planilha paralela ou anotação no papel.
                </p>
            </div>
            <div class="bg-surface rounded-xl p-8 h-96 flex items-center justify-center border border-border">
                <div class="text-center text-ink-faint">
                    [Espaço para imagem ou visual — será preenchido depois]
                </div>
            </div>
        </div>
    </div>
</section>

<!-- SOLUÇÕES POR TIPO -->
<section class="py-20 md:py-28 bg-surface">
    <div class="wrap">
        <div class="text-center mb-16">
            <h2 class="text-4xl font-bold mb-4">Escolha sua solução</h2>
            <p class="text-lg text-ink-soft max-w-2xl mx-auto">Cada módulo resolve um problema específico. Comece com o que você precisa hoje.</p>
        </div>

        <!-- PRODUTOS -->
        <div class="mb-16">
            <h3 class="text-2xl font-bold mb-8 text-navy">Produtos</h3>
            <div class="grid md:grid-cols-2 gap-6">
                <div class="bg-white rounded-xl p-8 border border-border hover:border-accent hover:shadow-lg transition">
                    <h4 class="text-xl font-bold mb-2">Oravel ERP</h4>
                    <p class="text-ink-soft text-sm mb-6">Sistema completo: frota, estoque, vendas, recebimentos, RH e inteligência integrados.</p>
                    <a href="/produtos/erp" class="text-accent font-semibold hover:text-orange-600 transition text-sm">Saiba mais →</a>
                </div>
                <div class="bg-white rounded-xl p-8 border border-border hover:border-accent hover:shadow-lg transition">
                    <h4 class="text-xl font-bold mb-2">Oravel Small</h4>
                    <p class="text-ink-soft text-sm mb-6">Entrada focada para quem está começando: os módulos essenciais de frota e estoque.</p>
                    <a href="/produtos/small" class="text-accent font-semibold hover:text-orange-600 transition text-sm">Saiba mais →</a>
                </div>
            </div>
        </div>

        <!-- SOLUÇÕES MODULARES -->
        <div>
            <h3 class="text-2xl font-bold mb-8 text-navy">Soluções Modulares</h3>
            <div class="grid md:grid-cols-4 gap-4">
                <a href="/solucoes/crm" class="bg-white rounded-lg p-6 border border-border hover:border-accent hover:shadow-md transition text-center">
                    <h5 class="font-bold text-sm mb-2">Oravel CRM</h5>
                    <p class="text-xs text-ink-soft">Gestão de vendas</p>
                </a>
                <a href="/solucoes/frota" class="bg-white rounded-lg p-6 border border-border hover:border-accent hover:shadow-md transition text-center">
                    <h5 class="font-bold text-sm mb-2">Oravel Frota</h5>
                    <p class="text-xs text-ink-soft">Manutenção e rastreamento</p>
                </a>
                <a href="/solucoes/wms" class="bg-white rounded-lg p-6 border border-border hover:border-accent hover:shadow-md transition text-center">
                    <h5 class="font-bold text-sm mb-2">Oravel WMS</h5>
                    <p class="text-xs text-ink-soft">Gestão de estoque</p>
                </a>
                <a href="/solucoes/os" class="bg-white rounded-lg p-6 border border-border hover:border-accent hover:shadow-md transition text-center">
                    <h5 class="font-bold text-sm mb-2">Oravel OS</h5>
                    <p class="text-xs text-ink-soft">Ordem de serviço</p>
                </a>
                <a href="/solucoes/recebimentos" class="bg-white rounded-lg p-6 border border-border hover:border-accent hover:shadow-md transition text-center">
                    <h5 class="font-bold text-sm mb-2">Oravel Recebimentos</h5>
                    <p class="text-xs text-ink-soft">Contas a receber</p>
                </a>
                <a href="/solucoes/rh" class="bg-white rounded-lg p-6 border border-border hover:border-accent hover:shadow-md transition text-center">
                    <h5 class="font-bold text-sm mb-2">Oravel RH</h5>
                    <p class="text-xs text-ink-soft">Gestão de pessoas</p>
                </a>
                <a href="/solucoes/analytics" class="bg-white rounded-lg p-6 border border-border hover:border-accent hover:shadow-md transition text-center">
                    <h5 class="font-bold text-sm mb-2">Oravel Analytics</h5>
                    <p class="text-xs text-ink-soft">Inteligência de dados</p>
                </a>
                <a href="https://oravel.com.br/segmentos/" class="bg-white rounded-lg p-6 border border-border hover:border-accent hover:shadow-md transition text-center">
                    <h5 class="font-bold text-sm mb-2">Por Segmento</h5>
                    <p class="text-xs text-ink-soft">Veja por vertical</p>
                </a>
            </div>
        </div>
    </div>
</section>

<!-- DIFERENCIAIS -->
<section class="py-20 md:py-28 bg-white">
    <div class="wrap">
        <div class="text-center mb-16">
            <h2 class="text-4xl font-bold mb-4">Por que escolher Oravel</h2>
            <p class="text-lg text-ink-soft max-w-2xl mx-auto">Construído por quem operou. Feito para quem opera.</p>
        </div>

        <div class="grid md:grid-cols-3 gap-8">
            <div class="bg-surface rounded-xl p-8 border border-border">
                <div class="text-3xl font-bold text-accent mb-4">01</div>
                <h4 class="text-xl font-bold mb-3">Integração real</h4>
                <p class="text-ink-soft">Não é um conjunto de sistemas desconectados. Um dado entra uma vez, toda empresa acessa.</p>
            </div>
            <div class="bg-surface rounded-xl p-8 border border-border">
                <div class="text-3xl font-bold text-accent mb-4">02</div>
                <h4 class="text-xl font-bold mb-3">Menos digitação</h4>
                <p class="text-ink-soft">Automação onde importa. Menos retrabalho, menos erro manual, mais tempo pra quem opera.</p>
            </div>
            <div class="bg-surface rounded-xl p-8 border border-border">
                <div class="text-3xl font-bold text-accent mb-4">03</div>
                <h4 class="text-xl font-bold mb-3">Escalável</h4>
                <p class="text-ink-soft">Comece pequeno, expanda quando crescer. Estrutura pronta pra crescimento sem limites.</p>
            </div>
            <div class="bg-surface rounded-xl p-8 border border-border">
                <div class="text-3xl font-bold text-accent mb-4">04</div>
                <h4 class="text-xl font-bold mb-3">Suporte que entende</h4>
                <p class="text-ink-soft">Especialista em operação, não call center. Disponível 24/7. Sua empresa é nossa prioridade.</p>
            </div>
            <div class="bg-surface rounded-xl p-8 border border-border">
                <div class="text-3xl font-bold text-accent mb-4">05</div>
                <h4 class="text-xl font-bold mb-3">Mobile first</h4>
                <p class="text-ink-soft">Celular, tablet ou desktop. Funciona online e offline. Seu time em qualquer lugar.</p>
            </div>
            <div class="bg-surface rounded-xl p-8 border border-border">
                <div class="text-3xl font-bold text-accent mb-4">06</div>
                <h4 class="text-xl font-bold mb-3">Implantação rápida</h4>
                <p class="text-ink-soft">90 dias até operação. Estrutura pré-configurada. Seu time produtivo desde o dia um.</p>
            </div>
        </div>
    </div>
</section>

<!-- CTA FINAL -->
<section class="py-20 md:py-28 bg-navy text-white">
    <div class="wrap text-center">
        <h2 class="text-4xl font-bold mb-6">Seu negócio merece uma plataforma integrada</h2>
        <p class="text-lg text-white/80 mb-10 max-w-2xl mx-auto">Centenas de empresas já confiam na Oravel. Locadoras, distribuidoras, construtoras, logísticas. Qual é o seu segmento?</p>
        <a href="#demo" class="inline-block bg-accent text-white px-10 py-4 rounded-lg font-semibold hover:bg-orange-600 transition text-lg">
            Solicitar Demonstração
        </a>
    </div>
</section>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const demoLinks = document.querySelectorAll('a[href="#demo"]');
        demoLinks.forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                alert('Formulário de demonstração em breve');
            });
        });
    });
</script>

@endsection
