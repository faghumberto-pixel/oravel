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

    {{-- Assinatura do Técnico --}}
    <div class="rounded-2xl bg-slate-800 p-4 border border-slate-700">
        <label class="text-xs font-bold uppercase tracking-wide text-slate-400">✍️ Assinatura do Técnico</label>
        <p class="mt-1 text-[11px] text-slate-500">Use o dedo para assinar na área abaixo</p>

        <div class="mt-3 rounded-xl border-2 border-slate-600 bg-slate-900" style="height: 150px; touch-action: none; -webkit-user-select: none; user-select: none;">
            <canvas
                id="technicianSignaturePad"
                width="300"
                height="150"
                style="display: block; width: 100%; height: 100%; background-color: rgb(15, 23, 42); touch-action: none; cursor: crosshair; -webkit-touch-callout: none;"
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

        <div class="mt-3 rounded-xl border-2 border-slate-600 bg-slate-900" style="height: 150px; touch-action: none; -webkit-user-select: none; user-select: none;">
            <canvas
                id="clientSignaturePad"
                width="300"
                height="150"
                style="display: block; width: 100%; height: 100%; background-color: rgb(15, 23, 42); touch-action: none; cursor: crosshair; -webkit-touch-callout: none;"
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
@endPushOnce

<script>
    function initSignaturePads() {
        try {
            const techCanvas = document.getElementById('technicianSignaturePad');
            const clientCanvas = document.getElementById('clientSignaturePad');

            if (!techCanvas || !clientCanvas) {
                console.error('❌ Canvas não encontrados. Tech:', !!techCanvas, 'Client:', !!clientCanvas);
                // Tenta de novo em 200ms
                setTimeout(initSignaturePads, 200);
                return;
            }

            console.log('📐 Canvas encontrados, inicializando SignaturePad...');
            console.log('Tech canvas rect:', techCanvas.getBoundingClientRect());
            console.log('Client canvas rect:', clientCanvas.getBoundingClientRect());

            // Inicializar SignaturePad para técnico
            const techPad = new SignaturePad(techCanvas, {
                backgroundColor: 'rgb(15, 23, 42)',
                penColor: 'rgb(255, 255, 255)',
                minWidth: 0.5,
                maxWidth: 2.5,
                throttle: 16,
                minDistance: 5,
                velocityFilterWeight: 0.7,
            });

            // Inicializar SignaturePad para cliente
            const clientPad = new SignaturePad(clientCanvas, {
                backgroundColor: 'rgb(15, 23, 42)',
                penColor: 'rgb(255, 255, 255)',
                minWidth: 0.5,
                maxWidth: 2.5,
                throttle: 16,
                minDistance: 5,
                velocityFilterWeight: 0.7,
            });

            // Teste de eventos
            techCanvas.addEventListener('mousedown', () => console.log('🖱️ Mouse down no tech canvas'));
            techCanvas.addEventListener('touchstart', () => console.log('👆 Touch start no tech canvas'));

            // Botões de limpar
            document.getElementById('clearTechSignature')?.addEventListener('click', () => {
                console.log('🔄 Limpando assinatura do técnico');
                techPad.clear();
            });

            document.getElementById('clearClientSignature')?.addEventListener('click', () => {
                console.log('🔄 Limpando assinatura do cliente');
                clientPad.clear();
            });

            // Funções globais
            window.getTechnicianSignature = () => {
                const sig = !techPad.isEmpty() ? techPad.toDataURL('image/png') : null;
                console.log('📝 Tech signature:', sig ? '✓ Existe' : '✗ Vazia');
                return sig;
            };

            window.getClientSignature = () => {
                const sig = !clientPad.isEmpty() ? clientPad.toDataURL('image/png') : null;
                console.log('📝 Client signature:', sig ? '✓ Existe' : '✗ Vazia');
                return sig;
            };

            window.isTechnicianSigned = () => !techPad.isEmpty();
            window.isClientSigned = () => !clientPad.isEmpty();

            console.log('✅ SignaturePad inicializado com sucesso!');
        } catch (e) {
            console.error('❌ Erro ao inicializar SignaturePad:', e.message, e.stack);
        }
    }

    // Iniciar quando DOM estiver pronto
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initSignaturePads);
    } else {
        initSignaturePads();
    }

    // Também tentar no load do Livewire
    if (window.Livewire) {
        window.Livewire.on('updated', () => {
            console.log('📡 Livewire updated, reinicializando pads...');
            setTimeout(initSignaturePads, 100);
        });
    }
</script>
