@extends('layouts.website')
@section('title', 'Ordem de Serviço - Oravel')
@section('content')

<section class="py-20 md:py-32 bg-gradient-to-b from-red-50 to-white">
    <div class="wrap">
        <div class="max-w-3xl">
            <h1 class="text-5xl md:text-6xl font-bold text-red-950 mb-6">Gestão Completa de Ordem de Serviço</h1>
            <p class="text-xl text-red-800 mb-8">Centralize, organize e acompanhe todas as suas ordens de serviço em tempo real. Integração total com manutenção, estoque e financeiro.</p>
            <a href="/contato" class="inline-block bg-red-600 text-white px-8 py-4 rounded-lg font-semibold hover:bg-red-700 transition">Solicitar Demonstração</a>
        </div>
    </div>
</section>

<section class="py-16 md:py-24 bg-white">
    <div class="wrap">
        <h2 class="text-3xl font-bold mb-12">Funcionalidades Principais</h2>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
            <div class="p-6 bg-gray-50 rounded-lg">
                <div class="text-3xl mb-4">📋</div>
                <h3 class="text-lg font-semibold mb-3">Criação Rápida de OS</h3>
                <p class="text-gray-600">Crie ordens de serviço em segundos com templates predefinidos e checklist automático.</p>
            </div>
            <div class="p-6 bg-gray-50 rounded-lg">
                <div class="text-3xl mb-4">🔄</div>
                <h3 class="text-lg font-semibold mb-3">Rastreamento em Tempo Real</h3>
                <p class="text-gray-600">Acompanhe o status de cada OS desde a criação até o fechamento, com histórico completo.</p>
            </div>
            <div class="p-6 bg-gray-50 rounded-lg">
                <div class="text-3xl mb-4">📦</div>
                <h3 class="text-lg font-semibold mb-3">Controle de Peças</h3>
                <p class="text-gray-600">Integre automaticamente o uso de peças ao seu estoque e atualize custos em tempo real.</p>
            </div>
            <div class="p-6 bg-gray-50 rounded-lg">
                <div class="text-3xl mb-4">👥</div>
                <h3 class="text-lg font-semibold mb-3">Atribuição de Técnicos</h3>
                <p class="text-gray-600">Aloque técnicos automaticamente e acompanhe a produtividade de cada um.</p>
            </div>
            <div class="p-6 bg-gray-50 rounded-lg">
                <div class="text-3xl mb-4">💰</div>
                <h3 class="text-lg font-semibold mb-3">Faturamento Automático</h3>
                <p class="text-gray-600">Gere faturas e recibos automaticamente a partir das ordens de serviço concluídas.</p>
            </div>
            <div class="p-6 bg-gray-50 rounded-lg">
                <div class="text-3xl mb-4">📊</div>
                <h3 class="text-lg font-semibold mb-3">Relatórios e Analytics</h3>
                <p class="text-gray-600">Dashboards com KPIs de produtividade, custos e qualidade de serviço.</p>
            </div>
        </div>
    </div>
</section>

<section class="py-16 md:py-24 bg-gray-50">
    <div class="wrap">
        <h2 class="text-3xl font-bold mb-12">Por que escolher Oravel?</h2>
        <div class="space-y-8">
            <div class="flex gap-6">
                <div class="flex-shrink-0">
                    <div class="w-12 h-12 bg-red-600 text-white rounded-lg flex items-center justify-center font-bold">✓</div>
                </div>
                <div>
                    <h3 class="text-xl font-semibold mb-2">Integração Total com Estoque</h3>
                    <p class="text-gray-600">Peças e materiais utilizados nas OSs são automaticamente abatidos do seu inventário.</p>
                </div>
            </div>
            <div class="flex gap-6">
                <div class="flex-shrink-0">
                    <div class="w-12 h-12 bg-red-600 text-white rounded-lg flex items-center justify-center font-bold">✓</div>
                </div>
                <div>
                    <h3 class="text-xl font-semibold mb-2">Mobile-First</h3>
                    <p class="text-gray-600">Técnicos acessam OSs pelo celular no campo, com modo offline completo.</p>
                </div>
            </div>
            <div class="flex gap-6">
                <div class="flex-shrink-0">
                    <div class="w-12 h-12 bg-red-600 text-white rounded-lg flex items-center justify-center font-bold">✓</div>
                </div>
                <div>
                    <h3 class="text-xl font-semibold mb-2">Conformidade e Segurança</h3>
                    <p class="text-gray-600">Assinatura digital de OSs, rastreabilidade completa e auditoria de todas as ações.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="py-16 md:py-24 bg-white">
    <div class="wrap">
        <h2 class="text-3xl font-bold mb-12">Planos e Preços</h2>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
            <div class="border border-gray-200 rounded-lg p-8">
                <h3 class="text-lg font-semibold mb-4">Básico</h3>
                <p class="text-gray-600 mb-6">Ideal para pequenos negócios</p>
                <ul class="space-y-3 mb-8">
                    <li class="flex items-center"><span class="mr-3">✓</span> Até 100 OSs/mês</li>
                    <li class="flex items-center"><span class="mr-3">✓</span> 5 usuários</li>
                    <li class="flex items-center"><span class="mr-3">✓</span> Suporte por email</li>
                </ul>
                <button class="w-full bg-gray-100 text-gray-800 py-2 rounded-lg font-semibold hover:bg-gray-200">Solicitar Info</button>
            </div>
            <div class="border-2 border-red-600 rounded-lg p-8 relative">
                <div class="absolute top-0 right-0 bg-red-600 text-white px-4 py-1 rounded-bl-lg text-sm font-semibold">Recomendado</div>
                <h3 class="text-lg font-semibold mb-4">Profissional</h3>
                <p class="text-gray-600 mb-6">Para empresas em crescimento</p>
                <ul class="space-y-3 mb-8">
                    <li class="flex items-center"><span class="mr-3">✓</span> Até 500 OSs/mês</li>
                    <li class="flex items-center"><span class="mr-3">✓</span> 20 usuários</li>
                    <li class="flex items-center"><span class="mr-3">✓</span> Suporte 24/7</li>
                </ul>
                <button class="w-full bg-red-600 text-white py-2 rounded-lg font-semibold hover:bg-red-700">Solicitar Info</button>
            </div>
            <div class="border border-gray-200 rounded-lg p-8">
                <h3 class="text-lg font-semibold mb-4">Enterprise</h3>
                <p class="text-gray-600 mb-6">Customizado para sua operação</p>
                <ul class="space-y-3 mb-8">
                    <li class="flex items-center"><span class="mr-3">✓</span> Ilimitado</li>
                    <li class="flex items-center"><span class="mr-3">✓</span> Usuários ilimitados</li>
                    <li class="flex items-center"><span class="mr-3">✓</span> Suporte dedicado</li>
                </ul>
                <button class="w-full bg-gray-100 text-gray-800 py-2 rounded-lg font-semibold hover:bg-gray-200">Solicitar Info</button>
            </div>
        </div>
    </div>
</section>

<section class="py-16 md:py-24 bg-red-50">
    <div class="wrap">
        <h2 class="text-3xl font-bold text-center mb-12">O que dizem nossos clientes</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
            <div class="bg-white p-8 rounded-lg">
                <div class="flex items-center mb-4">
                    <span class="text-yellow-400">★★★★★</span>
                </div>
                <p class="text-gray-700 mb-4">"A Oravel revolucionou nosso processo de OSs. O que levava 2 horas agora leva 20 minutos. Excelente!"</p>
                <p class="font-semibold text-gray-900">João Silva</p>
                <p class="text-sm text-gray-600">Gerente de Manutenção - Frota Brasil</p>
            </div>
            <div class="bg-white p-8 rounded-lg">
                <div class="flex items-center mb-4">
                    <span class="text-yellow-400">★★★★★</span>
                </div>
                <p class="text-gray-700 mb-4">"Reduzimos erros de digitação em 95% e o controle de peças ficou muito mais preciso. Recomendo!"</p>
                <p class="font-semibold text-gray-900">Maria Oliveira</p>
                <p class="text-sm text-gray-600">Diretora Operacional - Locadora XYZ</p>
            </div>
        </div>
    </div>
</section>

<section class="py-16 md:py-24 bg-white">
    <div class="wrap text-center">
        <h2 class="text-3xl font-bold mb-6">Pronto para simplificar suas ordens de serviço?</h2>
        <p class="text-lg text-gray-600 mb-8">Solicite uma demonstração gratuita e veja na prática como a Oravel pode transformar sua operação.</p>
        <a href="/contato" class="inline-block bg-red-600 text-white px-8 py-4 rounded-lg font-semibold hover:bg-red-700 transition">Agendar Demonstração</a>
    </div>
</section>

@endsection
