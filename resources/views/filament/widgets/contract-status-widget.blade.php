<div class="fi-wi-stats-overview-stat relative rounded-lg border p-6 shadow-sm
    @if ($isOverdue)
        border-red-300 bg-red-50 dark:border-red-700 dark:bg-red-900
    @elseif ($hasSignature)
        border-green-300 bg-green-50 dark:border-green-700 dark:bg-green-900
    @else
        border-amber-300 bg-amber-50 dark:border-amber-700 dark:bg-amber-900
    @endif
">
    <div class="flex items-start justify-between gap-3">
        <div>
            @if ($hasSignature)
                <p class="text-sm font-medium text-green-700 dark:text-green-300">
                    ✅ Contrato SLA + LGPD
                </p>
                <p class="mt-2 text-lg font-semibold text-gray-900 dark:text-white">
                    Assinado
                </p>
                <p class="mt-1 text-xs text-gray-600 dark:text-gray-300">
                    Por: <strong>{{ $signerName }}</strong>
                </p>
                <p class="text-xs text-gray-600 dark:text-gray-300">
                    Em: {{ $signedAt?->format('d/m/Y H:i') }}
                </p>
            @elseif ($isOverdue)
                <p class="text-sm font-medium text-red-700 dark:text-red-300">
                    ⚠️ ATENÇÃO: Prazo Vencido
                </p>
                <p class="mt-2 text-lg font-semibold text-red-900 dark:text-red-100">
                    Assinatura Atrasada
                </p>
                <p class="mt-1 text-xs text-red-800 dark:text-red-200">
                    Prazo era: <strong>{{ $requiredBy?->format('d/m/Y') }}</strong>
                </p>
                <p class="mt-1 text-xs text-red-800 dark:text-red-200">
                    Assine agora para reativar acesso total.
                </p>
                <a href="{{ route('filament.admin.pages.contract-signature') }}"
                   class="mt-3 inline-block rounded-md bg-red-600 px-4 py-2 text-xs font-bold text-white hover:bg-red-700 transition">
                    🔏 Assinar Agora
                </a>
            @else
                <p class="text-sm font-medium text-amber-700 dark:text-amber-300">
                    📋 Contrato SLA + LGPD
                </p>
                <p class="mt-2 text-lg font-semibold text-amber-900 dark:text-amber-100">
                    Prazo: {{ $requiredBy?->format('d/m/Y') }}
                </p>
                <p class="mt-1 text-xs text-amber-800 dark:text-amber-200">
                    {{ $daysLeft }} dias restantes
                </p>
                <p class="mt-1 text-xs text-amber-800 dark:text-amber-200">
                    Assine o contrato de serviço para formalizar o SLA e conformidade LGPD.
                </p>
                <a href="{{ route('filament.admin.pages.contract-signature') }}"
                   class="mt-3 inline-block rounded-md bg-amber-600 px-4 py-2 text-xs font-medium text-white hover:bg-amber-700 transition">
                    🔏 Assinar Agora
                </a>
            @endif
        </div>
        <div class="text-4xl">
            @if ($isOverdue)
                ⚠️
            @elseif ($hasSignature)
                ✅
            @else
                ⏰
            @endif
        </div>
    </div>
</div>
