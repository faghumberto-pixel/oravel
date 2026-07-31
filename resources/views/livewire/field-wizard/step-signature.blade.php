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
                <span :class="$maintenanceOrder->checklists_count > 0 ? 'text-emerald-400' : 'text-slate-500'" class="font-semibold">
                    {{ $maintenanceOrder->checklists_count > 0 ? '✓ Vistoriado' : '— Opcional' }}
                </span>
            </div>
            <div class="flex items-center justify-between rounded-lg bg-slate-700 px-3 py-2">
                <span class="text-slate-400">Avarias</span>
                <span class="font-semibold" :class="strlen($maintenanceOrder->description ?? '') > 0 ? 'text-emerald-400' : 'text-slate-500'">
                    {{ strlen($maintenanceOrder->description ?? '') > 0 ? '✓ Documentado' : '— Nenhum' }}
                </span>
            </div>
            <div class="flex items-center justify-between rounded-lg bg-slate-700 px-3 py-2">
                <span class="text-slate-400">Materiais ({{ $maintenanceOrder->materials_count ?? 0 }})</span>
                <span class="font-semibold" :class="$maintenanceOrder->materials_count > 0 ? 'text-emerald-400' : 'text-slate-500'">
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

    {{-- Assinatura do Técnico --}}
    <div class="rounded-2xl bg-slate-800 p-4 border border-slate-700">
        <label class="text-xs font-bold uppercase tracking-wide text-slate-400">✍️ Assinatura do Técnico</label>
        <p class="mt-1 text-[11px] text-slate-500">Use o dedo para assinar na área abaixo</p>

        <div class="mt-3 rounded-xl border-2 border-slate-600 bg-slate-900 overflow-hidden" style="height: 120px; touch-action: none; display: block;">
            <canvas
                id="technicianSignaturePad"
                width="320"
                height="120"
                class="w-full h-full cursor-crosshair block"
                style="display: block; touch-action: none; background: rgb(15, 23, 42);"
                wire:ignore
            ></canvas>
        </div>

        <button
            type="button"
            id="clearTechSignature"
            class="mt-2 w-full rounded-lg bg-slate-700 hover:bg-slate-600 px-3 py-2 text-xs font-bold text-slate-300 transition"
        >
            Limpar Assinatura
        </button>
    </div>

    {{-- Assinatura do Cliente --}}
    <div class="rounded-2xl bg-slate-800 p-4 border border-slate-700">
        <label class="text-xs font-bold uppercase tracking-wide text-slate-400">✍️ Assinatura do Cliente</label>
        <p class="mt-1 text-[11px] text-slate-500">Cliente deve assinar para confirmar o trabalho realizado</p>

        <div class="mt-3 rounded-xl border-2 border-slate-600 bg-slate-900 overflow-hidden" style="height: 120px; touch-action: none; display: block;">
            <canvas
                id="clientSignaturePad"
                width="320"
                height="120"
                class="w-full h-full cursor-crosshair block"
                style="display: block; touch-action: none; background: rgb(15, 23, 42);"
                wire:ignore
            ></canvas>
        </div>

        <button
            type="button"
            id="clearClientSignature"
            class="mt-2 w-full rounded-lg bg-slate-700 hover:bg-slate-600 px-3 py-2 text-xs font-bold text-slate-300 transition"
        >
            Limpar Assinatura
        </button>
    </div>

    {{-- Aviso de finalização --}}
    <div class="rounded-2xl border border-amber-900 bg-amber-950/30 px-4 py-3">
        <p class="text-xs font-semibold text-amber-400">
            ⚠️ Ao enviar, a O.S. será marcada como concluída e não poderá ser editada.
        </p>
    </div>
</div>

@pushOnce('scripts')
    <script src="https://cdn.jsdelivr.net/npm/signature_pad@4.1.7/dist/signature_pad.umd.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Pequeno delay para garantir que tudo foi renderizado
            setTimeout(() => {
                const initSignaturePad = (canvasId, clearBtnId) => {
                    const canvas = document.getElementById(canvasId);
                    if (!canvas) {
                        console.error(`Canvas ${canvasId} not found`);
                        return null;
                    }

                    // Limpar e resetar o canvas
                    const ctx = canvas.getContext('2d', { willReadFrequently: true });

                    // Usar dimensões já definidas em width/height attributes
                    const dpr = window.devicePixelRatio || 1;
                    canvas.width = canvas.getAttribute('width') * dpr;
                    canvas.height = canvas.getAttribute('height') * dpr;
                    ctx.scale(dpr, dpr);

                    // Desenhar fundo escuro
                    ctx.fillStyle = 'rgb(15, 23, 42)';
                    ctx.fillRect(0, 0, canvas.width / dpr, canvas.height / dpr);

                    // Garantir que o canvas está visível
                    canvas.style.display = 'block';

                    if (!window.SignaturePad) {
                        console.error('SignaturePad library not loaded');
                        return null;
                    }

                    const signaturePad = new window.SignaturePad(canvas, {
                        backgroundColor: 'rgb(15, 23, 42)',
                        penColor: 'rgb(255, 255, 255)',
                        throttle: 16,
                        minWidth: 1,
                        maxWidth: 3,
                        velocityFilterWeight: 0.7,
                        onEnd: () => console.log(`${canvasId} signature drawn`)
                    });

                    const clearBtn = document.getElementById(clearBtnId);
                    if (clearBtn) {
                        clearBtn.addEventListener('click', () => {
                            signaturePad.clear();
                        });
                    }

                    return signaturePad;
                };

                const techSignature = initSignaturePad('technicianSignaturePad', 'clearTechSignature');
                const clientSignature = initSignaturePad('clientSignaturePad', 'clearClientSignature');

                // Expor para uso global
                window.getTechnicianSignature = () => {
                    if (!techSignature?.isEmpty?.()) return null;
                    return techSignature?.toDataURL?.('image/png');
                };
                window.getClientSignature = () => {
                    if (!clientSignature?.isEmpty?.()) return null;
                    return clientSignature?.toDataURL?.('image/png');
                };
                window.isTechnicianSigned = () => techSignature && !techSignature.isEmpty?.();
                window.isClientSigned = () => clientSignature && !clientSignature.isEmpty?.();
            }, 100);
        });
    </script>
@endPushOnce
