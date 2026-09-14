<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Oravel - Gestão Integrada para Locadoras, Frota e Estoque')</title>
    <meta name="description" content="@yield('description', 'Plataforma SaaS de gestão integrada para locadoras de equipamento, empresas de construção civil, logística e distribuidoras.')">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@500;600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --paper: #ffffff;
            --surface: #f6f7f9;
            --ink: #0f172a;
            --ink-soft: #5b6472;
            --ink-faint: #8891a0;
            --border: #e4e7ec;
            --accent: #f97316;
            --navy: #0f172a;
        }
        * { box-sizing: border-box; }
        body {
            font-family: "Inter", "Segoe UI", system-ui, sans-serif;
            color: var(--ink);
            background: var(--paper);
            -webkit-font-smoothing: antialiased;
        }
        h1, h2, h3 {
            font-family: "Manrope", "Inter", sans-serif;
            font-weight: 800;
            letter-spacing: -0.02em;
            text-wrap: balance;
        }
        a { color: inherit; text-decoration: none; }
        .wrap { max-width: 1180px; margin: 0 auto; padding: 0 28px; }
    </style>
</head>
<body>
    <header class="sticky top-0 z-50 bg-white/92 backdrop-blur-md border-b border-gray-200">
        <nav class="wrap h-18 flex items-center justify-between">
            <a href="/" class="logo text-xl font-bold">
                <span class="text-navy">O</span><span class="text-accent">r</span><span class="text-navy">avel</span>
            </a>

            <div class="hidden md:flex items-center gap-8">
                <!-- Dropdown Produtos -->
                <div class="relative group">
                    <button class="text-sm font-semibold text-ink-soft hover:text-ink transition">Produtos</button>
                    <div class="absolute left-0 mt-0 w-48 bg-white border border-border rounded-lg shadow-lg opacity-0 invisible group-hover:opacity-100 group-hover:visible transition">
                        <a href="/produtos/erp" class="block px-4 py-3 hover:bg-surface text-sm">Oravel ERP</a>
                        <a href="/produtos/small" class="block px-4 py-3 hover:bg-surface text-sm border-t border-border">Oravel Small</a>
                    </div>
                </div>

                <!-- Dropdown Soluções -->
                <div class="relative group">
                    <button class="text-sm font-semibold text-ink-soft hover:text-ink transition">Soluções</button>
                    <div class="absolute left-0 mt-0 w-56 bg-white border border-border rounded-lg shadow-lg opacity-0 invisible group-hover:opacity-100 group-hover:visible transition">
                        <a href="/solucoes/crm" class="block px-4 py-3 hover:bg-surface text-sm">Oravel CRM</a>
                        <a href="/solucoes/frota" class="block px-4 py-3 hover:bg-surface text-sm border-t border-border">Oravel Frota</a>
                        <a href="/solucoes/wms" class="block px-4 py-3 hover:bg-surface text-sm border-t border-border">Oravel WMS</a>
                        <a href="/solucoes/os" class="block px-4 py-3 hover:bg-surface text-sm border-t border-border">Oravel OS</a>
                        <a href="/solucoes/recebimentos" class="block px-4 py-3 hover:bg-surface text-sm border-t border-border">Oravel Recebimentos</a>
                        <a href="/solucoes/rh" class="block px-4 py-3 hover:bg-surface text-sm border-t border-border">Oravel RH</a>
                        <a href="/solucoes/analytics" class="block px-4 py-3 hover:bg-surface text-sm border-t border-border">Oravel Analytics</a>
                    </div>
                </div>

                <a href="https://oravel.com.br/segmentos/" class="text-sm font-semibold text-ink-soft hover:text-ink transition">Segmentos</a>
                <a href="/sobre" class="text-sm font-semibold text-ink-soft hover:text-ink transition">Sobre Nós</a>
            </div>

            <a href="/contato" class="bg-accent text-white px-6 py-2 rounded-lg font-semibold text-sm hover:bg-orange-600 transition">
                Solicitar Demonstração
            </a>
        </nav>
    </header>

    <main>
        @yield('content')
    </main>

    <footer class="border-t border-border py-12 mt-20">
        <div class="wrap">
            <div class="grid md:grid-cols-4 gap-8 mb-8">
                <div>
                    <div class="font-bold text-lg mb-4">
                        <span class="text-navy">O</span><span class="text-accent">r</span><span class="text-navy">avel</span>
                    </div>
                    <p class="text-sm text-ink-soft">Gestão integrada para quem opera equipamento, frota ou estoque no dia a dia.</p>
                </div>
                <div>
                    <h4 class="font-semibold mb-4 text-sm">Produtos</h4>
                    <ul class="text-sm text-ink-soft space-y-2">
                        <li><a href="/produtos/erp" class="hover:text-ink">Oravel ERP</a></li>
                        <li><a href="/produtos/small" class="hover:text-ink">Oravel Small</a></li>
                    </ul>
                </div>
                <div>
                    <h4 class="font-semibold mb-4 text-sm">Soluções</h4>
                    <ul class="text-sm text-ink-soft space-y-2">
                        <li><a href="/solucoes/crm" class="hover:text-ink">CRM</a></li>
                        <li><a href="/solucoes/frota" class="hover:text-ink">Frota</a></li>
                        <li><a href="/solucoes/wms" class="hover:text-ink">WMS</a></li>
                        <li><a href="/solucoes/os" class="hover:text-ink">OS</a></li>
                    </ul>
                </div>
                <div>
                    <h4 class="font-semibold mb-4 text-sm">Empresa</h4>
                    <ul class="text-sm text-ink-soft space-y-2">
                        <li><a href="https://oravel.com.br/segmentos/" class="hover:text-ink">Segmentos</a></li>
                        <li><a href="/sobre" class="hover:text-ink">Sobre Nós</a></li>
                        <li><a href="#demo" class="hover:text-ink">Contato</a></li>
                    </ul>
                </div>
            </div>
            <div class="border-t border-border pt-8 flex justify-between items-center">
                <p class="text-xs text-ink-faint">© 2026 Oravel Software. Todos os direitos reservados.</p>
                <p class="text-xs text-ink-faint">Desenvolvido por quem entende de operação.</p>
            </div>
        </div>
    </footer>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const demoButton = document.querySelector('a[href="#demo"]');
            if (demoButton) {
                demoButton.addEventListener('click', function(e) {
                    e.preventDefault();
                    alert('Formulário de demonstração em breve');
                });
            }
        });
    </script>
</body>
</html>
