<div x-data="{
    canvas: null,
    ctx: null,
    init() {
        this.canvas = this.$refs.canvas;
        this.ctx = this.canvas.getContext('2d');
        this.ctx.strokeStyle = '#000';
        this.ctx.lineWidth = 2;
        this.canvas.addEventListener('mousemove', (e) => this.draw(e));
        this.canvas.addEventListener('touchmove', (e) => {
            e.preventDefault();
            let rect = this.canvas.getBoundingClientRect();
            let touch = e.touches[0];
            this.ctx.lineTo(touch.clientX - rect.left, touch.clientY - rect.top);
            this.ctx.stroke();
            $wire.set('data.signature_path', this.canvas.toDataURL());
        });
    },
    draw(e) {
        if (e.buttons !== 1) return;
        this.ctx.lineTo(e.offsetX, e.offsetY);
        this.ctx.stroke();
        $wire.set('data.signature_path', this.canvas.toDataURL());
    }
}">
    <canvas x-ref="canvas" width="400" height="200" style="border:1px solid #ccc; background:#fff; touch-action:none;"></canvas>
    <br>
    <button type="button" @click="ctx.clearRect(0,0,400,200); $wire.set('data.signature_path', null)" class="text-xs text-red-500">Limpar Assinatura</button>
</div>
