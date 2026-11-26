@php
    $trial = auth()->user()?->tenant_id ? \App\Domain\Intelligence\Trial\Services\TrialCiclo::doTenant(auth()->user()->tenant_id) : null;
    $status = $trial?->statusAtual();
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
                    <strong>Teste grátis de {{ config('intelligence_trial.dias') }} dias.</strong> Os dias começam a contar no seu próximo acesso.
                @endif
            </p>
            <a href="{{ $contratado ? route('intelligence.trial.plano') : route('intelligence.trial.contratar') }}" class="rounded-lg px-3 py-1.5 font-semibold" style="background:#4f46e5;color:#fff">
                {{ $contratado ? 'Meu plano' : 'Contratar plano básico' }}
            </a>
        </div>
    </div>
    @php
        $tenantId = (string) auth()->user()->tenant_id;
        $temExemplo = \App\Domain\Intelligence\Services\DadosDeExemplo::existe($tenantId);
        $podeGerar = ! $temExemplo && \App\Domain\Intelligence\Services\DadosDeExemplo::podeGerar($trial);
        $diasExemplo = \App\Domain\Intelligence\Services\DadosDeExemplo::diasRestantes($trial);
    @endphp
    @if ($temExemplo)
        <div class="mb-4 rounded-xl px-4 py-3 text-sm" style="background:rgba(245,158,11,.14);border:1px solid rgba(245,158,11,.5)" data-intelligence-exemplo>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <p><strong>Você está vendo dados de DEMONSTRAÇÃO.</strong> Eles são fictícios e servem só para mostrar como as telas ficam com dados. Limpe-os assim que for começar com os seus{{ $diasExemplo !== null ? ' (somem sozinhos em '.$diasExemplo.' dia(s))' : '' }}.</p>
                <form method="POST" action="{{ route('intelligence.trial.exemplo.limpar') }}" onsubmit="return confirm('Apagar os dados de exemplo? Os seus próprios dados não são afetados.')">
                    @csrf
                    <button class="rounded-lg px-3 py-1.5 font-semibold" style="background:#d97706;color:#fff">Limpar dados de exemplo</button>
                </form>
            </div>
        </div>
    @elseif ($podeGerar)
        <div class="mb-4 text-right text-xs" data-intelligence-exemplo-voltar>
            <form method="POST" action="{{ route('intelligence.trial.exemplo.gerar') }}" class="inline">@csrf
                <button class="underline opacity-80">Ver dados de exemplo</button>
            </form>
        </div>
    @endif
@endif
