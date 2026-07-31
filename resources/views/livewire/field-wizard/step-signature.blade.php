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
    console.log('📝 Carregando script de assinatura...');

    function initSignatures() {
        console.log('🔍 Procurando canvas...');

        const techCanvas = document.getElementById('technicianSignaturePad');
        const clientCanvas = document.getElementById('clientSignaturePad');

        console.log('Tech canvas:', techCanvas ? '✓ Encontrado' : '✗ NÃO ENCONTRADO');
        console.log('Client canvas:', clientCanvas ? '✓ Encontrado' : '✗ NÃO ENCONTRADO');

        if (!techCanvas || !clientCanvas) {
            console.error('❌ Faltam canvas, tentando novamente em 200ms...');
            setTimeout(initSignatures, 200);
            return;
        }

        try {
            console.log('📐 Verificando tamanho dos canvas...');
            console.log('Tech: offsetWidth=' + techCanvas.offsetWidth + ' offsetHeight=' + techCanvas.offsetHeight);
            console.log('Client: offsetWidth=' + clientCanvas.offsetWidth + ' offsetHeight=' + clientCanvas.offsetHeight);

            // Canvas técnico
            const techCtx = techCanvas.getContext('2d');
            techCanvas.width = 320;
            techCanvas.height = 150;

            const techPad = new SignaturePad(techCanvas, {
                backgroundColor: 'rgb(15, 23, 42)',
                penColor: 'rgb(255, 255, 255)',
                minWidth: 1,
                maxWidth: 3,
            });

            // Canvas cliente
            const clientCtx = clientCanvas.getContext('2d');
            clientCanvas.width = 320;
            clientCanvas.height = 150;

            const clientPad = new SignaturePad(clientCanvas, {
                backgroundColor: 'rgb(15, 23, 42)',
                penColor: 'rgb(255, 255, 255)',
                minWidth: 1,
                maxWidth: 3,
            });

            console.log('✅ SignaturePad criado para ambos canvas');

            // Listeners para DEBUG
            techCanvas.addEventListener('mousedown', () => console.log('🖱️ Técnico: mouse down'));
            techCanvas.addEventListener('mousemove', () => console.log('🖱️ Técnico: mouse move'));
            techCanvas.addEventListener('mouseup', () => console.log('🖱️ Técnico: mouse up'));
            techCanvas.addEventListener('touchstart', () => console.log('👆 Técnico: touch start'));
            techCanvas.addEventListener('touchmove', () => console.log('👆 Técnico: touch move'));
            techCanvas.addEventListener('touchend', () => console.log('👆 Técnico: touch end'));

            clientCanvas.addEventListener('mousedown', () => console.log('🖱️ Cliente: mouse down'));
            clientCanvas.addEventListener('mousemove', () => console.log('🖱️ Cliente: mouse move'));
            clientCanvas.addEventListener('mouseup', () => console.log('🖱️ Cliente: mouse up'));
            clientCanvas.addEventListener('touchstart', () => console.log('👆 Cliente: touch start'));
            clientCanvas.addEventListener('touchmove', () => console.log('👆 Cliente: touch move'));
            clientCanvas.addEventListener('touchend', () => console.log('👆 Cliente: touch end'));

            // Botões limpar
            document.getElementById('clearTechSignature')?.addEventListener('click', () => {
                console.log('🔄 Limpando técnico');
                techPad.clear();
            });

            document.getElementById('clearClientSignature')?.addEventListener('click', () => {
                console.log('🔄 Limpando cliente');
                clientPad.clear();
            });

            // Funções globais
            window.getTechnicianSignature = () => {
                const sig = techPad.toDataURL('image/png');
                console.log('📝 Tech signature capturada');
                return sig;
            };

            window.getClientSignature = () => {
                const sig = clientPad.toDataURL('image/png');
                console.log('📝 Client signature capturada');
                return sig;
            };

            window.isTechnicianSigned = () => !techPad.isEmpty();
            window.isClientSigned = () => !clientPad.isEmpty();

            console.log('✅✅✅ ASSINATURA PRONTA PARA USAR ✅✅✅');
        } catch (e) {
            console.error('❌ ERRO:', e.message, e.stack);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initSignatures);
    } else {
        initSignatures();
    }
</script>
