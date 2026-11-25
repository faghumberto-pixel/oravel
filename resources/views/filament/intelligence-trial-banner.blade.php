@php
    $trial = auth()->user()?->tenant_id ? \App\Domain\Intelligence\Trial\Services\TrialCiclo::doTenant(auth()->user()->tenant_id) : null;
    $status = $trial?->statusAtual();
    $link = session('intelligence_link');
@endphp
@if ($trial)
    @php
        // Fundo translúcido + texto herdado do tema: legível no modo claro e no escuro (classes novas do Tailwind não existem no CSS compilado).
        $rgb = match ($status->cor()) { 'danger' => '239,68,68', 'warning' => '245,158,11', 'success' => '16,185,129', default => '99,102,241' };
        $contratado = $trial->contratou() && ! $trial->canceled_at;
    @endphp
    <div class="mb-4 rounded-xl px-4 py-3 text-sm" style="background:rgba({{ $rgb }},.14);border:1px solid rgba({{ $rgb }},.5)" data-intelligence-trial-banner>
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
            <a href="{{ $contratado ? route('intelligence.trial.plano') : route('intelligence.trial.contratar') }}" class="rounded-lg px-3 py-1.5 font-semibold" style="background:#4f46e5;color:#fff">
                {{ $contratado ? 'Meu plano' : 'Contratar plano básico' }}
            </a>
        </div>
        @if ($link)
            <p class="mt-2 text-xs">Guarde este link para voltar sem senha: <a class="break-all underline" href="{{ $link }}">{{ $link }}</a></p>
        @endif
    </div>
@endif
