<x-filament-panels::page>
    <!-- Header com status geral -->
    <div class="mb-6 rounded-lg border-2 p-6
        @if ($overallStatus['status'] === 'completed')
            border-green-300 bg-green-50 dark:border-green-700 dark:bg-green-900
        @elseif ($overallStatus['status'] === 'warning')
            border-amber-300 bg-amber-50 dark:border-amber-700 dark:bg-amber-900
        @else
            border-blue-300 bg-blue-50 dark:border-blue-700 dark:bg-blue-900
        @endif
    ">
        <div class="flex items-center gap-4">
            <div class="text-5xl">
                @if ($overallStatus['status'] === 'completed')
                    ✅
                @elseif ($overallStatus['status'] === 'warning')
                    ⚠️
                @else
                    📋
                @endif
            </div>
            <div>
                <h1 class="text-2xl font-bold">{{ $overallStatus['label'] }}</h1>
                <p class="text-sm text-gray-600 dark:text-gray-300">
                    {{ $tenant->name }}
                </p>
            </div>
        </div>
    </div>

    <!-- Checklist de conformidade -->
    <div class="grid gap-4">
        @foreach ($checks as $check)
            <div class="rounded-lg border p-4
                @if ($check['status'] === 'completed')
                    border-green-200 bg-green-50 dark:border-green-700 dark:bg-green-900
                @elseif ($check['status'] === 'warning')
                    border-amber-200 bg-amber-50 dark:border-amber-700 dark:bg-amber-900
                @else
                    border-gray-200 bg-gray-50 dark:border-gray-700 dark:bg-gray-900
                @endif
            ">
                <div class="flex items-start justify-between">
                    <div class="flex gap-3">
                        <div class="text-2xl">{{ $check['icon'] }}</div>
                        <div>
                            <h3 class="font-semibold text-gray-900 dark:text-white">
                                {{ $check['name'] }}
                            </h3>
                            <p class="text-sm text-gray-600 dark:text-gray-300">
                                {{ $check['details'] }}
                            </p>
                            @if ($check['date'])
                                <p class="mt-1 text-xs text-gray-500">
                                    {{ $check['date']->format('d/m/Y H:i') }}
                                </p>
                            @endif
                        </div>
                    </div>
                    <div>
                        @if ($check['status'] === 'completed')
                            <span class="inline-flex rounded-full bg-green-100 px-3 py-1 text-xs font-medium text-green-800 dark:bg-green-800 dark:text-green-100">
                                ✓ Completo
                            </span>
                        @elseif ($check['status'] === 'warning')
                            <span class="inline-flex rounded-full bg-amber-100 px-3 py-1 text-xs font-medium text-amber-800 dark:bg-amber-800 dark:text-amber-100">
                                ⚠ Atenção
                            </span>
                        @else
                            <span class="inline-flex rounded-full bg-blue-100 px-3 py-1 text-xs font-medium text-blue-800 dark:bg-blue-800 dark:text-blue-100">
                                ⏳ Pendente
                            </span>
                        @endif
                    </div>
                </div>

                <!-- Ação para contrato não assinado -->
                @if ($check['name'] === 'Contrato SLA + LGPD Assinado' && !$signature)
                    <div class="mt-4">
                        <a href="{{ route('filament.admin.pages.contract-signature') }}"
                           class="inline-block rounded-md bg-orange-600 px-4 py-2 text-sm font-medium text-white hover:bg-orange-700 transition">
                            🔏 Assinar Agora
                        </a>
                    </div>
                @endif
            </div>
        @endforeach
    </div>

    <!-- Links úteis -->
    <div class="mt-8 rounded-lg border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-800">
        <h3 class="mb-4 font-semibold text-gray-900 dark:text-white">📚 Documentos e Links Úteis</h3>
        <ul class="space-y-2">
            <li>
                <a href="https://www.oravel.com.br/sla" target="_blank" class="text-orange-600 hover:underline dark:text-orange-400">
                    → Acordos de Nível de Serviço (SLA)
                </a>
            </li>
            <li>
                <a href="https://www.oravel.com.br/lgpd" target="_blank" class="text-orange-600 hover:underline dark:text-orange-400">
                    → Conformidade LGPD
                </a>
            </li>
            <li>
                <a href="mailto:suporte@oravel.com.br" class="text-orange-600 hover:underline dark:text-orange-400">
                    → Contatar Suporte
                </a>
            </li>
        </ul>
    </div>
</x-filament-panels::page>
