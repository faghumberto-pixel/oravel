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

{{--
    @script, nao @once/<script> puro: a etapa 5 so' entra no DOM via morph
    AJAX do Livewire (troca de $step 4->5), nunca via carregamento de pagina
    completo. Uma <script> comum injetada por innerHTML/morph NAO executa --
    e' assim que o navegador funciona, Livewire nao muda isso. @script e'
    o mecanismo do proprio Livewire v3 pra' garantir que o JS rode tanto no
    mount inicial quanto em updates subsequentes (fonte: mesmo padrao usado
    em vendor/filament/filament .../unsaved-action-changes-alert.blade.php).
    Esse era o motivo real de "assinatura nao funciona" sobreviver a varias
    rodadas de fix no JS em si -- o script simplesmente nunca rodava.
--}}
@script
    <script>
        (function () {
            if (window.__oravelSignatureDelegated) return;
            window.__oravelSignatureDelegated = true;

            const BG = 'rgb(15, 23, 42)';
            const BG_RGB = [15, 23, 42];
            const pads = {};

            function isSignatureCanvas(el) {
                return el && (el.id === 'technicianSignaturePad' || el.id === 'clientSignaturePad');
            }

            function ensureSetup(canvas) {
                if (canvas.dataset.oravelSigReady === '1') {
                    return pads[canvas.id];
                }

                const ctx = canvas.getContext('2d', { willReadFrequently: true });
                const dpr = window.devicePixelRatio || 1;
                const w = canvas.clientWidth || 300;
                const h = canvas.clientHeight || 150;
                canvas.width = w * dpr;
                canvas.height = h * dpr;
                ctx.scale(dpr, dpr);
                ctx.fillStyle = BG;
                ctx.fillRect(0, 0, w, h);

                canvas.dataset.oravelSigReady = '1';
                pads[canvas.id] = { ctx, w, h, drawing: false, lastX: 0, lastY: 0 };

                return pads[canvas.id];
            }

            function clearCanvas(canvas) {
                const pad = ensureSetup(canvas);
                pad.ctx.fillStyle = BG;
                pad.ctx.fillRect(0, 0, pad.w, pad.h);
            }

            function isCanvasEmpty(canvas) {
                const pad = pads[canvas.id];
                if (!pad) return true;
                const { data } = pad.ctx.getImageData(0, 0, canvas.width, canvas.height);
                for (let i = 0; i < data.length; i += 4) {
                    if (data[i] !== BG_RGB[0] || data[i + 1] !== BG_RGB[1] || data[i + 2] !== BG_RGB[2]) {
                        return false;
                    }
                }
                return true;
            }

            function getPos(canvas, e) {
                const rect = canvas.getBoundingClientRect();
                const point = e.touches && e.touches.length ? e.touches[0] : e;
                return { x: point.clientX - rect.left, y: point.clientY - rect.top };
            }

            function handleStart(e) {
                if (!isSignatureCanvas(e.target)) return;
                e.preventDefault();
                const pad = ensureSetup(e.target);
                const pos = getPos(e.target, e);
                pad.drawing = true;
                pad.lastX = pos.x;
                pad.lastY = pos.y;
            }

            function handleMove(e) {
                if (!isSignatureCanvas(e.target)) return;
                const pad = pads[e.target.id];
                if (!pad || !pad.drawing) return;
                e.preventDefault();
                const pos = getPos(e.target, e);
                pad.ctx.strokeStyle = 'rgb(255, 255, 255)';
                pad.ctx.lineWidth = 2;
                pad.ctx.lineCap = 'round';
                pad.ctx.lineJoin = 'round';
                pad.ctx.beginPath();
                pad.ctx.moveTo(pad.lastX, pad.lastY);
                pad.ctx.lineTo(pos.x, pos.y);
                pad.ctx.stroke();
                pad.lastX = pos.x;
                pad.lastY = pos.y;
            }

            function handleEnd(e) {
                if (!isSignatureCanvas(e.target)) return;
                const pad = pads[e.target.id];
                if (pad) pad.drawing = false;
            }

            document.addEventListener('mousedown', handleStart);
            document.addEventListener('mousemove', handleMove);
            document.addEventListener('mouseup', handleEnd);
            document.addEventListener('mouseleave', handleEnd, true);
            document.addEventListener('touchstart', handleStart, { passive: false });
            document.addEventListener('touchmove', handleMove, { passive: false });
            document.addEventListener('touchend', handleEnd);

            document.addEventListener('click', function (e) {
                if (e.target.id === 'clearTechSignature') {
                    const canvas = document.getElementById('technicianSignaturePad');
                    if (canvas) clearCanvas(canvas);
                }
                if (e.target.id === 'clearClientSignature') {
                    const canvas = document.getElementById('clientSignaturePad');
                    if (canvas) clearCanvas(canvas);
                }
            });

            // Garante que um canvas recem-inserido pelo Livewire ja' nasce com
            // tamanho/fundo prontos, mesmo que o tecnico nunca toque nele
            // antes de tentar enviar (isEmpty precisa do ctx existir).
            function scanAndSetup() {
                document.querySelectorAll('#technicianSignaturePad, #clientSignaturePad').forEach(ensureSetup);
            }
            scanAndSetup();
            new MutationObserver(scanAndSetup).observe(document.body, { childList: true, subtree: true });

            window.getTechnicianSignature = () => {
                const canvas = document.getElementById('technicianSignaturePad');
                if (!canvas || isCanvasEmpty(canvas)) return null;
                return canvas.toDataURL('image/png');
            };
            window.getClientSignature = () => {
                const canvas = document.getElementById('clientSignaturePad');
                if (!canvas || isCanvasEmpty(canvas)) return null;
                return canvas.toDataURL('image/png');
            };
            window.isTechnicianSigned = () => {
                const canvas = document.getElementById('technicianSignaturePad');
                return !!canvas && !isCanvasEmpty(canvas);
            };
            window.isClientSigned = () => {
                const canvas = document.getElementById('clientSignaturePad');
                return !!canvas && !isCanvasEmpty(canvas);
            };
        })();
    </script>
@endscript
