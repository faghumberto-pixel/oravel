<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>@yield('titulo', 'Oravel Intelligence')</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="antialiased bg-slate-950 text-white">
    <div class="min-h-screen flex flex-col items-center justify-center px-4 py-10">
        <a href="https://oravel.com.br/intelligence" class="mb-6 text-2xl font-bold">Oravel <span class="text-indigo-400">Intelligence</span></a>
        <div class="w-full max-w-md rounded-3xl border border-white/15 bg-white/5 p-6 shadow-2xl sm:p-8">
            @yield('conteudo')
        </div>
    </div>
</body>
</html>
