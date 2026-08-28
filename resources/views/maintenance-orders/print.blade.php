<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OS #{{ $order->os_number }} - ORAVEL</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        /* 🖨️ CONTROLE DE IMPRESSÃO MINIMALISTA CORES PURAS */
        @media print {
            .no-print { display: none !important; }
            body { background: white; color: black; padding: 0; margin: 0; }
            @page { margin: 1.2cm; }
        }
    </style>
</head>
<body class="bg-gray-50 text-gray-900 antialiased font-sans p-4 sm:p-8">

    <div class="max-w-4xl mx-auto mb-6 flex justify-between items-center bg-white p-4 rounded-xl border border-gray-200 shadow-sm no-print">
        <span class="text-sm text-gray-500 font-medium">➔ <strong>Visualização PHP Minimalista</strong> (O conteúdo abaixo reflete a folha oficial de pátio)</span>
        <div class="flex gap-2">
            <button onclick="window.close()" class="px-4 py-2 text-sm font-semibold bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg transition">Fechar</button>
            <button onclick="window.print()" class="px-4 py-2 text-sm font-semibold bg-amber-500 hover:bg-amber-600 text-white rounded-lg shadow-sm transition">Imprimir Agora</button>
        </div>
    </div>

    <div class="max-w-4xl mx-auto bg-white p-8 sm:p-12 rounded-2xl border border-gray-200 shadow-sm relative">

        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center border-b border-gray-200 pb-6 mb-6 gap-4">
            <div>
                <h1 class="text-2xl font-black tracking-tight text-amber-500">ORAVEL SISTEMAS</h1>
                <p class="text-xs text-gray-500 font-mono mt-1">Tenant ID: {{ $order->tenant_id }}</p>
            </div>
            <div class="text-left sm:text-right">
                <h2 class="text-xl font-bold text-gray-900">ORDEM DE SERVIÇO</h2>
                <p class="text-lg font-mono font-black text-amber-600 mt-0.5">Nº {{ $order->os_number ?? 'N/A' }}</p>
            </div>
        </div>

        <div class="bg-gray-50 rounded-xl p-4 mb-6 grid grid-cols-2 sm:grid-cols-4 gap-4 text-xs font-medium text-gray-600 border border-gray-100">
            <div>
                <span class="block text-[10px] text-gray-400 uppercase">Emissão do Sistema</span>
                <span class="text-gray-900 font-semibold">{{ $order->created_at?->format('d/m/Y') ?? now()->format('d/m/Y') }}</span>
            </div>
            <div>
                <span class="block text-[10px] text-gray-400 uppercase">Horário Registro</span>
                <span class="text-gray-900 font-semibold">{{ $order->created_at?->format('H:i:s') ?? now()->format('H:i:s') }}</span>
            </div>
            <div>
                <span class="block text-[10px] text-gray-400 uppercase">Status Operação</span>
                <span class="text-gray-900 font-semibold">{{ ucfirst($order->status ?? 'Aberto') }}</span>
            </div>
            <div>
                <span class="block text-[10px] text-gray-400 uppercase">Tipo Manutenção</span>
                <span class="text-gray-900 font-semibold">{{ $order->maintenance_type ?? 'Não Definido' }}</span>
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 mb-8">
            <div class="p-4 rounded-xl border border-gray-200/80">
                <h3 class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-2">Dados do Atendimento</h3>
                <p class="text-sm font-bold text-gray-900">{{ $order->client?->name ?? 'Atendimento Interno / Sem Cliente' }}</p>
                <p class="text-xs text-gray-500 mt-1">Localidade vinculada ao escopo corporativo.</p>
            </div>
            <div class="p-4 rounded-xl border border-gray-200/80">
                <h3 class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-2">Responsável Técnico</h3>
                <p class="text-sm font-bold text-gray-900">{{ $order->technician?->name ?? 'Não Atribuído' }}</p>
                <p class="text-xs text-gray-500 mt-1">Identificador: {{ substr($order->technician_id, 0, 8) ?? 'N/A' }}</p>
            </div>
        </div>

        <div class="mb-8">
            <h3 class="text-sm font-bold text-gray-900 border-b border-gray-100 pb-2 mb-3">Especificações do Ativo</h3>
            <table class="w-full text-left text-sm">
                <thead>
                    <tr class="text-xs text-gray-400 uppercase bg-gray-50 border-y border-gray-200/60">
                        <th class="p-3">Equipamento / Ativo</th>
                        <th class="p-3">Patrimônio</th>
                        <th class="p-3">Horímetro Atual</th>
                        <th class="p-3">Combustível</th>
                    </tr>
                </thead>
                <tbody>
                    <tr class="border-b border-gray-100 font-medium">
                        <td class="p-3 text-gray-900 font-bold">{{ $order->asset?->name ?? 'Não Definido' }}</td>
                        <td class="p-3 font-mono text-gray-600">{{ $order->asset?->patrimonio ?? 'Sem Reg' }}</td>
                        <td class="p-3 font-mono text-gray-600">{{ $order->horimetro_entry ?? '0' }} h</td>
                        <td class="p-3 text-gray-600">{{ $order->fuel_level ? $order->fuel_level.'%' : 'Não Informado' }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        @if($order->maintenance_type === \App\Models\MaintenanceOrder::TYPE_PREVENTIVE && $order->maintenancePlan)
            <div class="mb-8 page-break-inside-avoid">
                <h3 class="text-sm font-bold text-gray-900 border-b border-gray-100 pb-2 mb-3">Plano de Manutenção Preventiva</h3>
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr class="text-gray-400 uppercase bg-gray-50 border-y border-gray-200/60">
                            <th class="p-2.5">Plano</th>
                            <th class="p-2.5">Intervalo</th>
                            <th class="p-2.5">Observações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr class="border-b border-gray-100">
                            <td class="p-2.5 font-bold text-gray-900">{{ $order->maintenancePlan->name }}</td>
                            <td class="p-2.5 font-mono text-gray-600">
                                @if($order->maintenancePlan->interval_hours)
                                    {{ $order->maintenancePlan->interval_hours }}h
                                @elseif($order->maintenancePlan->interval_days)
                                    {{ $order->maintenancePlan->interval_days }} dias
                                @else
                                    —
                                @endif
                            </td>
                            <td class="p-2.5 text-gray-600">{{ $order->maintenancePlan->notes ?? '—' }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        @endif

        @if(!empty($order->technical_notes))
            <div class="mb-8">
                <h3 class="text-sm font-bold text-gray-900 border-b border-b-gray-100 pb-2 mb-3">Laudo e Notas Técnicas</h3>
                <div class="p-4 bg-gray-50 rounded-xl border border-gray-100 text-sm leading-relaxed text-gray-700 whitespace-pre-line">
                    {{ $order->technical_notes }}
                </div>
            </div>
        @endif

        @if($order->checklists && $order->checklists->count() > 0)
            <div class="mb-8 page-break-inside-avoid">
                <h3 class="text-sm font-bold text-gray-900 border-b border-gray-100 pb-2 mb-3">Dossiê de Verificação (Checklist)</h3>
                <div class="space-y-4">
                    @foreach($order->checklists->groupBy(fn ($chk) => $chk->section ?: 'Geral') as $section => $items)
                        <div>
                            @if($section !== 'Geral')
                                <p class="text-[11px] font-bold text-gray-500 uppercase tracking-wide mb-1.5">{{ $section }}</p>
                            @endif
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-xs">
                                @foreach($items as $chk)
                                    @php
                                        $statusLabel = match ($chk->status) {
                                            'conforme' => 'Conforme',
                                            'nao_conforme' => 'Não Conforme',
                                            'nao_aplicavel' => 'N/A',
                                            default => 'Pendente',
                                        };
                                        $statusClass = match ($chk->status) {
                                            'conforme' => 'bg-emerald-100 text-emerald-800',
                                            'nao_conforme' => 'bg-rose-100 text-rose-800',
                                            'nao_aplicavel' => 'bg-gray-100 text-gray-600',
                                            default => 'bg-amber-100 text-amber-800',
                                        };
                                    @endphp
                                    <div class="flex justify-between items-center p-2.5 bg-gray-50/50 rounded-lg border border-gray-100">
                                        <span class="font-medium text-gray-700 truncate mr-2">{{ $chk->item_name }}</span>
                                        <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase shrink-0 {{ $statusClass }}">
                                            {{ $statusLabel }}
                                        </span>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        @if($order->materials && $order->materials->count() > 0)
            <div class="mb-8 page-break-inside-avoid">
                <h3 class="text-sm font-bold text-gray-900 border-b border-gray-100 pb-2 mb-3">Materiais e Insumos Utilizados</h3>
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr class="text-gray-400 uppercase bg-gray-50 border-y border-gray-200/60">
                            <th class="p-2.5">Material</th>
                            <th class="p-2.5 text-center">Quantidade</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($order->materials as $mat)
                            <tr class="border-b border-gray-100">
                                <td class="p-2.5 font-medium text-gray-800">{{ $mat->material?->name ?? 'Material Removido' }}</td>
                                <td class="p-2.5 text-center font-mono text-gray-600">{{ $mat->quantity }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        <div class="mt-12 pt-8 grid grid-cols-2 gap-12 text-center text-xs">
            <div class="border-t border-gray-400 pt-2 text-gray-600">Assinatura do Técnico</div>
            <div class="border-t border-gray-400 pt-2 text-gray-600">Responsável Pátio / Liberação</div>
        </div>

        <div class="mt-16 pt-8 border-t border-gray-200 text-center text-[10px] text-gray-400 font-mono">
            Documento emitido eletronicamente via Central ORAVEL.<br>
            Visualizado em: {{ now()->format('d/m/Y') }} às {{ now()->format('H:i:s') }} (Horário de Brasília)
        </div>
    </div>

</body>
</html>
