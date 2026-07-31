<div class="space-y-4">
    {{-- Resumo da O.S. --}}
    <div class="rounded-2xl bg-slate-800 p-4 border border-slate-700">
        <label class="text-xs font-bold uppercase tracking-wide text-slate-400">Resumo da Execução</label>

        <div class="mt-3 space-y-2 text-sm">
            <div class="flex items-center justify-between rounded-lg bg-slate-700 px-3 py-2">
                <span class="text-slate-400">Equipamento</span>
                <span class="font-semibold text-emerald-400">✓ Registrado</span>
            </div>
            <div class="flex items-center justify-between rounded-lg bg-slate-700 px-3 py-2">
                <span class="text-slate-400">Checklist ({{ $maintenanceOrder->checklists_count ?? 0 }} itens)</span>
                <span class="font-semibold {{ $maintenanceOrder->checklists_count > 0 ? 'text-emerald-400' : 'text-slate-500' }}">
                    {{ $maintenanceOrder->checklists_count > 0 ? '✓ Vistoriado' : '— Opcional' }}
                </span>
            </div>
            <div class="flex items-center justify-between rounded-lg bg-slate-700 px-3 py-2">
                <span class="text-slate-400">Avarias</span>
                <span class="font-semibold {{ strlen($maintenanceOrder->description ?? '') > 0 ? 'text-emerald-400' : 'text-slate-500' }}">
                    {{ strlen($maintenanceOrder->description ?? '') > 0 ? '✓ Documentado' : '— Nenhum' }}
                </span>
            </div>
            <div class="flex items-center justify-between rounded-lg bg-slate-700 px-3 py-2">
                <span class="text-slate-400">Materiais ({{ $maintenanceOrder->materials_count ?? 0 }})</span>
                <span class="font-semibold {{ $maintenanceOrder->materials_count > 0 ? 'text-emerald-400' : 'text-slate-500' }}">
                    {{ $maintenanceOrder->materials_count > 0 ? '✓ Aplicado' : '— Nenhum' }}
                </span>
            </div>
        </div>
    </div>

    {{-- Custos finais --}}
    <div class="rounded-2xl border border-emerald-900 bg-emerald-950/30 p-4">
        <p class="text-xs font-bold uppercase tracking-wide text-emerald-400">💰 Custos Finais</p>
        <div class="mt-3 space-y-1 text-sm">
            <div class="flex justify-between text-slate-300">
                <span>Materiais:</span>
                <span>R$ {{ number_format($maintenanceOrder->material_cost ?? 0, 2, ',', '.') }}</span>
            </div>
            <div class="flex justify-between text-slate-300">
                <span>Mão de obra:</span>
                <span>R$ {{ number_format($maintenanceOrder->labor_cost ?? 0, 2, ',', '.') }}</span>
            </div>
            <div class="flex justify-between text-slate-300">
                <span>Logística:</span>
                <span>R$ {{ number_format($maintenanceOrder->logistics_cost ?? 0, 2, ',', '.') }}</span>
            </div>
            <div class="border-t border-emerald-800 pt-2 flex justify-between text-lg font-bold text-emerald-300">
                <span>Total:</span>
                <span>R$ {{ number_format($maintenanceOrder->total_order_cost ?? 0, 2, ',', '.') }}</span>
            </div>
        </div>
    </div>

    {{-- Assinaturas: saade/filament-autograph, mesmo pacote do form desktop
         (MaintenanceOrderResource). Resolve touch/dedo, DPR e limpar/refazer
         sem JS escrito a mao. --}}
    <div class="rounded-2xl bg-slate-800 p-4 border border-slate-700">
        {{ $this->signatureForm }}
    </div>

    {{-- Aviso de finalização --}}
    <div class="rounded-2xl border border-amber-900 bg-amber-950/30 px-4 py-3">
        <p class="text-xs font-semibold text-amber-400">
            ⚠️ Ao enviar, a O.S. será marcada como concluída e não poderá ser editada.
        </p>
    </div>
</div>
