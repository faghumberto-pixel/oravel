<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Oravel Colaborador</title>
    @vite(['resources/css/app.css'])
</head>
<body class="flex min-h-screen items-center justify-center bg-zinc-950 p-6 font-sans text-zinc-100">
    <div class="max-w-sm text-center">
        <div class="text-2xl font-black">O<span class="text-orange-500">r</span>avel</div>
        <h1 class="mt-6 text-xl font-bold">Abra no celular</h1>
        <p class="mt-2 text-sm text-zinc-400">Este aplicativo funciona somente no celular. Aponte a câmera para o QR abaixo.</p>
        <div class="mx-auto mt-6 inline-block rounded-2xl bg-white p-3">{!! $qr !!}</div>
        <p class="mt-4 break-all text-xs text-zinc-500">{{ $url }}</p>
    </div>
</body>
</html>
