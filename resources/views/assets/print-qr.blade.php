<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Etiqueta: {{ $asset->name }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="p-8 flex flex-col items-center justify-center min-h-screen">
    <div class="border-2 border-black p-6 rounded-lg text-center shadow-lg">
        <h1 class="text-2xl font-bold uppercase">{{ $asset->name }}</h1>
        <p class="text-lg">Patrimônio: {{ $asset->patrimonio }}</p>
        <div class="my-6">
            {!! QrCode::size(250)->generate(route('asset.scan', $asset->id)) !!}
        </div>
        <p class="text-sm">Escaneie para acessar o Prontuário</p>
        <button onclick="window.print()" class="mt-4 bg-blue-600 text-white px-6 py-2 rounded no-print">Imprimir Etiqueta</button>
    </div>
    <style>@media print { .no-print { display: none; } }</style>
</body>
</html>
