@extends('intelligence-trial.layout')
@section('titulo', 'Contratar plano básico')
@section('conteudo')
    <h1 class="text-2xl font-bold">Contratar plano básico</h1>
    <p class="mt-2 text-sm text-white/70">
        R$ {{ number_format(config('intelligence_trial.plano.preco'), 2, ',', '.') }} por mês.
        Primeira cobrança em <strong>{{ $inicio->format('d/m/Y') }}</strong>. Nada é cobrado antes disso.
    </p>

    @if ($errors->any())
        <div class="mt-4 rounded-lg bg-red-500/20 p-3 text-sm text-red-100">@foreach ($errors->all() as $erro)<p>{{ $erro }}</p>@endforeach</div>
    @endif

    <form method="POST" action="{{ route('intelligence.trial.contratar.store') }}" class="mt-6 space-y-3">
        @csrf
        @foreach ([['razao_social', 'Razão social'], ['cnpj', 'CNPJ'], ['email', 'E-mail'], ['responsavel', 'Responsável']] as [$campo, $rotulo])
            <label class="block text-sm">{{ $rotulo }}
                <input name="{{ $campo }}" value="{{ old($campo) }}" required class="mt-1 w-full rounded-lg border-0 bg-white/10 px-3 py-2 text-white">
            </label>
        @endforeach
        <label class="block text-sm">Forma de pagamento preferida
            <select name="forma_pagamento" class="mt-1 w-full rounded-lg border-0 bg-slate-800 px-3 py-2 text-white">
                <option value="UNDEFINED">Escolho ao receber a cobrança</option>
                <option value="PIX" @selected(old('forma_pagamento') === 'PIX')>Pix</option>
                <option value="BOLETO" @selected(old('forma_pagamento') === 'BOLETO')>Boleto</option>
                <option value="CREDIT_CARD" @selected(old('forma_pagamento') === 'CREDIT_CARD')>Cartão</option>
            </select>
        </label>
        <details class="rounded-lg bg-white/5 p-3 text-xs text-white/80">
            <summary class="cursor-pointer font-semibold">Ler o contrato</summary>
            <pre class="mt-2 whitespace-pre-wrap font-sans">{{ $texto }}</pre>
        </details>
        <label class="flex items-start gap-2 text-xs text-white/80">
            <input type="checkbox" name="aceite_contrato" value="1" required class="mt-0.5"> Li e aceito o contrato.
        </label>
        <button class="w-full rounded-lg bg-indigo-500 px-4 py-3 font-semibold hover:bg-indigo-400">Aceitar e contratar</button>
    </form>
    <p class="mt-4 text-center text-xs"><a class="underline text-white/60" href="{{ \App\Filament\Resources\IntelligenceImportResource::getUrl('index') }}">Voltar ao sistema</a></p>
@endsection
