/*
 * App do colaborador -- trabalho SEM internet.
 *  - Guarda no celular (IndexedDB) um pacote com os equipamentos da empresa e as OS (baixado com internet).
 *  - Consulta por QR / patrimônio / placa e "OS do equipamento" funcionam offline, desenhadas aqui no aparelho.
 *  - Execução da OS offline (iniciar, horímetro, combustível, checklist, fotos, notas, assinaturas, concluir) vira uma
 *    FILA de ações com UUID; ao voltar a internet a fila é enviada em ordem e o servidor aplica sem duplicar.
 * JavaScript puro (a página do app não carrega Alpine/Livewire). Estilos próprios com prefixo "co-".
 */
(function () {
    'use strict';

    var meta = function (n) { var e = document.querySelector('meta[name="' + n + '"]'); return e ? e.content : null; };
    var BASE = (meta('colab-base') || '/').replace(/\/?$/, '/');
    var UUID = /^[0-9a-f-]{8,}$/i;
    var COR = { green: ['#10b98126', '#34d399'], orange: ['#f9731626', '#fb923c'], amber: ['#f59e0b26', '#fbbf24'], red: ['#ef444426', '#f87171'], yellow: ['#eab30826', '#fde047'], sky: ['#0ea5e926', '#38bdf8'], zinc: ['#3f3f4666', '#d4d4d8'] };
    var ROTULO_ATIVO = { disponivel: ['Liberado', 'green'], operando: ['Operando', 'sky'], locado: ['Locado', 'yellow'], manutencao: ['Em manutenção', 'orange'], aguardando_triagem: ['Aguardando triagem', 'red'], quarentena: ['Em quarentena', 'red'], reservado: ['Reservado', 'sky'] };
    var PRIORIDADE_SIT = ['Aguardando peça', 'Em manutenção', 'Aguardando diagnóstico'];

    /* ---------- utilidades ---------- */
    function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
    function uuid() {
        if (window.crypto && crypto.randomUUID) { return crypto.randomUUID(); }
        return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function (c) { var r = Math.random() * 16 | 0; return (c === 'x' ? r : (r & 3 | 8)).toString(16); });
    }
    function agoraLocal() { var d = new Date(), p = function (n) { return String(n).padStart(2, '0'); }; return d.getFullYear() + '-' + p(d.getMonth() + 1) + '-' + p(d.getDate()) + ' ' + p(d.getHours()) + ':' + p(d.getMinutes()) + ':' + p(d.getSeconds()); }
    function dias(ab, fim) {
        if (!ab) { return 0; }
        var a = new Date(ab), b = fim ? new Date(fim) : new Date();
        a.setHours(0, 0, 0, 0); b.setHours(0, 0, 0, 0);
        return Math.max(0, Math.round((b - a) / 86400000));
    }
    function dataBR(iso) { if (!iso) { return '—'; } var d = new Date(iso); return String(d.getDate()).padStart(2, '0') + '/' + String(d.getMonth() + 1).padStart(2, '0') + '/' + d.getFullYear(); }
    function normPlaca(s) { return String(s || '').toUpperCase().replace(/[^A-Z0-9]/g, ''); }
    function badge(rotulo, cor) { var c = COR[cor] || COR.zinc; return '<span class="co-badge" style="background:' + c[0] + ';color:' + c[1] + '">' + esc(rotulo) + '</span>'; }
    function dd(n) { return n + (n === 1 ? ' dia' : ' dias'); }

    /* ---------- banco local ---------- */
    var dbp = null;
    function db() {
        if (dbp) { return dbp; }
        dbp = new Promise(function (ok, erro) {
            var rq = indexedDB.open('oravel-colab', 1);
            rq.onupgradeneeded = function () {
                var d = rq.result;
                ['kv', 'ativos', 'os', 'minhas', 'fila', 'fotos'].forEach(function (s) {
                    if (!d.objectStoreNames.contains(s)) { d.createObjectStore(s, { keyPath: s === 'kv' ? 'k' : (s === 'fila' || s === 'fotos' ? 'uuid' : 'id') }); }
                });
            };
            rq.onsuccess = function () { ok(rq.result); };
            rq.onerror = function () { erro(rq.error); };
        });
        return dbp;
    }
    function tx(store, modo, fn) {
        return db().then(function (d) {
            return new Promise(function (ok, erro) {
                var t = d.transaction(store, modo), r = fn(t.objectStore(store));
                t.oncomplete = function () { ok(r && r.result !== undefined ? r.result : undefined); };
                t.onerror = function () { erro(t.error); };
                t.onabort = function () { erro(t.error); };
            });
        });
    }
    var dbGet = function (s, k) { return tx(s, 'readonly', function (o) { return o.get(k); }); };
    var dbAll = function (s) { return tx(s, 'readonly', function (o) { return o.getAll(); }); };
    var dbPut = function (s, v) { return tx(s, 'readwrite', function (o) { return o.put(v); }); };
    var dbDel = function (s, k) { return tx(s, 'readwrite', function (o) { return o.delete(k); }); };
    var kvGet = function (k) { return dbGet('kv', k).then(function (r) { return r ? r.v : undefined; }); };
    var kvSet = function (k, v) { return dbPut('kv', { k: k, v: v }); };
    var kvDel = function (k) { return dbDel('kv', k); };

    /* ---------- pacote de dados (baixa) ---------- */
    var sincronizando = false;
    function sincronizar(forcar) {
        if (!navigator.onLine || sincronizando) { return Promise.resolve(false); }
        sincronizando = true;
        return kvGet('pacote_em').then(function (em) {
            if (!forcar && em && Date.now() - em < 10 * 60 * 1000) { return false; }
            return fetch(BASE + 'api/pacote', { credentials: 'same-origin', headers: { Accept: 'application/json' } }).then(function (r) {
                if (!r.ok) { return false; }
                return r.json().then(function (p) { return guardarPacote(p).then(reaplicarFrota).then(function () { return true; }); });
            });
        }).catch(function () { return false; }).then(function (r) { sincronizando = false; if (r) { window.dispatchEvent(new CustomEvent('colab:pacote')); } return r; });
    }
    function guardarPacote(p) {
        return db().then(function (d) {
            return new Promise(function (ok, erro) {
                var t = d.transaction(['ativos', 'os', 'minhas', 'kv'], 'readwrite');
                ['ativos', 'os', 'minhas'].forEach(function (s) { t.objectStore(s).clear(); });
                (p.ativos || []).forEach(function (a) { t.objectStore('ativos').put(a); });
                Object.keys(p.os || {}).forEach(function (k) { t.objectStore('os').put(p.os[k]); });
                (p.minhas || []).forEach(function (o) { t.objectStore('minhas').put(o); });
                t.objectStore('kv').put({ k: 'pacote_em', v: Date.now() });
                t.objectStore('kv').put({ k: 'usuario_id', v: p.usuario_id });
                t.objectStore('kv').put({ k: 'frota', v: p.frota || null });
                t.oncomplete = ok; t.onerror = function () { erro(t.error); }; t.onabort = function () { erro(t.error); };
            });
        });
    }

    /* ---------- fila de envio (sobe) ---------- */
    var seq = 0;
    function enfileirar(tipo, dados, uuidFixo) {
        return kvGet('usuario_id').then(function (usuario) {
            var acao = { uuid: uuidFixo || uuid(), tipo: tipo, dados: dados, criado_em: agoraLocal(), seq: Date.now() * 1000 + (seq++ % 1000), estado: 'pendente', mensagem: null, usuario: usuario || null };
            return dbPut('fila', acao).then(function () { return acao; });
        }).then(function (acao) { atualizarBarra(); if (navigator.onLine) { setTimeout(enviarFila, 50); } return acao; });
    }
    var enviando = false;
    function csrf() { return fetch(BASE + 'api/csrf', { credentials: 'same-origin', headers: { Accept: 'application/json' } }).then(function (r) { if (!r.ok) { throw new Error('auth'); } return r.json(); }); }
    function enviarFila() {
        if (enviando || !navigator.onLine) { return Promise.resolve(); }
        enviando = true;
        var algumOk = false;
        return dbAll('fila').then(function (todas) {
            var pend = todas.filter(function (a) { return a.estado === 'pendente'; }).sort(function (a, b) { return a.seq - b.seq; });
            if (!pend.length) { return; }
            return csrf().then(function (sessao) {
                var token = sessao.token;
                // Só envia o que o usuário LOGADO criou: se outra pessoa usou este aparelho antes, as ações dela esperam o login dela.
                pend = pend.filter(function (a) { return !a.usuario || a.usuario === sessao.usuario_id; });
                var passo = function (i) {
                    if (i >= pend.length) { return; }
                    return enviarUma(pend[i], token).then(function (continua) { if (continua === 'ok') { algumOk = true; } if (continua !== 'parar') { return passo(i + 1); } });
                };
                return passo(0);
            });
        }).catch(function () { /* sem sessão/rede: tenta de novo depois */ }).then(function () {
            enviando = false;
            atualizarBarra();
            if (algumOk) { return sincronizar(true); }
        });
    }
    function enviarUma(acao, token) {
        var corpo = { uuid: acao.uuid, tipo: acao.tipo, dados: acao.dados, criado_em: acao.criado_em };
        var pronta = acao.tipo === 'os_foto'
            ? dbGet('fotos', acao.uuid).then(function (f) {
                if (!f) { return { erroLocal: 'Foto não está mais no aparelho.' }; }
                var fd = new FormData();
                fd.append('acao', JSON.stringify(corpo));
                fd.append('arquivo', f.blob, 'foto.jpg');
                return { init: { method: 'POST', credentials: 'same-origin', headers: { Accept: 'application/json', 'X-CSRF-TOKEN': token }, body: fd } };
            })
            : Promise.resolve({ init: { method: 'POST', credentials: 'same-origin', headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token }, body: JSON.stringify({ acoes: [corpo] }) } });

        return pronta.then(function (p) {
            if (p.erroLocal) { return finalizarAcao(acao, 'erro', p.erroLocal); }
            return fetch(BASE + 'api/fila', p.init).then(function (r) {
                if (!r.ok) { return 'parar'; }           // 401/419/5xx: sessão ou servidor -- tenta mais tarde, sem perder nada
                return r.json().then(function (j) { var res = (j.resultados || [])[0] || {}; return finalizarAcao(acao, res.status === 'erro' ? 'erro' : 'ok', res.mensagem); });
            });
        }).catch(function () { return 'parar'; });
    }
    function finalizarAcao(acao, status, mensagem) {
        if (status === 'ok') {
            return Promise.all([dbDel('fila', acao.uuid), dbDel('fotos', acao.uuid)]).then(function () {
                return mensagem && /encerrada/i.test(mensagem) ? avisar('OS ' + (acao.dados.os_num || '') + ': ' + mensagem).then(function () { return 'ok'; }) : 'ok';
            });
        }
        acao.estado = 'erro'; acao.mensagem = mensagem || 'Recusada pelo servidor.';
        return dbPut('fila', acao).then(function () { return 'erro'; });
    }
    function avisar(texto) { return kvGet('avisos').then(function (l) { l = l || []; l.push({ t: texto, em: agoraLocal() }); return kvSet('avisos', l.slice(-20)); }); }

    /* ---------- barra de status ---------- */
    var barra = null;
    function estilos() {
        if (document.getElementById('co-css')) { return; }
        var s = document.createElement('style'); s.id = 'co-css';
        s.textContent = '.co-wrap{max-width:28rem;margin:0 auto;padding:1.25rem;color:#f4f4f5;font-family:inherit}.co-h1{font-size:1.25rem;font-weight:700;margin:.5rem 0}.co-sub{color:#a1a1aa;font-size:.8rem}.co-card{background:#18181b;border-radius:1rem;padding:.9rem;margin-top:.7rem;display:block;color:inherit;text-decoration:none}.co-card b{display:block}.co-badge{display:inline-block;border-radius:999px;padding:.15rem .7rem;font-size:.7rem;font-weight:700}.co-btn{display:flex;align-items:center;justify-content:center;min-height:3rem;border-radius:.75rem;background:#f97316;color:#fff;font-weight:700;border:0;width:100%;margin-top:.7rem;font-size:1rem;text-decoration:none}.co-btn.alt{background:#27272a}.co-btn.ok{background:#059669}.co-btn:disabled{opacity:.5}.co-in{width:100%;min-height:3rem;border-radius:.75rem;border:1px solid #3f3f46;background:#18181b;color:#fff;padding:0 .9rem;font-size:1rem;box-sizing:border-box}textarea.co-in{padding:.7rem .9rem;min-height:5rem}.co-lbl{display:block;color:#a1a1aa;font-size:.7rem;font-weight:700;text-transform:uppercase;margin:.9rem 0 .25rem}.co-row{display:flex;gap:.5rem}.co-seg{flex:1;border-radius:.6rem;border:1px solid #3f3f46;background:#18181b;color:#d4d4d8;padding:.6rem .2rem;font-size:.75rem;font-weight:700}.co-seg.on-g{background:#059669;color:#fff;border-color:#059669}.co-seg.on-r{background:#dc2626;color:#fff;border-color:#dc2626}.co-seg.on-n{background:#52525b;color:#fff;border-color:#52525b}.co-sig{background:#fff;border-radius:.6rem;width:100%;height:140px;touch-action:none;display:block}.co-fotos img{width:4.5rem;height:4.5rem;object-fit:cover;border-radius:.5rem;margin:.25rem .25rem 0 0}.co-bar{position:fixed;left:0;right:0;bottom:0;z-index:50;padding:.55rem .9rem;font-size:.8rem;font-weight:700;display:flex;gap:.6rem;align-items:center;justify-content:space-between;color:#fff}.co-bar button{background:#00000040;border:0;color:#fff;border-radius:.5rem;padding:.4rem .7rem;font-weight:700}.co-pad{height:3.5rem}';
        document.head.appendChild(s);
    }
    function atualizarBarra() {
        estilos();
        return dbAll('fila').then(function (f) {
            var pend = f.filter(function (a) { return a.estado === 'pendente'; }).length, erros = f.filter(function (a) { return a.estado === 'erro'; });
            var offline = !navigator.onLine, cor = null, html = '';
            if (erros.length) { cor = '#b91c1c'; html = '<span>⚠ ' + erros.length + ' ação(ões) recusada(s) pelo servidor</span><button data-co="erros">Ver</button>'; }
            else if (pend) { cor = offline ? '#52525b' : '#b45309'; html = '<span>' + (offline ? '📡 Sem conexão · ' : '⏳ ') + pend + ' ação(ões) aguardando envio</span>' + (offline ? '' : '<button data-co="enviar">Enviar agora</button>'); }
            else if (offline) { cor = '#52525b'; html = '<span>📡 Sem conexão — trabalhando offline</span>'; }
            if (!cor) { if (barra) { barra.remove(); barra = null; } return; }
            if (!barra) { barra = document.createElement('div'); barra.className = 'co-bar'; document.body.appendChild(barra); barra.addEventListener('click', cliqueBarra); }
            barra.style.background = cor; barra.innerHTML = html;
        }).catch(function () {});
    }
    function cliqueBarra(e) {
        var a = e.target.getAttribute && e.target.getAttribute('data-co');
        if (a === 'enviar') { enviarFila(); }
        if (a === 'erros') {
            dbAll('fila').then(function (f) {
                var er = f.filter(function (x) { return x.estado === 'erro'; });
                var t = er.map(function (x) { return x.tipo + ': ' + x.mensagem; }).join('\n');
                if (window.confirm('Ações recusadas pelo servidor:\n\n' + t + '\n\nDescartar estas ações?')) {
                    Promise.all(er.map(function (x) { return Promise.all([dbDel('fila', x.uuid), dbDel('fotos', x.uuid)]); })).then(atualizarBarra);
                }
            });
        }
    }

    /* ---------- consulta offline (desenhada no aparelho) ---------- */
    function situacaoAtivo(a, ordens) {
        var abertas = ordens.filter(function (o) { return !o.fechada; });
        if (!abertas.length) { var r = ROTULO_ATIVO[a.st] || [String(a.st || '—').replace(/_/g, ' '), 'zinc']; return { rotulo: r[0], cor: r[1], abertas: [] }; }
        abertas.sort(function (x, y) { var px = PRIORIDADE_SIT.indexOf(x.sit), py = PRIORIDADE_SIT.indexOf(y.sit); return (px < 0 ? 99 : px) - (py < 0 ? 99 : py); });
        return { rotulo: abertas[0].sit, cor: abertas[0].cor, abertas: abertas.sort(function (x, y) { return new Date(x.ab) - new Date(y.ab); }) };
    }
    function osDoAtivo(id) { return dbAll('os').then(function (l) { return l.filter(function (o) { return o.ativo === id; }); }); }
    function buscarAtivos(termo) {
        var t = String(termo || '').trim().toLowerCase(), p = normPlaca(termo);
        if (!t) { return Promise.resolve([]); }
        return dbAll('ativos').then(function (l) {
            var ach = l.filter(function (a) {
                return [a.p, a.t, a.n, a.s, a.pl].some(function (v) { return v && String(v).toLowerCase().indexOf(t) >= 0; }) || (p.length >= 3 && normPlaca(a.pl).indexOf(p) >= 0);
            });
            ach.sort(function (x, y) {
                var ex = (x.p && String(x.p).toLowerCase().indexOf(t) === 0) || (p && normPlaca(x.pl) === p) ? 0 : 1;
                var ey = (y.p && String(y.p).toLowerCase().indexOf(t) === 0) || (p && normPlaca(y.pl) === p) ? 0 : 1;
                return ex - ey || String(x.n).localeCompare(String(y.n));
            });
            return ach.slice(0, 20);
        });
    }
    function cartaoOS(o, link) {
        var n = dias(o.ab, o.fim);
        return '<a class="co-card" href="' + link + '"><b>OS ' + esc(o.num) + ' ' + badge(o.sit, o.cor) + '</b><span class="co-sub">Aberta em ' + dataBR(o.ab) + ' · ' + dd(n) + (o.fechada ? ' de duração' : ' em aberto') + '</span><br><span class="co-sub">Técnico: ' + esc(o.tec || 'não definido') + '</span>' + (o.prob ? '<br><span class="co-sub">' + esc(String(o.prob).slice(0, 120)) + '</span>' : '') + '</a>';
    }
    function semPacote() { return '<div class="co-wrap"><h1 class="co-h1">Dados ainda não baixados</h1><p class="co-sub">Abra o app uma vez com internet para guardar os equipamentos e as OS neste aparelho.</p><a class="co-btn alt" href="' + BASE + '">Ir para o início</a></div>'; }
    function listaAtivos(lista, destino, titulo) {
        return '<div class="co-wrap"><a class="co-sub" href="' + BASE + '">← Início</a><h1 class="co-h1">' + esc(titulo) + '</h1><p class="co-sub">Consulta offline — dados do último download.</p>' +
            lista.map(function (a) { return '<a class="co-card" href="' + BASE + destino + a.id + '"><b>' + esc(a.n) + '</b><span class="co-sub">Patrimônio: ' + esc(a.p || '—') + (a.pl ? ' · Placa: ' + esc(a.pl) : '') + '</span></a>'; }).join('') + '</div>';
    }

    function telaDossie(id) {
        return Promise.all([dbGet('ativos', id), osDoAtivo(id)]).then(function (r) {
            var a = r[0]; if (!a) { return '<div class="co-wrap"><h1 class="co-h1">Equipamento não encontrado neste aparelho</h1><p class="co-sub">Ele pode ser novo: abra o app com internet para atualizar.</p><a class="co-btn alt" href="' + BASE + '">Ir para o início</a></div>'; }
            var s = situacaoAtivo(a, r[1]);
            var abertas = s.abertas.map(function (o) { return cartaoOS(o, BASE + 'os/' + o.id); }).join('');
            return '<div class="co-wrap"><a class="co-sub" href="' + BASE + '">← Início</a><p class="co-sub" style="margin-top:.6rem">DOSSIÊ DO ATIVO · OFFLINE</p><h1 class="co-h1">' + esc(a.n) + '</h1>' +
                '<p class="co-sub">PAT: ' + esc(a.p || '—') + (a.pl ? ' · Placa: ' + esc(a.pl) : '') + ' · TAG: ' + esc(a.t || '—') + '</p>' +
                '<div class="co-card"><b>Situação ' + badge(s.rotulo, s.cor) + '</b>' + abertas + '<a class="co-sub" style="display:block;margin-top:.7rem;color:#fb923c;font-weight:700" href="' + BASE + 'os/ativo/' + a.id + '">Ver todas as OS deste equipamento →</a></div></div>';
        });
    }
    function telaOsAtivo(id) {
        return Promise.all([dbGet('ativos', id), osDoAtivo(id)]).then(function (r) {
            var a = r[0]; if (!a) { return semPacote(); }
            var s = situacaoAtivo(a, r[1]);
            var fechadas = r[1].filter(function (o) { return o.fechada; }).sort(function (x, y) { return new Date(y.ab) - new Date(x.ab); }).slice(0, 10);
            var bloco = function (titulo, lista) { return lista.length ? '<p class="co-lbl">' + titulo + ' (' + lista.length + ')</p>' + lista.map(function (o) { return cartaoOS(o, BASE + 'os/' + o.id); }).join('') : ''; };
            return '<div class="co-wrap"><a class="co-sub" href="' + BASE + 'os">← Buscar outro equipamento</a><div class="co-card"><h1 class="co-h1" style="margin:0">' + esc(a.n) + '</h1><span class="co-sub">PAT: ' + esc(a.p || '—') + (a.pl ? ' · Placa: ' + esc(a.pl) : '') + '</span><p style="margin:.6rem 0 0">' + badge(s.rotulo, s.cor) + '</p><a class="co-sub" style="display:block;margin-top:.6rem;color:#fb923c;font-weight:700" href="' + BASE + 'dossie/' + a.id + '">Ver dossiê do ativo →</a></div>' +
                bloco('Em aberto', s.abertas) + bloco('Concluídas recentemente', fechadas) + (!s.abertas.length && !fechadas.length ? '<div class="co-card"><span class="co-sub">Este equipamento não tem nenhuma ordem de serviço.</span></div>' : '') + '</div>';
        });
    }
    function telaOs(id) {
        return Promise.all([dbGet('os', id), dbGet('minhas', id), kvGet('concl:' + id)]).then(function (r) {
            var o = r[0]; if (!o) { return '<div class="co-wrap"><h1 class="co-h1">OS não encontrada neste aparelho</h1><a class="co-btn alt" href="' + BASE + '">Ir para o início</a></div>'; }
            return dbGet('ativos', o.ativo).then(function (a) {
                var n = dias(o.ab, o.fim);
                var abrir = r[1] && !o.fechada && !r[2] ? '<a class="co-btn" href="' + BASE + 'os/' + o.id + '/offline">Abrir OS</a>' : (r[2] ? '<p class="co-sub" style="margin-top:.7rem">✔ Concluída neste aparelho — aguardando envio.</p>' : '');
                return '<div class="co-wrap"><a class="co-sub" href="' + (a ? BASE + 'dossie/' + a.id : BASE) + '">← ' + (a ? 'Dossiê do ativo' : 'Início') + '</a><p class="co-sub" style="margin-top:.6rem">DOSSIÊ DA OS · OFFLINE</p><h1 class="co-h1">OS ' + esc(o.num) + ' ' + badge(o.sit, o.cor) + '</h1>' + abrir +
                    '<div class="co-card"><b>' + esc(a ? a.n : 'Equipamento') + '</b><span class="co-sub">' + (a ? 'PAT: ' + esc(a.p || '—') + (a.pl ? ' · Placa: ' + esc(a.pl) : '') : '') + '</span></div>' +
                    '<div class="co-card"><span class="co-sub">Aberta em</span> <b>' + dataBR(o.ab) + '</b><span class="co-sub">' + (o.fechada ? 'Duração' : 'Em aberto há') + '</span> <b>' + dd(n) + '</b><span class="co-sub">Técnico</span> <b>' + esc(o.tec || 'não definido') + '</b>' + (o.ag ? '<span class="co-sub">Agendada</span> <b>' + dataBR(o.ag) + '</b>' : '') + '</div>' +
                    (o.prob ? '<div class="co-card"><span class="co-sub">Problema relatado</span><b style="font-weight:400">' + esc(o.prob) + '</b></div>' : '') + '</div>';
            });
        });
    }

    /* ---------- execução da OS offline ---------- */
    function reduzirFoto(file) {
        return new Promise(function (ok) {
            var img = new Image(), url = URL.createObjectURL(file);
            img.onload = function () {
                var e = Math.min(1, 1600 / Math.max(img.width, img.height)), c = document.createElement('canvas');
                c.width = Math.round(img.width * e); c.height = Math.round(img.height * e);
                c.getContext('2d').drawImage(img, 0, 0, c.width, c.height);
                URL.revokeObjectURL(url);
                c.toBlob(function (b) { ok(b || file); }, 'image/jpeg', 0.8);
            };
            img.onerror = function () { URL.revokeObjectURL(url); ok(file); };
            img.src = url;
        });
    }
    function assinatura(canvas, aoMudar) {
        var ctx = canvas.getContext('2d'), desenhando = false, tem = false;
        function ajusta() { var r = canvas.getBoundingClientRect(); canvas.width = Math.max(300, Math.round(r.width)); canvas.height = 140; ctx.lineWidth = 2.5; ctx.lineCap = 'round'; ctx.strokeStyle = '#111827'; }
        ajusta();
        function pos(e) { var r = canvas.getBoundingClientRect(); return [(e.clientX - r.left) * canvas.width / r.width, (e.clientY - r.top) * canvas.height / r.height]; }
        canvas.addEventListener('pointerdown', function (e) { desenhando = true; var p = pos(e); ctx.beginPath(); ctx.moveTo(p[0], p[1]); canvas.setPointerCapture(e.pointerId); e.preventDefault(); });
        canvas.addEventListener('pointermove', function (e) { if (!desenhando) { return; } var p = pos(e); ctx.lineTo(p[0], p[1]); ctx.stroke(); tem = true; e.preventDefault(); });
        var fim = function () { if (desenhando) { desenhando = false; if (tem) { aoMudar(canvas.toDataURL('image/png')); } } };
        canvas.addEventListener('pointerup', fim); canvas.addEventListener('pointercancel', fim);
        return { limpar: function () { ctx.clearRect(0, 0, canvas.width, canvas.height); tem = false; aoMudar(null); }, desenharExistente: function (url) { if (!url) { return; } var im = new Image(); im.onload = function () { ctx.drawImage(im, 0, 0, canvas.width, canvas.height); tem = true; }; im.src = url; } };
    }
    function telaExecucao(root, osId) {
        return Promise.all([dbGet('minhas', osId), kvGet('rasc:' + osId), kvGet('concl:' + osId), dbAll('fotos')]).then(function (r) {
            var os = r[0];
            if (r[2]) { root.innerHTML = '<div class="co-wrap"><h1 class="co-h1">✔ OS concluída neste aparelho</h1><p class="co-sub">Aguardando o envio ao servidor.</p><a class="co-btn alt" href="' + BASE + 'minhas-os">Minhas OS</a></div>'; return; }
            if (!os) { root.innerHTML = '<div class="co-wrap"><h1 class="co-h1">Esta OS não está no seu pacote offline</h1><p class="co-sub">Abra o app com internet uma vez para baixá-la. Só OS atribuídas a você ficam disponíveis.</p><a class="co-btn alt" href="' + BASE + 'minhas-os">Minhas OS</a></div>'; return; }
            var rasc = r[1] || { horimetro: os.horimetro_entry == null ? '' : os.horimetro_entry, combustivel: os.fuel_level == null ? '' : String(os.fuel_level), notas: os.technical_notes || '', itens: {}, sigT: null, sigC: null, iniciada: os.status === 'Em Andamento' };
            os.checklist.forEach(function (i) { if (!rasc.itens[i.id]) { rasc.itens[i.id] = { status: i.status || null, notas: i.notas || '' }; } });
            var fotos = r[3].filter(function (f) { return f.os_id === osId; });
            var salvarTimer = null;
            function salvar() { clearTimeout(salvarTimer); salvarTimer = setTimeout(function () { kvSet('rasc:' + osId, rasc); }, 250); }

            var h = '<div class="co-wrap"><a class="co-sub" href="' + BASE + 'minhas-os">← Minhas OS</a><p class="co-sub" style="margin-top:.6rem">EXECUÇÃO · OFFLINE</p><h1 class="co-h1">OS ' + esc(os.num) + '</h1>' +
                '<div class="co-card"><b>' + esc(os.ativo ? os.ativo.n : 'Equipamento') + '</b><span class="co-sub">' + (os.ativo ? 'PAT: ' + esc(os.ativo.p || '—') + (os.ativo.pl ? ' · Placa: ' + esc(os.ativo.pl) : '') : '') + '</span></div>' +
                '<button class="co-btn alt" id="x-iniciar">▶ Iniciar OS</button><p class="co-sub" id="x-iniciada" style="display:none;margin-top:.6rem">✔ OS iniciada (aguardando envio).</p>' +
                '<label class="co-lbl">Horímetro atual' + (os.horimetro_anterior != null ? ' (anterior: ' + esc(os.horimetro_anterior) + ')' : '') + '</label><input class="co-in" id="x-hor" type="number" inputmode="decimal" step="0.1" min="0" value="' + esc(rasc.horimetro) + '">' +
                '<label class="co-lbl">Nível de combustível</label><select class="co-in" id="x-comb"><option value="">—</option>' + ['0', '25', '50', '75', '100'].map(function (n) { return '<option value="' + n + '"' + (String(rasc.combustivel) === n ? ' selected' : '') + '>' + n + '%</option>'; }).join('') + '</select>' +
                '<label class="co-lbl">Checklist</label>' +
                (os.checklist.length ? os.checklist.map(function (i) {
                    var it = rasc.itens[i.id];
                    return '<div class="co-card" data-item="' + i.id + '"><b>' + esc(i.nome) + '</b><span class="co-sub">' + esc(i.secao || '') + '</span><div class="co-row" style="margin-top:.5rem">' +
                        [['conforme', 'Conforme', 'g'], ['nao_conforme', 'Não conforme', 'r'], ['nao_aplicavel', 'N/A', 'n']].map(function (s) { return '<button type="button" class="co-seg' + (it.status === s[0] ? ' on-' + s[2] : '') + '" data-st="' + s[0] + '" data-c="' + s[2] + '">' + s[1] + '</button>'; }).join('') +
                        '</div><input class="co-in" style="margin-top:.5rem;min-height:2.5rem" placeholder="Observação (opcional)" data-nota value="' + esc(it.notas) + '"><label class="co-seg" style="display:block;margin-top:.5rem;text-align:center">📷 Foto do item<input type="file" accept="image/*" capture="environment" data-foto-item style="display:none"></label><div class="co-fotos" data-fotos-item></div></div>';
                }).join('') : '<p class="co-sub">Esta OS não tem itens de checklist.</p>') +
                '<label class="co-lbl">Notas técnicas</label><textarea class="co-in" id="x-notas" placeholder="O que foi feito...">' + esc(rasc.notas) + '</textarea>' +
                '<label class="co-seg" style="display:block;margin-top:.7rem;text-align:center">📷 Foto geral da OS<input type="file" accept="image/*" capture="environment" id="x-foto-geral" style="display:none"></label><div class="co-fotos" id="x-fotos-geral"></div>' +
                '<label class="co-lbl">Assinatura do técnico</label><canvas class="co-sig" id="x-sigt"></canvas><button type="button" class="co-seg" style="margin-top:.4rem;width:100%" id="x-limpar-t">Limpar</button>' +
                '<label class="co-lbl">Assinatura do cliente</label><canvas class="co-sig" id="x-sigc"></canvas><button type="button" class="co-seg" style="margin-top:.4rem;width:100%" id="x-limpar-c">Limpar</button>' +
                '<p class="co-sub" style="margin-top:1rem">Peças/materiais e avarias são registrados online, no Modo Campo.</p>' +
                '<button class="co-btn alt" id="x-pausar">⏸ Salvar e pausar</button><button class="co-btn ok" id="x-concluir">✔ Concluir OS</button><p class="co-sub" id="x-msg" style="color:#f87171;margin-top:.6rem"></p></div><div class="co-pad"></div>';
            root.innerHTML = h;
            var $ = function (id) { return root.querySelector('#' + id); };
            var msg = function (t) { $('x-msg').textContent = t || ''; };

            function pintaFotos(alvo, lista) { alvo.innerHTML = lista.map(function (f) { return '<img data-foto="' + f.uuid + '" src="' + URL.createObjectURL(f.blob) + '" alt="foto">'; }).join(''); }
            function repintaFotos() {
                dbAll('fotos').then(function (todas) {
                    var minhasFotos = todas.filter(function (f) { return f.os_id === osId; });
                    pintaFotos($('x-fotos-geral'), minhasFotos.filter(function (f) { return !f.item_id; }));
                    root.querySelectorAll('[data-item]').forEach(function (c) { pintaFotos(c.querySelector('[data-fotos-item]'), minhasFotos.filter(function (f) { return f.item_id === c.getAttribute('data-item'); })); });
                });
            }
            repintaFotos();
            function mostraIniciada() { $('x-iniciar').style.display = rasc.iniciada ? 'none' : ''; $('x-iniciada').style.display = rasc.iniciada ? '' : 'none'; }
            mostraIniciada();

            $('x-iniciar').addEventListener('click', function () { rasc.iniciada = true; enfileirar('os_iniciar', { os_id: osId, os_num: os.num }); salvar(); mostraIniciada(); });
            $('x-hor').addEventListener('input', function (e) { rasc.horimetro = e.target.value; salvar(); });
            $('x-comb').addEventListener('change', function (e) { rasc.combustivel = e.target.value; salvar(); });
            $('x-notas').addEventListener('input', function (e) { rasc.notas = e.target.value; salvar(); });
            root.querySelectorAll('[data-item]').forEach(function (card) {
                var id = card.getAttribute('data-item');
                card.querySelectorAll('[data-st]').forEach(function (b) {
                    b.addEventListener('click', function () {
                        rasc.itens[id].status = b.getAttribute('data-st');
                        card.querySelectorAll('[data-st]').forEach(function (x) { x.className = 'co-seg' + (x === b ? ' on-' + x.getAttribute('data-c') : ''); });
                        salvar();
                    });
                });
                card.querySelector('[data-nota]').addEventListener('input', function (e) { rasc.itens[id].notas = e.target.value; salvar(); });
                card.querySelector('[data-foto-item]').addEventListener('change', function (e) { guardaFoto(e.target.files[0], id); e.target.value = ''; });
            });
            $('x-foto-geral').addEventListener('change', function (e) { guardaFoto(e.target.files[0], null); e.target.value = ''; });
            function guardaFoto(file, itemId) {
                if (!file) { return; }
                reduzirFoto(file).then(function (blob) { return dbPut('fotos', { uuid: uuid(), os_id: osId, item_id: itemId, blob: blob }); }).then(repintaFotos);
            }
            root.addEventListener('click', function (e) {
                var u = e.target.getAttribute && e.target.getAttribute('data-foto');
                if (u && window.confirm('Remover esta foto?')) { dbDel('fotos', u).then(repintaFotos); }
            });

            var sigT = assinatura($('x-sigt'), function (v) { rasc.sigT = v; salvar(); }), sigC = assinatura($('x-sigc'), function (v) { rasc.sigC = v; salvar(); });
            sigT.desenharExistente(rasc.sigT); sigC.desenharExistente(rasc.sigC);
            $('x-limpar-t').addEventListener('click', function () { sigT.limpar(); });
            $('x-limpar-c').addEventListener('click', function () { sigC.limpar(); });

            function montarAcoes(finalTipo) {
                var seqAcoes = [];
                if (!rasc.iniciada && os.status === 'Aberto') { seqAcoes.push(['os_iniciar', { os_id: osId, os_num: os.num }]); rasc.iniciada = true; }
                seqAcoes.push(['os_dados', { os_id: osId, os_num: os.num, horimetro_entry: rasc.horimetro, fuel_level: rasc.combustivel, technical_notes: rasc.notas }]);
                os.checklist.forEach(function (i) { var it = rasc.itens[i.id]; if (it.status) { seqAcoes.push(['os_checklist', { os_id: osId, os_num: os.num, item_id: i.id, status: it.status, notas: it.notas }]); } });
                if (rasc.sigT || rasc.sigC) { seqAcoes.push(['os_assinaturas', { os_id: osId, os_num: os.num, technician_signature: rasc.sigT, client_signature: rasc.sigC }]); }
                seqAcoes.push([finalTipo, { os_id: osId, os_num: os.num }]);
                return seqAcoes;
            }
            function enfileirarTudo(acoes, fotosOS) {
                // As fotos entram na fila antes do final (concluir/pausar), reaproveitando o UUID de cada foto.
                var ultimo = acoes[acoes.length - 1], antes = acoes.slice(0, -1), p = Promise.resolve();
                antes.forEach(function (a) { p = p.then(function () { return enfileirar(a[0], a[1]); }); });
                fotosOS.forEach(function (f) { p = p.then(function () { return enfileirar('os_foto', { os_id: osId, os_num: os.num, item_id: f.item_id }, f.uuid); }); });
                return p.then(function () { return enfileirar(ultimo[0], ultimo[1]); });
            }
            $('x-pausar').addEventListener('click', function () {
                msg('');
                dbAll('fotos').then(function (todas) { return enfileirarTudo(montarAcoes('os_pausar'), todas.filter(function (f) { return f.os_id === osId; })); })
                    .then(function () { return kvSet('rasc:' + osId, rasc); }).then(function () { window.location = BASE + 'minhas-os'; });
            });
            $('x-concluir').addEventListener('click', function () {
                if (rasc.horimetro === '' || isNaN(Number(rasc.horimetro))) { msg('Informe o horímetro atual.'); return; }
                if (!rasc.sigT || !rasc.sigC) { msg('Faltam as assinaturas do técnico e do cliente.'); return; }
                var pend = os.checklist.filter(function (i) { return !rasc.itens[i.id].status; }).length;
                if (!window.confirm((pend ? pend + ' item(ns) do checklist sem resposta.\n\n' : '') + 'Concluir a OS ' + os.num + '?')) { return; }
                $('x-concluir').disabled = true;
                dbAll('fotos').then(function (todas) { return enfileirarTudo(montarAcoes('os_concluir'), todas.filter(function (f) { return f.os_id === osId; })); })
                    .then(function () { return Promise.all([kvSet('concl:' + osId, true), kvDel('rasc:' + osId), dbDel('minhas', osId), dbGet('os', osId).then(function (o) { if (o) { o.fechada = true; o.sit = 'Concluída'; o.cor = 'green'; o.fim = new Date().toISOString(); return dbPut('os', o); } })]); })
                    .then(function () { root.innerHTML = '<div class="co-wrap"><h1 class="co-h1">✔ OS concluída</h1><p class="co-sub">Salva neste aparelho. O envio ao servidor acontece sozinho quando houver internet.</p><a class="co-btn" href="' + BASE + 'minhas-os">Minhas OS</a></div>'; });
            });
        });
    }

    /* ---------- Logística offline (entrada/saída, abastecimento, marcar km) ---------- */
    var FROTA_TIPOS = ['veiculo_saida', 'veiculo_entrada', 'abastecimento', 'km_marcar'];
    var COMBUSTIVEL_NIVEL = [['', '—'], ['vazio', 'Reserva'], ['1/4', '1/4'], ['1/2', '1/2'], ['3/4', '3/4'], ['cheio', 'Cheio']];
    var frotaGet = function () { return kvGet('frota'); };
    var frotaSet = function (f) { return kvSet('frota', f); };

    function mesmoLocal(x, y) {
        if (x.tipo !== y.tipo) { return false; }
        if (x.tipo === 'empresa') { return true; }
        if (x.tipo === 'cliente') { return !!x.cliente_id && x.cliente_id === y.cliente_id; }
        return !!x.texto && String(x.texto).trim().toLowerCase() === String(y.texto || '').trim().toLowerCase();
    }
    function percursoLiga(p, o, d) { return (mesmoLocal(p.a, o) && mesmoLocal(p.b, d)) || (mesmoLocal(p.b, o) && mesmoLocal(p.a, d)); }

    /* O que o aparelho mostra DEPOIS de registrar offline (e de novo após cada download do pacote, enquanto a ação estiver na fila). */
    function aplicarLocalFrota(f, acao) {
        var d = acao.dados, ui = d.ui || {}, v = (f.veiculos || []).filter(function (x) { return x.id === d.ativo_id; })[0];
        if (!v) { return; }
        if (acao.tipo === 'veiculo_saida') {
            v.aberta = { id: 'local-' + acao.uuid, saida_em: d.saida_em, trecho: ui.origem_rotulo + ' → ' + ui.destino_rotulo, destino: ui.destino_rotulo, destino_tipo: d.destino_tipo, cliente_id: d.cliente_id || null, condutor: ui.motorista_nome, motivo: d.motivo, odo_saida: Number(d.odometro), equipamento: ui.equipamento_nome || null };
            v.odo = Number(d.odometro);
        } else if (acao.tipo === 'veiculo_entrada') {
            if (v.aberta) { v.local = { tipo: v.aberta.destino_tipo, cliente_id: v.aberta.cliente_id || null, rotulo: v.aberta.destino, texto: v.aberta.destino_tipo === 'outro' ? v.aberta.destino : null }; }
            v.aberta = null; v.odo = Number(d.odometro);
        } else { v.odo = Number(d.odometro); }
    }
    function reaplicarFrota() {
        return Promise.all([frotaGet(), dbAll('fila')]).then(function (r) {
            var f = r[0]; if (!f) { return; }
            r[1].filter(function (a) { return a.estado === 'pendente' && FROTA_TIPOS.indexOf(a.tipo) >= 0; }).sort(function (a, b) { return a.seq - b.seq; }).forEach(function (a) { aplicarLocalFrota(f, a); });
            return frotaSet(f);
        });
    }
    function enfileirarFrota(tipo, dados) {
        return frotaGet().then(function (f) {
            var id = uuid();
            if (f) { aplicarLocalFrota(f, { uuid: id, tipo: tipo, dados: dados }); }
            return (f ? frotaSet(f) : Promise.resolve()).then(function () { return enfileirar(tipo, dados, id); });
        });
    }

    function opts(pares, atual, vazio) {
        return (vazio ? '<option value="">' + esc(vazio) + '</option>' : '') + pares.map(function (p) { return '<option value="' + esc(p[0]) + '"' + (String(atual) === String(p[0]) ? ' selected' : '') + '>' + esc(p[1]) + '</option>'; }).join('');
    }
    function mapaPares(m) { return Object.keys(m || {}).map(function (k) { return [k, m[k]]; }); }
    function campo(rotulo, corpo) { return '<label class="co-lbl">' + rotulo + '</label>' + corpo; }
    function dataLocalInput() { return agoraLocal().slice(0, 16).replace(' ', 'T'); }
    function statusVeiculo(v) {
        if (v.aberta) { return { txt: 'EM TRÂNSITO', cor: 'amber', sub: 'Em trânsito: ' + v.aberta.trecho }; }
        if (v.local && v.local.tipo !== 'empresa') { return { txt: 'NO CLIENTE', cor: 'sky', sub: '📍 em ' + v.local.rotulo }; }
        return { txt: 'NA BASE', cor: 'green', sub: '' };
    }
    function sucessoFrota(root, titulo, tipo) {
        root.innerHTML = '<div class="co-wrap"><h1 class="co-h1">✔ ' + esc(titulo) + '</h1><p class="co-sub">Salvo neste aparelho. O envio ao servidor acontece sozinho quando houver internet.</p><a class="co-btn" href="' + BASE + 'logistica/' + tipo + '">Voltar</a><a class="co-btn alt" href="' + BASE + 'logistica">Logística</a></div><div class="co-pad"></div>';
    }
    function cabecalhoFrota(titulo, voltar) { return '<a class="co-sub" href="' + voltar + '">← Voltar</a><p class="co-sub" style="margin-top:.6rem">LOGÍSTICA · OFFLINE</p><h1 class="co-h1">' + esc(titulo) + '</h1>'; }

    function telaFrotaLista(root, tipo, titulo) {
        return frotaGet().then(function (f) {
            if (!f || !f.veiculos) { root.innerHTML = semPacote(); return; }
            function lista(q) {
                var t = String(q || '').trim().toLowerCase(), p = normPlaca(q);
                return f.veiculos.filter(function (v) { return !t || [v.pl, v.p, v.n].some(function (x) { return x && String(x).toLowerCase().indexOf(t) >= 0; }) || (p.length >= 3 && normPlaca(v.pl).indexOf(p) >= 0); }).slice(0, 40);
            }
            function pinta(q) {
                root.querySelector('#fl-itens').innerHTML = lista(q).map(function (v) {
                    var s = statusVeiculo(v);
                    return '<a class="co-card" href="' + BASE + 'logistica/' + tipo + '/' + v.id + '"><b>' + esc(v.pl || v.n) + ' ' + badge(s.txt, s.cor) + '</b><span class="co-sub">' + esc(v.n) + (v.p ? ' · PAT ' + esc(v.p) : '') + ' · ' + Number(v.odo).toLocaleString('pt-BR') + ' km</span>' + (s.sub ? '<br><span class="co-sub">' + esc(s.sub) + '</span>' : '') + '</a>';
                }).join('') || '<p class="co-sub" style="margin-top:.7rem">Nenhum veículo encontrado.</p>';
            }
            root.innerHTML = '<div class="co-wrap">' + cabecalhoFrota(titulo, BASE + 'logistica') + '<input class="co-in" id="fl-q" placeholder="Buscar por placa, patrimônio ou nome"><div id="fl-itens"></div></div><div class="co-pad"></div>';
            root.querySelector('#fl-q').addEventListener('input', function (e) { pinta(e.target.value); });
            pinta('');
        });
    }

    function veiculoDe(f, id) { return (f.veiculos || []).filter(function (v) { return v.id === id; })[0]; }
    function validaOdo(root, v, odo, just) {
        if (odo === '' || isNaN(Number(odo)) || Number(odo) < 0) { return 'Informe o km do painel.'; }
        if (Number(odo) < v.odo && !String(just || '').trim()) { return 'O km informado (' + odo + ') é menor que o último (' + v.odo + '). Confira ou informe a justificativa.'; }
        return null;
    }

    function telaSaidaEntrada(root, id) {
        return Promise.all([frotaGet(), dbAll('ativos')]).then(function (r) {
            var f = r[0], ativos = r[1], v = f && veiculoDe(f, id);
            if (!v) { root.innerHTML = semPacote(); return; }
            var $ = function (i) { return root.querySelector('#' + i); }, msg = function (t) { $('x-msg').textContent = t || ''; };
            var cab = '<div class="co-wrap">' + cabecalhoFrota('Entrada e saída', BASE + 'logistica/saida') + '<div class="co-card"><b>' + esc(v.pl || v.n) + '</b><span class="co-sub">' + esc(v.n) + ' · ' + Number(v.odo).toLocaleString('pt-BR') + ' km</span></div>';

            if (v.aberta) {   /* ---- ENTRADA (chegada) ---- */
                root.innerHTML = cab + '<div class="co-card"><b>Em trânsito desde ' + dataBR(v.aberta.saida_em) + '</b><span class="co-sub">Trecho: ' + esc(v.aberta.trecho) + '<br>' + esc(v.aberta.condutor || '') + ' · ' + esc(v.aberta.motivo || '') + (v.aberta.equipamento ? '<br>Equipamento levado: ' + esc(v.aberta.equipamento) : '') + '</span></div>' +
                    campo('Data e hora da chegada', '<input class="co-in" id="e-data" type="datetime-local" value="' + dataLocalInput() + '">') +
                    campo('Odômetro na chegada (km)', '<input class="co-in" id="e-odo" type="number" inputmode="numeric" value="' + v.odo + '">') +
                    campo('Justificativa (só se o km for menor)', '<input class="co-in" id="e-just">') +
                    campo('Combustível', '<select class="co-in" id="e-comb">' + opts(COMBUSTIVEL_NIVEL, '') + '</select>') +
                    campo('Observações (avarias, ocorrências)', '<textarea class="co-in" id="e-obs"></textarea>') +
                    '<button class="co-btn ok" id="e-ok">REGISTRAR ENTRADA</button><p class="co-sub" id="x-msg" style="color:#f87171;margin-top:.6rem"></p></div><div class="co-pad"></div>';
                $('e-ok').addEventListener('click', function () {
                    var odo = $('e-odo').value, erro = validaOdo(root, v, odo, $('e-just').value);
                    if (erro) { msg(erro); return; }
                    if (!$('e-data').value) { msg('Informe a data e a hora.'); return; }
                    $('e-ok').disabled = true;
                    enfileirarFrota('veiculo_entrada', { ativo_id: v.id, retorno_em: $('e-data').value, odometro: odo, combustivel: $('e-comb').value, observacoes: $('e-obs').value, justificativa_odometro: $('e-just').value }).then(function () { sucessoFrota(root, 'Entrada registrada', 'saida'); });
                });
                return;
            }

            /* ---- SAÍDA ---- */
            var motoristas = (f.motoristas || []).map(function (m) { return [m.id, m.nome + (m.cnh_vencida ? ' (CNH vencida)' : '')]; });
            var clientes = (f.clientes || []).map(function (c) { return [c.id, c.nome]; });
            var local = v.local || { tipo: 'empresa' };
            var tipos = [['empresa', 'Empresa'], ['cliente', 'Cliente'], ['outro', 'Outro local']];
            var st = { eq: null };
            function bloco(pref, titulo, tipo, cliente, texto) {
                return '<div class="co-card"><b style="font-size:.75rem">' + titulo + '</b><select class="co-in" id="s-' + pref + 't" style="margin-top:.5rem">' + opts(tipos, tipo) + '</select>' +
                    '<select class="co-in" id="s-' + pref + 'c" style="margin-top:.5rem">' + opts(clientes, cliente, 'Escolha o cliente') + '</select>' +
                    '<input class="co-in" id="s-' + pref + 'x" placeholder="Nome do local" style="margin-top:.5rem" value="' + esc(texto || '') + '"></div>';
            }
            root.innerHTML = cab + (local.tipo !== 'empresa' ? '<p class="co-sub" style="margin-top:.7rem;color:#38bdf8">📍 Veículo está em: ' + esc(local.rotulo) + '</p>' : '') +
                campo('Finalidade', '<select class="co-in" id="s-fin">' + opts(mapaPares(f.rotulos && f.rotulos.finalidades), 'visita_tecnica') + '</select>') +
                campo('Motorista', '<select class="co-in" id="s-mot">' + opts(motoristas, v.titular ? v.titular.motorista_id : '', 'Escolha o motorista') + '</select>') +
                bloco('o', 'SAINDO DE', local.tipo, local.cliente_id, local.texto) + bloco('d', 'INDO PARA', local.tipo === 'empresa' ? 'cliente' : 'empresa', '', '') +
                '<div id="s-percbox"></div>' +
                campo('Motivo', '<textarea class="co-in" id="s-motivo"></textarea>') +
                campo('Equipamento que vai no veículo (se for)', '<div id="s-eq"></div>') +
                campo('Data e hora da saída', '<input class="co-in" id="s-data" type="datetime-local" value="' + dataLocalInput() + '">') +
                campo('Odômetro na saída (km)', '<input class="co-in" id="s-odo" type="number" inputmode="numeric" value="' + v.odo + '">') +
                campo('Justificativa (só se o km for menor)', '<input class="co-in" id="s-just">') +
                campo('Combustível', '<select class="co-in" id="s-comb">' + opts(COMBUSTIVEL_NIVEL, '') + '</select>') +
                campo('Observações', '<textarea class="co-in" id="s-obs"></textarea>') +
                '<button class="co-btn ok" id="s-ok">REGISTRAR SAÍDA</button><p class="co-sub" id="x-msg" style="color:#f87171;margin-top:.6rem"></p></div><div class="co-pad"></div>';

            function lugar(pref) { var t = $('s-' + pref + 't').value; return { tipo: t, cliente_id: t === 'cliente' ? $('s-' + pref + 'c').value : null, texto: t === 'outro' ? $('s-' + pref + 'x').value : null }; }
            function rotuloLugar(l) { return l.tipo === 'empresa' ? 'Empresa' : (l.tipo === 'cliente' ? ((clientes.filter(function (c) { return c[0] === l.cliente_id; })[0] || [0, 'Cliente'])[1]) : (l.texto || '')); }
            function visivel() {
                ['o', 'd'].forEach(function (p) { var t = $('s-' + p + 't').value; $('s-' + p + 'c').style.display = t === 'cliente' ? '' : 'none'; $('s-' + p + 'x').style.display = t === 'outro' ? '' : 'none'; });
                var compat = (f.percursos || []).filter(function (p) { return percursoLiga(p, lugar('o'), lugar('d')); });
                $('s-percbox').innerHTML = compat.length ? campo('Percurso (recebe o km da viagem)', '<select class="co-in" id="s-perc">' + opts(compat.map(function (p) { return [p.id, p.nome + (p.previsto ? ' · previsto ' + p.previsto + ' km' : '')]; }), '', compat.length === 1 ? 'Automático: ' + compat[0].nome : 'Escolha o percurso') + '</select>') : '';
            }
            function pintaEquip(lista) {
                var eq = st.eq;
                $('s-eq').innerHTML = eq ? '<div class="co-card" style="margin:0"><b>' + esc(eq.n) + '</b><span class="co-sub">PAT ' + esc(eq.p || '—') + '</span><button type="button" class="co-seg" id="s-eq-x" style="margin-top:.5rem">Remover</button></div>'
                    : '<input class="co-in" id="s-eq-q" placeholder="Patrimônio, placa ou nome (vazio se não levar)">' + (lista || []).map(function (a) { return '<a class="co-card" href="#" data-eq="' + a.id + '"><b>' + esc(a.n) + '</b><span class="co-sub">PAT ' + esc(a.p || '—') + '</span></a>'; }).join('');
                var x = $('s-eq-x'); if (x) { x.addEventListener('click', function () { st.eq = null; pintaEquip(); }); }
                var q = $('s-eq-q'); if (q) { q.addEventListener('input', function (e) { buscarAtivos(e.target.value).then(function (r2) { var t = q.value; pintaEquip(r2.filter(function (a) { return !a.v && a.id !== v.id; }).slice(0, 6)); var q2 = $('s-eq-q'); q2.value = t; q2.focus(); }); }); }
                root.querySelectorAll('[data-eq]').forEach(function (a) { a.addEventListener('click', function (ev) { ev.preventDefault(); st.eq = ativos.filter(function (x) { return x.id === a.getAttribute('data-eq'); })[0]; pintaEquip(); }); });
            }
            ['o', 'd'].forEach(function (p) { ['t', 'c', 'x'].forEach(function (s) { $('s-' + p + s).addEventListener('change', visivel); $('s-' + p + s).addEventListener('input', function () { if (s === 'x') { visivel(); } }); }); });
            visivel(); pintaEquip();

            $('s-ok').addEventListener('click', function () {
                var o = lugar('o'), d = lugar('d'), odo = $('s-odo').value;
                if (!$('s-mot').value) { msg('Escolha o motorista.'); return; }
                if ((o.tipo === 'cliente' && !o.cliente_id) || (d.tipo === 'cliente' && !d.cliente_id)) { msg('Escolha o cliente.'); return; }
                if ((o.tipo === 'outro' && !String(o.texto).trim()) || (d.tipo === 'outro' && !String(d.texto).trim())) { msg('Informe o nome do local.'); return; }
                if (!$('s-motivo').value.trim()) { msg('Informe o motivo da saída.'); return; }
                if (!$('s-data').value) { msg('Informe a data e a hora.'); return; }
                var erro = validaOdo(root, v, odo, $('s-just').value); if (erro) { msg(erro); return; }
                var perc = $('s-perc') ? $('s-perc').value : '', mot = motoristas.filter(function (m) { return m[0] === $('s-mot').value; })[0];
                $('s-ok').disabled = true;
                enfileirarFrota('veiculo_saida', {
                    ativo_id: v.id, finalidade: $('s-fin').value, motorista_id: $('s-mot').value, equipamento_id: st.eq ? st.eq.id : null,
                    origem_tipo: o.tipo, origem_cliente_id: o.cliente_id, origem: o.texto || '', destino_tipo: d.tipo, cliente_id: d.cliente_id, destino: d.texto || '',
                    percurso_id: perc || null, motivo: $('s-motivo').value, saida_em: $('s-data').value, odometro: odo, combustivel: $('s-comb').value,
                    observacoes: $('s-obs').value, justificativa_odometro: $('s-just').value,
                    ui: { origem_rotulo: rotuloLugar(o), destino_rotulo: rotuloLugar(d), motorista_nome: mot ? mot[1] : '', equipamento_nome: st.eq ? st.eq.n : null },
                }).then(function () { sucessoFrota(root, 'Saída registrada', 'saida'); });
            });
        });
    }

    function telaAbastecimento(root, id) {
        return frotaGet().then(function (f) {
            var v = f && veiculoDe(f, id); if (!v) { root.innerHTML = semPacote(); return; }
            var $ = function (i) { return root.querySelector('#' + i); }, msg = function (t) { $('x-msg').textContent = t || ''; };
            var motoristas = (f.motoristas || []).map(function (m) { return [m.id, m.nome]; });
            var r = f.rotulos || {};
            root.innerHTML = '<div class="co-wrap">' + cabecalhoFrota('Abastecimento', BASE + 'logistica/abastecimento') + '<div class="co-card"><b>' + esc(v.pl || v.n) + '</b><span class="co-sub">' + esc(v.n) + ' · ' + Number(v.odo).toLocaleString('pt-BR') + ' km</span></div>' +
                campo('Data e hora', '<input class="co-in" id="a-data" type="datetime-local" value="' + dataLocalInput() + '">') +
                campo('Odômetro no abastecimento (km)', '<input class="co-in" id="a-odo" type="number" inputmode="numeric" value="' + v.odo + '">') +
                campo('Justificativa (só se o km for menor)', '<input class="co-in" id="a-just">') +
                campo('Combustível', '<select class="co-in" id="a-comb">' + opts(mapaPares(r.combustiveis), 'diesel_s10') + '</select>') +
                campo('Litros', '<input class="co-in" id="a-lit" type="number" inputmode="decimal" step="0.01">') +
                campo('Valor total (R$)', '<input class="co-in" id="a-val" type="number" inputmode="decimal" step="0.01">') +
                '<label class="co-lbl" style="display:flex;gap:.6rem;align-items:center"><input type="checkbox" id="a-cheio" checked style="width:1.2rem;height:1.2rem"> Encheu o tanque</label>' +
                campo('Origem', '<select class="co-in" id="a-ori">' + opts(mapaPares(r.origens), 'externo') + '</select>') +
                campo('Posto', '<input class="co-in" id="a-posto">') + campo('Nota fiscal / cupom', '<input class="co-in" id="a-nf">') +
                campo('Motorista', '<select class="co-in" id="a-mot">' + opts(motoristas, v.titular ? v.titular.motorista_id : '', 'Não informar') + '</select>') +
                campo('Observações', '<textarea class="co-in" id="a-obs"></textarea>') +
                '<button class="co-btn ok" id="a-ok">REGISTRAR ABASTECIMENTO</button><p class="co-sub" id="x-msg" style="color:#f87171;margin-top:.6rem"></p></div><div class="co-pad"></div>';
            $('a-ok').addEventListener('click', function () {
                var erro = validaOdo(root, v, $('a-odo').value, $('a-just').value); if (erro) { msg(erro); return; }
                if (!(Number($('a-lit').value) > 0)) { msg('Informe os litros abastecidos.'); return; }
                if (!(Number($('a-val').value) > 0)) { msg('Informe o valor total.'); return; }
                if (!$('a-data').value) { msg('Informe a data e a hora.'); return; }
                $('a-ok').disabled = true;
                enfileirarFrota('abastecimento', { ativo_id: v.id, abastecido_em: $('a-data').value, odometro: $('a-odo').value, combustivel: $('a-comb').value, litros: $('a-lit').value, valor_total: $('a-val').value, tanque_cheio: $('a-cheio').checked, origem: $('a-ori').value, posto: $('a-posto').value, nota_fiscal: $('a-nf').value, motorista_id: $('a-mot').value || null, observacoes: $('a-obs').value, justificativa_odometro: $('a-just').value })
                    .then(function () { sucessoFrota(root, 'Abastecimento registrado', 'abastecimento'); });
            });
        });
    }

    function telaMarcarKm(root, id) {
        return frotaGet().then(function (f) {
            var v = f && veiculoDe(f, id); if (!v) { root.innerHTML = semPacote(); return; }
            var $ = function (i) { return root.querySelector('#' + i); }, msg = function (t) { $('x-msg').textContent = t || ''; };
            root.innerHTML = '<div class="co-wrap">' + cabecalhoFrota('Marcar km', BASE + 'logistica/km') + '<div class="co-card"><b>' + esc(v.pl || v.n) + '</b><span class="co-sub">' + esc(v.n) + ' · último km: ' + Number(v.odo).toLocaleString('pt-BR') + '</span></div>' +
                campo('Km no painel agora', '<input class="co-in" id="k-odo" type="number" inputmode="numeric" value="' + v.odo + '" style="font-size:1.2rem;font-weight:700">') +
                campo('Justificativa (só se o km for menor, ex.: painel trocado)', '<input class="co-in" id="k-just">') +
                '<button class="co-btn ok" id="k-ok">MARCAR KM</button><p class="co-sub" id="x-msg" style="color:#f87171;margin-top:.6rem"></p></div><div class="co-pad"></div>';
            $('k-ok').addEventListener('click', function () {
                var erro = validaOdo(root, v, $('k-odo').value, $('k-just').value); if (erro) { msg(erro); return; }
                $('k-ok').disabled = true;
                enfileirarFrota('km_marcar', { ativo_id: v.id, odometro: $('k-odo').value, justificativa: $('k-just').value, lido_em: agoraLocal() }).then(function () { sucessoFrota(root, 'Km marcado', 'km'); });
            });
        });
    }

    /* ---------- roteamento da tela offline ---------- */
    function renderizarOffline(root) {
        var rel = location.pathname.indexOf(BASE) === 0 ? location.pathname.slice(BASE.length) : location.pathname.replace(/^\//, '');
        rel = rel.replace(/\/$/, '');
        var q = new URLSearchParams(location.search), m;
        var pintar = function (p) { return p.then(function (html) { if (html) { var est = document.getElementById('colab-estatico'); if (est) { est.style.display = 'none'; } root.innerHTML = html; } }); };

        if ((m = rel.match(/^logistica\/(saida|abastecimento|km)(?:\/([^/]+))?$/))) {
            var estatico = document.getElementById('colab-estatico'); if (estatico) { estatico.style.display = 'none'; }
            var nomes = { saida: 'Entrada e saída', abastecimento: 'Abastecimento', km: 'Marcar km' };
            if (!m[2]) { return telaFrotaLista(root, m[1], nomes[m[1]]); }
            if (!UUID.test(m[2])) { return Promise.resolve(); }
            return m[1] === 'saida' ? telaSaidaEntrada(root, m[2]) : (m[1] === 'abastecimento' ? telaAbastecimento(root, m[2]) : telaMarcarKm(root, m[2]));
        }
        if ((m = rel.match(/^os\/([^/]+)\/executar$/)) && UUID.test(m[1])) { location.replace(BASE + 'os/' + m[1] + '/offline'); return Promise.resolve(); }
        if ((m = rel.match(/^os\/([^/]+)\/offline$/)) && UUID.test(m[1])) { var est = document.getElementById('colab-estatico'); if (est) { est.style.display = 'none'; } return telaExecucao(root, m[1]); }
        if ((m = rel.match(/^dossie\/([^/]+)$/)) && UUID.test(m[1])) { return pintar(telaDossie(m[1])); }
        if ((m = rel.match(/^os\/ativo\/([^/]+)$/)) && UUID.test(m[1])) { return pintar(telaOsAtivo(m[1])); }
        if ((m = rel.match(/^os\/([^/]+)$/)) && UUID.test(m[1])) { return pintar(telaOs(m[1])); }
        if (rel === 'dossie' || rel === 'os') {
            var termo = q.get('busca') || q.get('q'), destino = rel === 'dossie' ? 'dossie/' : 'os/ativo/';
            if (!termo) { return Promise.resolve(); }
            return pintar(dbAll('ativos').then(function (l) {
                if (!l.length) { return semPacote(); }
                return buscarAtivos(termo).then(function (ach) {
                    if (ach.length === 1) { location.replace(BASE + destino + ach[0].id); return null; }
                    if (!ach.length) { return '<div class="co-wrap"><a class="co-sub" href="' + BASE + '">← Início</a><h1 class="co-h1">Nenhum equipamento encontrado</h1><p class="co-sub">Nada com "' + esc(termo) + '" nos dados deste aparelho.</p></div>'; }
                    return listaAtivos(ach, destino, ach.length + ' equipamentos encontrados');
                });
            }));
        }
        return Promise.resolve();
    }

    /* ---------- OS concluídas neste aparelho: esconde "Abrir OS" nas listas guardadas ---------- */
    function marcarConcluidas() {
        var links = document.querySelectorAll('a[href*="/executar"]');
        if (!links.length) { return; }
        dbAll('kv').then(function (kv) {
            var concl = kv.filter(function (e) { return e.k.indexOf('concl:') === 0; }).map(function (e) { return e.k.slice(6); });
            links.forEach(function (a) { concl.forEach(function (id) { if (a.getAttribute('href').indexOf(id) >= 0) { a.outerHTML = '<span class="co-sub" style="display:block;margin-top:.7rem">✔ Concluída neste aparelho — aguardando envio.</span>'; } }); });
        });
    }

    /* ---------- inicialização ---------- */
    window.Colab = { sincronizar: sincronizar, enviarFila: enviarFila, enfileirar: enfileirar, buscarAtivos: buscarAtivos, db: { all: dbAll, get: dbGet, kvGet: kvGet } };

    function iniciar() {
        estilos();
        var app = document.getElementById('colab-app');
        if (app) { renderizarOffline(app).catch(function () {}); }
        atualizarBarra();
        marcarConcluidas();
        window.addEventListener('online', function () { atualizarBarra(); enviarFila().then(function () { return sincronizar(true); }); });
        window.addEventListener('offline', atualizarBarra);
        if (navigator.onLine) { enviarFila().then(function () { return sincronizar(false); }); }
    }
    if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', iniciar); } else { iniciar(); }
})();
