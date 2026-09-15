<x-filament-panels::page>
    <div class="space-y-6">
        <!-- Timeline Component -->
        <x-contract-timeline
            :contract="$contract"
            :startDate="$timelineData['startDate']"
            :endDate="$timelineData['endDate']"
            :daysElapsed="$timelineData['daysElapsed']"
            :daysRemaining="$timelineData['daysRemaining']"
            :totalDays="$timelineData['totalDays']"
            :progress="$timelineData['progress']"
            :renewalSuggestedDate="$timelineData['renewalSuggestedDate']"
            :maintenanceEvents="$timelineData['maintenanceEvents']"
            :maintenanceCount="$timelineData['maintenanceCount']"
        />

        <!-- Detalhes adicionais -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- Informações do Contrato -->
            <div class="bg-white rounded-lg border border-gray-200 p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Informações do Contrato</h3>
                <div class="space-y-3 text-sm">
                    <div class="flex justify-between">
                        <span class="text-gray-600">Número:</span>
                        <span class="font-medium">{{ $contract->contract_number }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Cliente:</span>
                        <span class="font-medium">{{ $contract->client->name }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Equipamento:</span>
                        <span class="font-medium">{{ $contract->asset->name }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Tipo de Faturamento:</span>
                        <span class="font-medium">{{ $contract::billingTypeOptions()[$contract->billing_type] ?? 'N/A' }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Valor:</span>
                        <span class="font-medium">R$ {{ number_format($contract->price, 2, ',', '.') }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Status:</span>
                        <span class="font-medium px-2 py-1 rounded text-xs {{ $contract->is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' }}">
                            {{ $contract->is_active ? 'Ativo' : 'Inativo' }}
                        </span>
                    </div>
                </div>
            </div>

            <!-- Status da Renovação -->
            <div class="bg-blue-50 rounded-lg border border-blue-200 p-6">
                <h3 class="text-lg font-semibold text-blue-900 mb-4">🔄 Status de Renovação</h3>
                <div class="space-y-3 text-sm">
                    <div class="flex justify-between">
                        <span class="text-blue-700">Data de Vencimento:</span>
                        <span class="font-medium">{{ $timelineData['endDate']->format('d/m/Y') }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-blue-700">Dias Restantes:</span>
                        <span class="font-medium text-lg text-blue-600">{{ $timelineData['daysRemaining'] }} dias</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-blue-700">Renovação Sugerida:</span>
                        <span class="font-medium">{{ $timelineData['renewalSuggestedDate']->format('d/m/Y') }}</span>
                    </div>
                    <div class="pt-2 border-t border-blue-200">
                        @if($timelineData['daysRemaining'] <= 60 && $timelineData['daysRemaining'] > 0)
                            <p class="text-amber-600 text-xs">
                                ⚠️ Contrato próximo ao vencimento. Considere iniciar processo de renovação.
                            </p>
                        @elseif($timelineData['daysRemaining'] <= 0)
                            <p class="text-red-600 text-xs">
                                🔴 Contrato vencido! Ação urgente necessária.
                            </p>
                        @else
                            <p class="text-green-600 text-xs">
                                ✅ Contrato em dia. Renovação sugerida em {{ $timelineData['renewalSuggestedDate']->format('d/m/Y') }}.
                            </p>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <!-- Histórico de Manutenções Detalhado -->
        @if(count($timelineData['maintenanceEvents']) > 0)
            <div class="bg-white rounded-lg border border-gray-200 p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">📋 Histórico de Manutenções</h3>
                <div class="space-y-3">
                    @foreach($timelineData['maintenanceEvents'] as $event)
                        <div class="flex gap-4 pb-3 border-b border-gray-100 last:border-0">
                            <div class="flex-shrink-0">
                                <div class="flex items-center justify-center h-8 w-8 rounded-full" style="background-color: {{ $event['color'] }}20; color: {{ $event['color'] }};">
                                    @if($event['type'] === 'preventiva')
                                        🔧
                                    @elseif($event['type'] === 'corretiva')
                                        ⚠️
                                    @else
                                        🔍
                                    @endif
                                </div>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-medium text-gray-900">
                                    {{ ucfirst($event['type']) }}
                                </p>
                                <p class="text-sm text-gray-600">
                                    {{ $event['description'] }}
                                </p>
                                <p class="text-xs text-gray-500 mt-1">
                                    {{ $event['date'] }}
                                </p>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </div>
</x-filament-panels::page>
