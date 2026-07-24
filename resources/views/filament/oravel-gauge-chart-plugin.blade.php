@auth
    {{--
        Chart.js não tem um tipo nativo de "gauge" (mostrador semicircular).
        App\Filament\Widgets\Charts\GaugeChart desenha as 3 faixas de cor
        como um doughnut de 180° (rotation:-90, circumference:180) -- isso o
        Chart.js já faz nativamente -- e essa plugin só desenha por cima
        (afterDraw): ponteiro, marcador de meta e os rótulos da escala.

        Registro via window.filamentChartJsPlugins: é o array global que
        vendor/filament/widgets/resources/js/components/chart.js já injeta
        em TODO ChartWidget do painel (`plugins: window.filamentChartJsPlugins
        ?? []`). Não precisa mexer no bundle do Vite pra isso -- só empurrar
        o objeto da plugin nesse array antes do Chart.js montar o widget.

        Escopo: só age em charts que tenham chart.options.plugins.oravelGauge
        definido (GaugeChart::getOptions() é quem seta isso) -- doughnuts
        comuns do resto do painel (ex: PmpStatusDonutChart) não são afetados.
    --}}
    <script>
        (function () {
            if (window.__oravelGaugePluginRegistered) return;
            window.__oravelGaugePluginRegistered = true;

            const oravelGaugePlugin = {
                id: 'oravelGauge',
                afterDraw(chart) {
                    const opts = chart.options?.plugins?.oravelGauge;
                    if (!opts) return;

                    const meta = chart.getDatasetMeta(0);
                    const arcs = meta?.data;
                    if (!arcs || !arcs.length) return;

                    const first = arcs[0];
                    const last = arcs[arcs.length - 1];
                    const { x, y, innerRadius, outerRadius } = first;
                    const startAngle = first.startAngle;
                    const endAngle = last.endAngle;

                    const ctx = chart.ctx;
                    const value = Math.max(0, Math.min(100, opts.value ?? 0));
                    const target = opts.target;
                    const fontFamily = chart.options?.font?.family || "'Inter', system-ui, sans-serif";
                    const tickColor = opts.tickColor || '#94a3b8';
                    const needleColor = opts.needleColor || '#e5e7eb';
                    const valueColor = opts.valueColor || '#f9fafb';

                    const angleAt = (pct) => startAngle + (endAngle - startAngle) * (pct / 100);

                    ctx.save();

                    // Rótulos da escala (0/25/50/75/100)
                    ctx.font = '10px ' + fontFamily;
                    ctx.fillStyle = tickColor;
                    ctx.textAlign = 'center';
                    ctx.textBaseline = 'middle';
                    [0, 25, 50, 75, 100].forEach((pct) => {
                        const a = angleAt(pct);
                        const r = outerRadius + 13;
                        ctx.fillText(pct + '%', x + Math.cos(a) * r, y + Math.sin(a) * r);
                    });

                    // Marcador de meta (traço perpendicular ao arco)
                    if (target !== null && target !== undefined) {
                        const a = angleAt(Math.max(0, Math.min(100, target)));
                        const r1 = innerRadius - 3;
                        const r2 = outerRadius + 3;
                        ctx.strokeStyle = opts.targetColor || '#f9fafb';
                        ctx.lineWidth = 3;
                        ctx.beginPath();
                        ctx.moveTo(x + Math.cos(a) * r1, y + Math.sin(a) * r1);
                        ctx.lineTo(x + Math.cos(a) * r2, y + Math.sin(a) * r2);
                        ctx.stroke();
                    }

                    // Ponteiro
                    const needleAngle = angleAt(value);
                    const needleLength = innerRadius * 0.9;
                    ctx.strokeStyle = needleColor;
                    ctx.lineWidth = 3;
                    ctx.lineCap = 'round';
                    ctx.beginPath();
                    ctx.moveTo(x, y);
                    ctx.lineTo(x + Math.cos(needleAngle) * needleLength, y + Math.sin(needleAngle) * needleLength);
                    ctx.stroke();

                    ctx.beginPath();
                    ctx.arc(x, y, 5, 0, Math.PI * 2);
                    ctx.fillStyle = needleColor;
                    ctx.fill();

                    // Valor central
                    ctx.font = 'bold 22px ' + fontFamily;
                    ctx.fillStyle = valueColor;
                    ctx.textAlign = 'center';
                    ctx.fillText(Math.round(value) + '%', x, y + outerRadius * 0.4);

                    ctx.restore();
                },
            };

            window.filamentChartJsPlugins = window.filamentChartJsPlugins || [];
            window.filamentChartJsPlugins.push(oravelGaugePlugin);
        })();
    </script>
@endauth
