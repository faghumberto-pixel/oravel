@extends('intelligence-trial.layout')
@section('titulo', 'Meu plano')
@section('conteudo')
    <h1 class="text-2xl font-bold">Meu plano</h1>
    @if (session('ok'))<div class="mt-4 rounded-lg bg-emerald-500/20 p-3 text-sm">{{ session('ok') }}</div>@endif
    @if ($errors->any())<div class="mt-4 rounded-lg bg-red-500/20 p-3 text-sm text-red-100">@foreach ($errors->all() as $e)<p>{{ $e }}</p>@endforeach</div>@endif

    <p class="mt-4 text-sm"><span class="rounded-full bg-white/10 px-3 py-1 font-semibold">{{ $status->rotulo() }}</span></p>
    <div class="mt-4 space-y-1 text-sm text-white/80">
        @if ($trial->trial_ends_at)<p>Teste até {{ $trial->trial_ends_at->format('d/m/Y') }} ({{ $trial->diasRestantes() }} dia(s)).</p>@else<p>O teste começa quando você enviar a primeira planilha.</p>@endif
        @if ($trial->contratou() && ! $trial->canceled_at)<p>Plano contratado. Primeira cobrança em {{ $trial->billing_starts_on->format('d/m/Y') }}.</p>@endif
        @if ($trial->canceled_at)<p>Cancelado. Acesso até {{ $trial->access_until?->format('d/m/Y') }}.</p>@endif
        @if ($trial->somenteLeitura())<p class="text-amber-300">Somente leitura: histórico e painéis visíveis, novos envios bloqueados.</p>@endif
    </div>

    <div class="mt-6 space-y-3">
        @if (! $trial->contratou() || $trial->canceled_at)
            <a href="{{ route('intelligence.trial.contratar') }}" class="block rounded-lg bg-indigo-500 px-4 py-3 text-center font-semibold hover:bg-indigo-400">Contratar plano básico</a>
        @else
            <form method="POST" action="{{ route('intelligence.trial.cancelar') }}" onsubmit="return confirm('Cancelar o plano? Você mantém o acesso até o fim do período pago.')">
                @csrf <button class="w-full rounded-lg bg-white/10 px-4 py-3 text-sm hover:bg-white/20">Cancelar plano</button>
            </form>
        @endif
        @if ($contrato)<a class="block text-center text-sm underline" href="{{ route('intelligence.trial.contrato', $contrato->id) }}">Baixar contrato aceito (PDF)</a>@endif
        <a class="block text-center text-sm text-white/60 underline" href="{{ \App\Filament\Resources\IntelligenceImportResource::getUrl('index') }}">Voltar ao sistema</a>
    </div>
@endsection
