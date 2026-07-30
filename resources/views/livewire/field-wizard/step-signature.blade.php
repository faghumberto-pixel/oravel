<div class="space-y-4">
    {{-- Resumo da O.S. --}}
    <div class="rounded-2xl bg-zinc-900 p-4">
        <label class="text-xs font-bold uppercase tracking-wide text-zinc-400">Resumo da Execução</label>

        <div class="mt-3 space-y-2 text-sm">
            <div class="flex items-center justify-between rounded-lg bg-zinc-800 px-3 py-2">
                <span class="text-zinc-400">Equipamento</span>
                <span class="font-semibold text-emerald-400">✓ Registrado</span>
            </div>
            <div class="flex items-center justify-between rounded-lg bg-zinc-800 px-3 py-2">
                <span class="text-zinc-400">Checklist ({{ $maintenanceOrder->checklists_count ?? 0 }} itens)</span>
                <span :class="$maintenanceOrder->checklists_count > 0 ? 'text-emerald-400' : 'text-zinc-500'" class="font-semibold">
                    {{ $maintenanceOrder->checklists_count > 0 ? '✓ Vistoriado' : '— Opcional' }}
                </span>
            </div>
            <div class="flex items-center justify-between rounded-lg bg-zinc-800 px-3 py-2">
                <span class="text-zinc-400">Avarias ({{ strlen($maintenanceOrder->description ?? '') > 0 ? 'Sim' : 'Não' }})</span>
                <span class="font-semibold" :class="strlen($maintenanceOrder->description ?? '') > 0 ? 'text-emerald-400' : 'text-zinc-500'">
                    {{ strlen($maintenanceOrder->description ?? '') > 0 ? '✓ Documentado' : '— Nenhum problema' }}
                </span>
            </div>
            <div class="flex items-center justify-between rounded-lg bg-zinc-800 px-3 py-2">
                <span class="text-zinc-400">Materiais ({{ $maintenanceOrder->materials_count ?? 0 }})</span>
                <span class="font-semibold" :class="$maintenanceOrder->materials_count > 0 ? 'text-emerald-400' : 'text-zinc-500'">
                    {{ $maintenanceOrder->materials_count > 0 ? '✓ Aplicado' : '— Nenhum' }}
                </span>
            </div>
        </div>
    </div>

    {{-- Custos finais --}}
    <div class="rounded-2xl border border-emerald-900 bg-emerald-950/20 p-4">
        <p class="text-xs font-bold uppercase tracking-wide text-emerald-400">Custos Finais</p>
        <div class="mt-3 space-y-1 text-sm">
            <div class="flex justify-between text-zinc-300">
                <span>Materiais:</span>
                <span>R$ {{ number_format($maintenanceOrder->material_cost ?? 0, 2, ',', '.') }}</span>
            </div>
            <div class="flex justify-between text-zinc-300">
                <span>Mão de obra:</span>
                <span>R$ {{ number_format($maintenanceOrder->labor_cost ?? 0, 2, ',', '.') }}</span>
            </div>
            <div class="flex justify-between text-zinc-300">
                <span>Logística:</span>
                <span>R$ {{ number_format($maintenanceOrder->logistics_cost ?? 0, 2, ',', '.') }}</span>
            </div>
            <div class="border-t border-emerald-800 pt-2 flex justify-between text-lg font-bold text-emerald-300">
                <span>Total:</span>
                <span>R$ {{ number_format($maintenanceOrder->total_order_cost ?? 0, 2, ',', '.') }}</span>
            </div>
        </div>
    </div>

    {{-- Assinatura do técnico --}}
    <div class="rounded-2xl bg-zinc-900 p-4">
        <label class="text-xs font-bold uppercase tracking-wide text-zinc-400">Assinatura do Técnico</label>
        <p class="mt-1 text-[11px] text-zinc-500">Assine na área abaixo para confirmar a execução</p>

        <div class="mt-3 rounded-xl border-2 border-zinc-700 bg-white" style="height: 120px;">
            <canvas
                id="signaturePad"
                class="w-full h-full"
                wire:ignore
            ></canvas>
        </div>

        <button
            type="button"
            wire:click="clearSignature"
            class="mt-2 w-full rounded-xl border border-zinc-700 px-3 py-2 text-xs font-bold text-zinc-400 hover:bg-zinc-800"
        >
            Limpar assinatura
        </button>
    </div>

    {{-- Aviso de finalização --}}
    <div class="rounded-2xl border border-amber-900 bg-amber-950/20 px-4 py-3">
        <p class="text-xs font-semibold text-amber-400">
            ⚠️ Ao enviar, a O.S. será marcada como concluída e não poderá ser editada.
        </p>
    </div>
</div>

@pushOnce('scripts')
    <script src="https://cdn.jsdelivr.net/npm/signature_pad@4.1.7/dist/signature_pad.umd.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const canvas = document.getElementById('signaturePad');
            if (!canvas) return;

            const ctx = canvas.getContext('2d');
            const rect = canvas.getBoundingClientRect();
            canvas.width = rect.width * window.devicePixelRatio;
            canvas.height = rect.height * window.devicePixelRatio;
            ctx.scale(window.devicePixelRatio, window.devicePixelRatio);

            const signaturePad = new SignaturePad(canvas, {
                backgroundColor: 'rgb(255, 255, 255)',
                penColor: 'rgb(0, 0, 0)',
                throttle: 16,
            });

            // Expor para Alpine se necessário
            window.getSignature = function() {
                return signaturePad.toDataURL('image/png');
            };
        });
    </script>
@endPushOnce
