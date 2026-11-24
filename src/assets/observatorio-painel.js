/*
 * Painel /observatorio: grafico principal, series do Banco Central (SGS), filtro de regiao, janela de detalhes e CSV.
 * Dados estaticos vem do JSON embutido na pagina (#op-dados, gerado de src/_data/observatorioPainel.js).
 * Series do Banco Central sao lidas direto no navegador por INTERVALO DE DATAS (a API limita "ultimos N").
 * Se uma leitura falhar, o item mostra "Indisponivel no momento": nunca um numero inventado.
 */
(function () {
  'use strict';
  var el = function (id) { return document.getElementById(id); };
  var dadosEl = el('op-dados');
  if (!dadosEl) return;
  var D = JSON.parse(dadosEl.textContent);
  var REDUZ = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  var COR = { laranja: '#f28c1b', ciano: '#5fd0c5', texto: '#eaf2f1', muted: '#a9bdba', grade: 'rgba(255,255,255,.09)', ambar: '#f5c25a' };
  var BCB = 'https://api.bcb.gov.br/dados/serie/bcdata.sgs.';
  var estado = { regiao: 'brasil', ind: 'selic', per: '24m', eq: 'total' };
  var grafPrincipal = null, grafDialogo = null, memoria = {};

  /* ---------- utilidades ---------- */
  function nf(n, c) { return Number(n).toLocaleString('pt-BR', { minimumFractionDigits: c, maximumFractionDigits: c }); }
  function bi(v) { return 'R$ ' + nf(v, 1) + ' bi'; }
  function esc(s) { return String(s).replace(/[&<>"]/g, function (ch) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[ch]; }); }
  function p2(n) { return (n < 10 ? '0' : '') + n; }
  function fData(d) { return p2(d.getDate()) + '/' + p2(d.getMonth() + 1) + '/' + d.getFullYear(); }
  function pData(s) { var p = s.split('/'); return new Date(+p[2], +p[1] - 1, +p[0]); }
  function recuo(meses) { var d = new Date(); d.setMonth(d.getMonth() - meses); return d; }
  function achar(lista, id) { for (var i = 0; i < lista.length; i++) if (lista[i].id === id) return lista[i]; return null; }
  function regiaoAtual() { return estado.regiao === 'brasil' ? null : achar(D.regioes, estado.regiao); }
  function fatorRegiao() { var r = regiaoAtual(); return r ? r.pct / 100 : 1; }

  /* ---------- Banco Central (SGS) ---------- */
  // A API do Banco Central oscila de vez em quando: uma nova tentativa automatica resolve a maioria dos casos.
  function tentar(fn, vezes) {
    return fn().catch(function (e) {
      if (vezes <= 0) throw e;
      return new Promise(function (ok) { setTimeout(ok, 1200); }).then(function () { return tentar(fn, vezes - 1); });
    });
  }
  function baixar(codigo, meses) {
    var extra = codigo === 192 ? 14 : 0; // INCC-M: 11 meses antes da janela para o acumulado em 12 meses
    var chave = 'op:bcb:' + codigo + ':' + meses;
    try {
      var g = JSON.parse(sessionStorage.getItem(chave) || 'null');
      if (g && Date.now() - g.t < 3600000) return Promise.resolve(g.d);
    } catch (e) { /* sem storage */ }
    var url = BCB + codigo + '/dados?formato=json&dataInicial=' + fData(recuo(meses + extra)) + '&dataFinal=' + fData(new Date());
    return tentar(function () {
      return fetch(url, { headers: { Accept: 'application/json' } })
        .then(function (r) { if (!r.ok) throw new Error('HTTP ' + r.status); return r.json(); });
    }, 1)
      .then(function (d) {
        if (!Array.isArray(d) || !d.length) throw new Error('serie vazia');
        try { sessionStorage.setItem(chave, JSON.stringify({ t: Date.now(), d: d })); } catch (e) { /* ignora */ }
        return d;
      });
  }
  function processar(codigo, bruto) {
    var fim = new Date(); fim.setHours(23, 59, 59);
    var pts = bruto.map(function (x) { return { d: pData(x.data), v: parseFloat(x.valor) }; })
      .filter(function (p) { return isFinite(p.v) && p.d <= fim; });
    if (codigo === 192) { // acumulado em 12 meses
      var acc = [];
      for (var i = 11; i < pts.length; i++) {
        var f = 1;
        for (var j = i - 11; j <= i; j++) f *= 1 + pts[j].v / 100;
        acc.push({ d: pts[i].d, v: (f - 1) * 100 });
      }
      pts = acc;
    }
    return pts;
  }
  function recortar(pts, meses) {
    var ini = recuo(meses);
    var r = pts.filter(function (p) { return p.d >= ini; });
    return r.length ? r : pts.slice(-2);
  }
  function obter(codigo, meses) {
    var m = memoria[codigo];
    if (!m || m.meses < meses) {
      var necessario = Math.max(meses, 24);
      var prom = baixar(codigo, necessario).then(function (b) { return processar(codigo, b); });
      m = memoria[codigo] = { meses: necessario, promessa: prom };
      prom.catch(function () { if (memoria[codigo] && memoria[codigo].promessa === prom) delete memoria[codigo]; });
    }
    return m.promessa.then(function (pts) { return recortar(pts, meses); });
  }
  function compactar(codigo, pts) {
    var r = [];
    if (codigo === 432) { // Selic muda poucas vezes: guarda so' os pontos de mudanca (e o ultimo)
      pts.forEach(function (p, i) { if (i === 0 || i === pts.length - 1 || p.v !== pts[i - 1].v) r.push(p); });
      return r;
    }
    if (pts.length > 320) {
      var passo = Math.ceil(pts.length / 320);
      pts.forEach(function (p, i) { if (i % passo === 0 || i === pts.length - 1) r.push(p); });
      return r;
    }
    return pts;
  }
  function csvBCB(codigo, nomeSerie, unidade, pts) {
    var fonte = 'Banco Central do Brasil, SGS série ' + codigo + (codigo === 192 ? ' (INCC-M, variação mensal), acumulado em 12 meses pela Oravel' : '');
    var linhas = ['"data";"valor";"unidade";"fonte";"serie_sgs"'].concat(pts.map(function (p) {
      return ['"' + fData(p.d) + '"', '"' + String(p.v.toFixed(codigo === 1 ? 4 : 2)).replace('.', ',') + '"', '"' + unidade + '"', '"' + fonte + '"', '"' + codigo + '"'].join(';');
    }));
    return new Blob(['﻿' + linhas.join('\r\n') + '\r\n'], { type: 'text/csv;charset=utf-8' });
  }
  function baixarBlob(blob, nome) {
    var a = document.createElement('a'); a.href = URL.createObjectURL(blob); a.download = nome;
    document.body.appendChild(a); a.click(); document.body.removeChild(a);
    setTimeout(function () { URL.revokeObjectURL(a.href); }, 1000);
  }

  /* ---------- sparklines dos cards do Banco Central ---------- */
  function sparkPontos(pts) {
    var W = 120, H = 36, P = 4, vs = pts.map(function (p) { return p.v; });
    var min = Math.min.apply(null, vs), max = Math.max.apply(null, vs), faixa = (max - min) || 1, n = pts.length;
    var xy = pts.map(function (p, i) { return { x: P + (n === 1 ? (W - 2 * P) / 2 : i * (W - 2 * P) / (n - 1)), y: H - P - ((p.v - min) / faixa) * (H - 2 * P) }; });
    return xy;
  }
  function preencherCardBCB(card, codigo, ind) {
    var casas = codigo === 1 ? 4 : 2;
    obter(codigo, 24).then(function (pts) {
      var ult = pts[pts.length - 1];
      var v = card.querySelector('[data-valor]'), d = card.querySelector('[data-data]'), s = card.querySelector('[data-spark]');
      v.textContent = (codigo === 1 ? 'R$ ' : '') + nf(ult.v, casas) + (codigo === 1 ? '' : '%');
      v.removeAttribute('data-pendente');
      d.textContent = 'Último dado: ' + fData(ult.d) + ' (série do Banco Central)';
      var xy = sparkPontos(compactar(codigo, pts).slice(-120));
      s.innerHTML = '<title>Evolução dos últimos 24 meses</title><polyline class="op-spark-linha" fill="none" points="' + xy.map(function (p) { return p.x.toFixed(1) + ',' + p.y.toFixed(1); }).join(' ') + '"/>' +
        '<circle class="op-spark-ponto" cx="' + xy[xy.length - 1].x.toFixed(1) + '" cy="' + xy[xy.length - 1].y.toFixed(1) + '" r="3"/>';
      s.setAttribute('aria-label', ind.titulo + ': ' + v.textContent + ' em ' + fData(ult.d) + '. Evolução dos últimos 24 meses.');
      var b = card.querySelector('[data-csv-bcb]');
      if (b) {
        b.disabled = false;
        b.onclick = function () { baixarBlob(csvBCB(codigo, ind.titulo, ind.unidade, pts), 'bcb-sgs-' + codigo + '.csv'); };
      }
    }).catch(function () {
      var v = card.querySelector('[data-valor]'), d = card.querySelector('[data-data]');
      v.textContent = '—'; v.setAttribute('data-pendente', '');
      d.textContent = 'Indisponível no momento';
    });
  }

  /* ---------- grafico principal ---------- */
  function hachura(cor) {
    var c = document.createElement('canvas'); c.width = c.height = 10;
    var x = c.getContext('2d'); x.strokeStyle = cor; x.lineWidth = 2; x.beginPath(); x.moveTo(-2, 12); x.lineTo(12, -2); x.stroke();
    return x.createPattern(c, 'repeat');
  }
  var TIPO_ROTULO = { real: 'Real', estimativa: 'Estimativa', simulado: 'Simulação' };

  // Monta a especificacao do grafico a partir do estado. Cada serie tem um tipo: real, estimativa ou simulado.
  function especificar() {
    var g = achar(D.grafico, estado.ind);
    var spec = { g: g, aviso: null, series: [], labels: [], csv: null, fonte: '', periodo: '', coleta: '', anual: g.tipo === 'anual' };
    var f = fatorRegiao(), reg = regiaoAtual(), sim = D.simulacao;
    if (g.tipo === 'bcb') return Promise.resolve(spec);
    if (g.id === 'faturamento') {
      var tipoReal = reg ? 'estimativa' : 'real';
      var rotR = reg ? 'Faturamento, estimativa ' + reg.rot : 'Faturamento do setor';
      var hist = sim.faturamentoHistorico;
      spec.labels = (hist ? hist.anos : []).concat(['2025', '2026 (proj.)']);
      var nulos = hist ? hist.anos.length : 0, vazios = []; for (var i = 0; i < nulos; i++) vazios.push(null);
      spec.series.push({ rot: rotR + ' (2025 realizado, 2026 projeção)', tipo: tipoReal, valores: vazios.concat([D.faturamento['2025'] * f, D.faturamento['2026'] * f]) });
      if (hist) spec.series.push({ rot: 'Histórico anterior a 2025 (simulação)', tipo: 'simulado', valores: hist.valores.map(function (v) { return v * f; }).concat([null, null]) });
      spec.fonte = 'KPMG, Rental Market Report (a pedido da Analoc, com apoio da Sobratema), divulgado pela Sobratema em 26/03/2026';
      spec.periodo = hist ? '2025 e 2026 (publicados); 2021 a 2024 (simulação)' : '2025 (realizado) e 2026 (projeção)'; spec.coleta = '26/03/2026';
      if (!reg) spec.csv = { href: 'dados/faturamento-setor.csv' };
      spec.nota = (reg ? 'Estimativa: participação da região × faturamento nacional; a fonte não mede faturamento por região. ' : '') +
        (hist ? 'Simulação: ' + hist.metodo : 'O histórico anterior a 2025 não está publicado nas fontes: em breve.');
      return Promise.resolve(spec);
    }
    if (g.id === 'vendas') {
      var amarela = estado.eq === 'amarela', serie = amarela ? D.vendas.amarela : D.vendas.total;
      spec.fonte = 'Estudo Sobratema do Mercado Brasileiro de Equipamentos para Construção (2023 e 2024, via Analoc, 03/01/2025; 2025, via SICEPOT-RS, 02/02/2026)';
      spec.periodo = '2023 a 2025'; spec.coleta = '02/02/2026';
      if (reg) {
        if (!sim.vendasRegionais) { spec.aviso = 'Vendas por região: em breve. A fonte não divulga vendas por região.'; return Promise.resolve(spec); }
        spec.labels = sim.vendasRegionais.map(function (r) { return r.rot; });
        spec.series.push({ rot: 'Vendas de 2025 por região (simulação)', tipo: 'simulado', valores: sim.vendasRegionais.map(function (r) { return amarela ? r.amarela : r.total; }) });
        spec.nota = 'Simulação: ' + sim.vendasRegionaisMetodo; spec.periodo = '2025 (simulação por região)';
        return Promise.resolve(spec);
      }
      spec.labels = D.vendas.anos;
      spec.series.push({ rot: amarela ? 'Linha amarela (mil unidades)' : 'Total de máquinas para construção (mil unidades)', tipo: 'real', valores: serie });
      spec.csv = { href: 'dados/' + (amarela ? 'vendas-linha-amarela' : 'vendas-maquinas') + '.csv' };
      if (amarela) spec.nota = 'A linha amarela de 2023 é aproximada ("quase 32,3 mil").';
      return Promise.resolve(spec);
    }
    // frota parada
    spec.labels = D.frotaParada.anos;
    spec.series.push({ rot: 'Frota parada (%)', tipo: 'real', valores: D.frotaParada.valores });
    spec.fonte = 'Estudo Sobratema do Mercado Brasileiro de Equipamentos para Construção, via Analoc (03/01/2025)';
    spec.periodo = '2017, 2023 e 2024'; spec.coleta = '03/01/2025'; spec.csv = { href: 'dados/frota-parada.csv' };
    if (reg) spec.nota = 'A frota parada é um dado nacional: o filtro de região não se aplica a este indicador.';
    return Promise.resolve(spec);
  }

  function estadoMsg(msg, repetir) {
    var e = el('op-estado');
    e.textContent = '';
    if (!msg) { e.hidden = true; return; }
    e.hidden = false;
    var t = document.createElement('span'); t.textContent = msg; e.appendChild(t);
    if (repetir) {
      var b = document.createElement('button'); b.type = 'button'; b.className = 'op-btn op-btn--ghost'; b.style.marginLeft = '12px'; b.textContent = 'Tentar novamente';
      b.onclick = repetir; e.appendChild(b);
    }
  }

  function renderTabela(spec, pontosBCB) {
    var t = el('op-tabela'), html;
    if (pontosBCB) {
      var casas = spec.g.casas, corte = pontosBCB.slice(-60).reverse();
      html = '<table class="op-tab"><thead><tr><th scope="col">Data</th><th scope="col">' + esc(spec.g.rot) + '</th><th scope="col">Tipo</th></tr></thead><tbody>' +
        corte.map(function (p) { return '<tr><th scope="row">' + fData(p.d) + '</th><td class="num">' + nf(p.v, casas) + '</td><td>Real</td></tr>'; }).join('') + '</tbody></table>' +
        (pontosBCB.length > 60 ? '<p class="op-nota">Mostrando os 60 pontos mais recentes de ' + pontosBCB.length + '. O CSV traz a série completa do período.</p>' : '');
    } else if (spec.series.length) {
      var linhas = [];
      spec.series.forEach(function (s) { spec.labels.forEach(function (l, i) { if (s.valores[i] !== null && s.valores[i] !== undefined) linhas.push([l, s.valores[i], s.tipo, s.rot]); }); });
      html = '<table class="op-tab"><thead><tr><th scope="col">Período</th><th scope="col">Valor (' + esc(spec.g.unidade) + ')</th><th scope="col">Tipo</th><th scope="col">Série</th></tr></thead><tbody>' +
        linhas.map(function (r) { return '<tr><th scope="row">' + esc(r[0]) + '</th><td class="num">' + nf(r[1], spec.g.casas) + '</td><td>' + TIPO_ROTULO[r[2]] + '</td><td>' + esc(r[3]) + '</td></tr>'; }).join('') + '</tbody></table>';
    } else { html = '<p class="op-nota">Sem dados para exibir.</p>'; }
    t.innerHTML = html;
  }

  function opcoesBase(unidadeY, casas, anual) {
    return {
      responsive: true, maintainAspectRatio: false, animation: REDUZ ? false : { duration: 400 }, interaction: { mode: 'index', intersect: false },
      plugins: {
        legend: { display: true, position: 'bottom', labels: { color: COR.muted, boxWidth: 12, boxHeight: 12, usePointStyle: true, font: { size: 12 } } },
        tooltip: { backgroundColor: '#041413', borderColor: '#2b5a56', borderWidth: 1, titleColor: COR.texto, bodyColor: COR.texto, padding: 10,
          callbacks: { label: function (c) { return ' ' + c.dataset.label + ': ' + nf(c.parsed.y, casas) + ' ' + unidadeY; } } }
      },
      scales: {
        x: { grid: { color: COR.grade }, ticks: { color: COR.muted, maxRotation: 0, autoSkip: true, maxTicksLimit: anual ? 12 : 7 }, border: { color: '#2b5a56' } },
        y: { grid: { color: COR.grade }, ticks: { color: COR.muted, callback: function (v) { return nf(v, casas > 1 ? 1 : casas); } }, border: { color: '#2b5a56' }, title: { display: true, text: unidadeY, color: COR.muted } }
      }
    };
  }

  function desenhar() {
    var g = achar(D.grafico, estado.ind);
    var tipoEquip = el('op-eq-campo'), selPer = el('op-per');
    tipoEquip.hidden = g.id !== 'vendas';
    selPer.disabled = g.tipo === 'anual';
    selPer.setAttribute('aria-describedby', 'op-meta');
    estadoMsg(null);
    if (grafPrincipal) { grafPrincipal.destroy(); grafPrincipal = null; }
    var ctx = el('op-canvas');
    var meta = el('op-meta'), link = el('op-csv-link'), btn = el('op-csv-btn');
    link.hidden = true; btn.hidden = true; btn.onclick = null;

    if (g.tipo === 'bcb') {
      var meses = achar(D.periodos, estado.per).meses;
      estadoMsg('Carregando série do Banco Central…');
      meta.textContent = '';
      var token = ++desenhar.t;
      obter(g.codigo, meses).then(function (pts) {
        if (token !== desenhar.t) return;
        var comp = compactar(g.codigo, pts), ult = pts[pts.length - 1];
        estadoMsg(null);
        var spec = { g: g, series: [], labels: [] };
        meta.innerHTML = '<strong>Fonte:</strong> Banco Central do Brasil, SGS série ' + g.codigo + (g.codigo === 192 ? ' (INCC-M, variação mensal), acumulado em 12 meses pela Oravel' : '') +
          ' · <strong>Período:</strong> ' + fData(pts[0].d) + ' a ' + fData(ult.d) + ' · <strong>Último dado:</strong> ' + nf(ult.v, g.casas) + ' ' + esc(g.unidade) + ' em ' + fData(ult.d) + ' · <span class="op-tag op-tag--breve" style="color:inherit">Real</span>';
        if (typeof Chart === 'undefined') { estadoMsg('A biblioteca de gráficos não carregou. Os dados estão na tabela abaixo.'); }
        else {
          grafPrincipal = new Chart(ctx, {
            type: 'line',
            data: { datasets: [{ label: g.rot, data: comp.map(function (p) { return { x: p.d.getTime(), y: p.v }; }), borderColor: COR.laranja, backgroundColor: 'rgba(242,140,27,.12)', fill: true, borderWidth: 2.5, pointRadius: comp.length > 40 ? 0 : 3, pointHoverRadius: 5, stepped: g.codigo === 432 ? 'after' : false, tension: 0 }] },
            options: (function () {
              var o = opcoesBase(g.unidade, g.casas, false);
              o.scales.x = { type: 'linear', grid: { color: COR.grade }, border: { color: '#2b5a56' }, ticks: { color: COR.muted, maxRotation: 0, maxTicksLimit: 6, callback: function (v) { var d = new Date(v); return p2(d.getMonth() + 1) + '/' + String(d.getFullYear()).slice(2); } } };
              o.plugins.legend.display = false;
              o.plugins.tooltip.callbacks.title = function (it) { return fData(new Date(it[0].parsed.x)); };
              return o;
            })()
          });
        }
        renderTabela(spec, pts);
        btn.hidden = false;
        btn.onclick = function () { baixarBlob(csvBCB(g.codigo, g.rot, g.unidade, pts), 'bcb-sgs-' + g.codigo + '-' + estado.per + '.csv'); };
        ctx.setAttribute('aria-label', g.rot + ': de ' + nf(pts[0].v, g.casas) + ' em ' + fData(pts[0].d) + ' a ' + nf(ult.v, g.casas) + ' em ' + fData(ult.d) + '. Dados completos na tabela abaixo.');
      }).catch(function () {
        if (token !== desenhar.t) return;
        estadoMsg('Série indisponível no momento (Banco Central).', desenhar);
        el('op-tabela').innerHTML = '<p class="op-nota">Sem dados para exibir.</p>';
      });
      return;
    }

    especificar().then(function (spec) {
      if (spec.aviso) { estadoMsg(spec.aviso); meta.textContent = ''; el('op-tabela').innerHTML = '<p class="op-nota">' + esc(spec.aviso) + '</p>'; return; }
      var tem = spec.series.some(function (s) { return s.tipo === 'simulado'; });
      meta.innerHTML = '<strong>Fonte:</strong> ' + esc(spec.fonte) + ' · <strong>Período:</strong> ' + esc(spec.periodo) + ' · <strong>Coleta:</strong> ' + esc(spec.coleta) +
        (spec.nota ? '<br><span class="op-nota">' + esc(spec.nota) + '</span>' : '') +
        (tem ? ' <span class="op-tag op-tag--sim">Simulação</span>' : '') + (spec.series.some(function (s) { return s.tipo === 'estimativa'; }) ? ' <span class="op-tag op-tag--est">Estimativa</span>' : '');
      if (spec.csv) { link.hidden = false; link.href = spec.csv.href; }
      else { meta.innerHTML += '<br><span class="op-nota">Download indisponível: este recorte contém valores estimados ou simulados, que não são distribuídos em CSV.</span>'; }
      renderTabela(spec, null);
      if (typeof Chart === 'undefined') { estadoMsg('A biblioteca de gráficos não carregou. Os dados estão na tabela abaixo.'); return; }
      var datasets = spec.series.map(function (s) {
        var cor = s.tipo === 'simulado' ? COR.ambar : (s.tipo === 'estimativa' ? COR.ciano : COR.laranja);
        return { label: s.rot + (s.tipo === 'real' ? '' : ' [' + TIPO_ROTULO[s.tipo] + ']'), data: s.valores, backgroundColor: s.tipo === 'simulado' ? hachura(cor) : cor, borderColor: cor, borderWidth: s.tipo === 'real' ? 0 : 1.5, borderRadius: 4, maxBarThickness: 56 };
      });
      var o = opcoesBase(spec.g.unidade, spec.g.casas, true);
      o.scales.x.stacked = true; o.scales.y.stacked = true; o.scales.y.beginAtZero = true;
      grafPrincipal = new Chart(ctx, { type: 'bar', data: { labels: spec.labels, datasets: datasets }, options: o });
      ctx.setAttribute('aria-label', spec.g.rot + '. ' + spec.labels.map(function (l, i) { var s = spec.series[0].valores[i] !== null && spec.series[0].valores[i] !== undefined ? spec.series[0] : spec.series[1]; return s && s.valores[i] !== null ? l + ': ' + nf(s.valores[i], spec.g.casas) : ''; }).filter(Boolean).join('; ') + '.');
    });
  }
  desenhar.t = 0;

  /* ---------- filtro de regiao: KPI e card do faturamento, barras regionais ---------- */
  function guardarOriginais() {
    document.querySelectorAll('[data-kpi="faturamento"], .op-card[data-ind="faturamento"]').forEach(function (n) {
      var v = n.querySelector('[data-valor]'), p = n.querySelector('[data-periodo]'), va = n.querySelector('[data-variacao]');
      n._orig = { v: v && v.textContent, p: p && p.textContent, va: va && va.innerHTML };
    });
  }
  function aplicarRegiao() {
    var reg = regiaoAtual(), f = fatorRegiao();
    document.querySelectorAll('[data-kpi="faturamento"], .op-card[data-ind="faturamento"]').forEach(function (n) {
      var v = n.querySelector('[data-valor]'), p = n.querySelector('[data-periodo]'), va = n.querySelector('[data-variacao]'), tag = n.querySelector('[data-tag-est]');
      if (v) v.textContent = reg ? bi(D.faturamento['2025'] * f) : n._orig.v;
      if (p && n._orig.p) p.textContent = reg ? '2025 (realizado) · estimativa, ' + reg.rot : n._orig.p;
      if (va && n._orig.va !== null) va.innerHTML = reg ? 'Estimativa por participação regional (' + reg.pct + '%)' : n._orig.va;
      if (tag) tag.hidden = !reg;
    });
    document.querySelectorAll('.op-barras li[data-regiao]').forEach(function (li) { li.classList.toggle('op-dim', !!reg && li.getAttribute('data-regiao') !== reg.id); });
    desenhar();
  }

  /* ---------- janela de detalhes ---------- */
  function abrirDetalhe(id, gatilho) {
    var ind = achar(D.indicadores, id), dlg = el('op-dialogo');
    if (!ind || !dlg || typeof dlg.showModal !== 'function') return;
    el('op-d-tit').textContent = ind.titulo;
    var corpo = el('op-d-corpo');
    var info = '<dl class="op-d-dl"><dt>Fonte</dt><dd>' + esc(ind.fonte) + '</dd><dt>Período</dt><dd>' + esc(ind.periodo) + '</dd>' +
      (ind.coleta ? '<dt>Data da coleta</dt><dd>' + esc(ind.coleta) + ' (data de divulgação da fonte)</dd>' : '<dt>Data da coleta</dt><dd>Lida ao vivo do Banco Central a cada visita</dd>') +
      '<dt>Metodologia</dt><dd><a href="' + esc(D.metodologia) + '">Como o Observatório trata os dados</a></dd>' +
      (ind.nota ? '<dt>Observação</dt><dd>' + esc(ind.nota) + '</dd>' : '') + '</dl>';
    var topo = ind.tipo === 'bcb' ? '<p class="op-d-val num" id="op-d-valor">…</p><p class="op-nota" id="op-d-data">Carregando série do Banco Central…</p>' :
      '<p class="op-d-val num">' + esc(ind.valor) + '</p><p class="op-nota">' + (ind.rotuloValor ? esc(ind.rotuloValor) + ' · ' : '') + esc(ind.unidade) + (ind.variacao ? ' · <span class="op-delta">▲ ' + esc(ind.variacao) + '</span>' : '') + '</p>';
    var visual = ind.tipo === 'barras' ? '<ul class="op-barras" role="list">' + ind.barras.map(function (b) { return '<li><span class="op-barra-rot">' + esc(b.rot) + '</span><span class="op-barra-trilho"><span class="op-barra-pre" style="width:' + (b.v / ind.max * 100).toFixed(1) + '%"></span></span><span class="op-barra-val num">' + esc(b.txt) + '</span></li>'; }).join('') + '</ul>'
      : '<div class="op-d-grafico"><canvas id="op-d-canvas" role="img" aria-label="' + esc(ind.titulo) + '"></canvas></div>';
    var acoes = '<div class="op-d-acoes">' + (ind.csv ? '<a class="op-btn" href="dados/' + esc(ind.csv) + '.csv" download>Baixar CSV</a>' : (ind.tipo === 'bcb' ? '<button type="button" class="op-btn" id="op-d-csv" disabled>Baixar CSV</button>' : '')) +
      '<a class="op-btn op-btn--ghost" href="' + esc(D.metodologia) + '">Ver metodologia</a></div>';
    corpo.innerHTML = topo + visual + info + acoes;
    if (grafDialogo) { grafDialogo.destroy(); grafDialogo = null; }
    var opts = function (un, c) { var o = opcoesBase(un, c, true); o.plugins.legend.display = false; return o; };
    if (ind.tipo === 'serie' && typeof Chart !== 'undefined') {
      grafDialogo = new Chart(el('op-d-canvas'), { type: 'bar', data: { labels: ind.spark.labels, datasets: [{ label: ind.titulo, data: ind.spark.valores, backgroundColor: COR.laranja, borderRadius: 4, maxBarThickness: 56 }] }, options: opts(ind.unidade, 1) });
    } else if (ind.tipo === 'bcb') {
      obter(ind.codigo, 24).then(function (pts) {
        var ult = pts[pts.length - 1], casas = ind.codigo === 1 ? 4 : 2;
        el('op-d-valor').textContent = (ind.codigo === 1 ? 'R$ ' : '') + nf(ult.v, casas) + (ind.codigo === 1 ? '' : '%');
        el('op-d-data').textContent = 'Último dado: ' + fData(ult.d) + ' · ' + ind.unidade;
        var b = el('op-d-csv'); if (b) { b.disabled = false; b.onclick = function () { baixarBlob(csvBCB(ind.codigo, ind.titulo, ind.unidade, pts), 'bcb-sgs-' + ind.codigo + '.csv'); }; }
        if (typeof Chart !== 'undefined' && el('op-d-canvas')) {
          var comp = compactar(ind.codigo, pts), o = opts(ind.unidade, casas);
          o.scales.x = { type: 'linear', grid: { color: COR.grade }, border: { color: '#2b5a56' }, ticks: { color: COR.muted, maxTicksLimit: 5, callback: function (v) { var d = new Date(v); return p2(d.getMonth() + 1) + '/' + String(d.getFullYear()).slice(2); } } };
          grafDialogo = new Chart(el('op-d-canvas'), { type: 'line', data: { datasets: [{ label: ind.titulo, data: comp.map(function (p) { return { x: p.d.getTime(), y: p.v }; }), borderColor: COR.laranja, borderWidth: 2.5, pointRadius: 0, stepped: ind.codigo === 432 ? 'after' : false }] }, options: o });
        }
      }).catch(function () { el('op-d-valor').textContent = '—'; el('op-d-data').textContent = 'Indisponível no momento'; });
    }
    dlg.showModal();
    dlg._gatilho = gatilho;
  }
  function fecharDialogo() { var d = el('op-dialogo'); if (d.open) d.close(); }

  /* ---------- inicializacao ---------- */
  function iniciar() {
    guardarOriginais();
    ['op-ind', 'op-per', 'op-eq'].forEach(function (id) {
      el(id).addEventListener('change', function () {
        estado[id === 'op-ind' ? 'ind' : id === 'op-per' ? 'per' : 'eq'] = this.value;
        desenhar();
      });
    });
    el('op-regiao').addEventListener('change', function () { estado.regiao = this.value; aplicarRegiao(); });
    el('op-limpar').addEventListener('click', function () {
      estado.regiao = 'brasil'; estado.ind = 'selic'; estado.per = '24m'; estado.eq = 'total';
      el('op-regiao').value = 'brasil'; el('op-ind').value = 'selic'; el('op-per').value = '24m'; el('op-eq').value = 'total';
      aplicarRegiao();
    });
    document.querySelectorAll('[data-detalhe]').forEach(function (b) { b.addEventListener('click', function () { abrirDetalhe(b.getAttribute('data-detalhe'), b); }); });
    var dlg = el('op-dialogo');
    el('op-d-fechar').addEventListener('click', fecharDialogo);
    dlg.addEventListener('click', function (e) { if (e.target === dlg) fecharDialogo(); });
    dlg.addEventListener('close', function () { if (grafDialogo) { grafDialogo.destroy(); grafDialogo = null; } if (dlg._gatilho) dlg._gatilho.focus(); });

    // Cards do Banco Central: carrega quando chegam perto da tela
    var cards = document.querySelectorAll('.op-card[data-tipo="bcb"]');
    var carregar = function (card) { var ind = achar(D.indicadores, card.getAttribute('data-ind')); if (ind) preencherCardBCB(card, ind.codigo, ind); };
    if ('IntersectionObserver' in window) {
      var io = new IntersectionObserver(function (es) { es.forEach(function (e) { if (e.isIntersecting) { io.unobserve(e.target); carregar(e.target); } }); }, { rootMargin: '300px' });
      cards.forEach(function (c) { io.observe(c); });
    } else { cards.forEach(carregar); }
    desenhar();
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', iniciar); else iniciar();
})();
