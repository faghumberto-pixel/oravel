@assets
<script src="https://cdn.jsdelivr.net/npm/signature_pad@4.2.0/dist/signature_pad.umd.min.js"></script>
@endassets

<div
    x-data="{
        pad: null,
        empty: true,
        init() {
            const canvas = this.$refs.canvas;
            const ratio = Math.max(window.devicePixelRatio || 1, 1);

            const resize = () => {
                const rect = canvas.getBoundingClientRect();
                canvas.width  = rect.width  * ratio;
                canvas.height = rect.height * ratio;
                canvas.getContext('2d').scale(ratio, ratio);
            };
            resize();

            this.pad = new SignaturePad(canvas, {
                penColor:        '#1e293b',
                backgroundColor: '#ffffff',
            });

            // carregar assinatura existente
            const existing = $wire.get('{{ $getStatePath() }}');
            if (existing) {
                this.pad.fromDataURL(existing, { ratio: 1 });
                this.empty = false;
            }

            this.pad.addEventListener('endStroke', () => {
                this.empty = this.pad.isEmpty();
                $wire.set('{{ $getStatePath() }}', this.pad.toDataURL('image/png'));
            });
        },
        clear() {
            this.pad.clear();
            const canvas = this.$refs.canvas;
            const ctx = canvas.getContext('2d');
            ctx.fillStyle = '#ffffff';
            ctx.fillRect(0, 0, canvas.width, canvas.height);
            this.empty = true;
            $wire.set('{{ $getStatePath() }}', null);
        }
    }"
    class="space-y-2"
>
    <canvas
        x-ref="canvas"
        style="width:100%; height:160px; border:2px dashed rgba(156,163,175,0.4); border-radius:8px; touch-action:none; cursor:crosshair; background:#fff;"
    ></canvas>

    <div style="display:flex; justify-content:space-between; align-items:center;">
        <span x-show="empty"  style="font-size:12px; color:#9ca3af;">
            ✍️ Assine com o dedo ou mouse
        </span>
        <span x-show="!empty" style="font-size:12px; color:#22c55e;">
            ✅ Assinatura capturada
        </span>
        <button
            type="button"
            x-show="!empty"
            @click="clear"
            style="font-size:12px; color:#ef4444; text-decoration:underline; background:none; border:none; cursor:pointer;"
        >
            Limpar
        </button>
    </div>
</div>
