@php
    $trial = auth()->user()?->tenant_id ? \App\Domain\Intelligence\Trial\Services\TrialCiclo::doTenant(auth()->user()->tenant_id) : null;
    $status = $trial?->statusAtual();
    $link = session('intelligence_link');
@endphp
@if ($trial)
    @php
        $cor = match ($status->cor()) { 'danger' => 'bg-red-50 text-red-900 ring-red-200', 'warning' => 'bg-amber-50 text-amber-900 ring-amber-200', 'success' => 'bg-emerald-50 text-emerald-900 ring-emerald-200', default => 'bg-indigo-50 text-indigo-900 ring-indigo-200' };
        $contratado = $trial->contratou() && ! $trial->canceled_at;
    @endphp
    <div class="mb-4 rounded-xl px-4 py-3 text-sm ring-1 {{ $cor }}" data-intelligence-trial-banner>
        <div class="flex flex-wrap items-center justify-between gap-3">
            <p>
                @if ($trial->somenteLeitura())
                    <strong>Somente leitura.</strong> Seu histórico e painéis continuam visíveis, mas novos envios estão bloqueados.
                @elseif ($status === \App\Domain\Intelligence\Trial\Enums\StatusConta::Active)
                    <strong>Plano básico ativo.</strong>
                @elseif ($status === \App\Domain\Intelligence\Trial\Enums\StatusConta::Overdue)
                    <strong>Pagamento em atraso.</strong> Regularize para não perder o envio de planilhas.
                @elseif ($status === \App\Domain\Intelligence\Trial\Enums\StatusConta::Canceled)
                    Plano cancelado. Acesso até {{ $trial->access_until?->format('d/m/Y') }}.
                @elseif ($contratado)
                    <strong>Plano contratado.</strong> A cobrança começa em {{ $trial->billing_starts_on->format('d/m/Y') }}.
                @elseif ($trial->trial_ends_at)
                    <strong>Teste grátis:</strong> {{ $trial->diasRestantes() }} dia(s) restantes (até {{ $trial->trial_ends_at->format('d/m/Y') }}).
                @else
                    <strong>Teste grátis de {{ config('intelligence_trial.dias') }} dias.</strong> Os dias começam a contar quando você enviar a primeira planilha.
                @endif
            </p>
            <a href="{{ $contratado ? route('intelligence.trial.plano') : route('intelligence.trial.contratar') }}" class="rounded-lg bg-indigo-600 px-3 py-1.5 font-semibold text-white">
                {{ $contratado ? 'Meu plano' : 'Contratar plano básico' }}
            </a>
        </div>
        @if ($link)
            <p class="mt-2 text-xs">Guarde este link para voltar sem senha: <a class="break-all underline" href="{{ $link }}">{{ $link }}</a></p>
        @endif
    </div>
@endif
