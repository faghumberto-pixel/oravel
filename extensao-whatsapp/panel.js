// Painel lateral do Oravel (Shadow DOM, isolado do CSS do WhatsApp). Todo conteúdo vindo da API entra por
// texto puro (nunca como HTML), então nome/observação de lead não executa nada na página.
(function () {
  const L = self.OravelLib;

  const CSS = `
    :host { all: initial; }
    * { box-sizing: border-box; font-family: system-ui, -apple-system, "Segoe UI", sans-serif; }
    .fab { position: fixed; right: 0; top: 40%; z-index: 2147483000; background: #0f172a; color: #fff; border: 0; border-radius: 10px 0 0 10px;
           padding: 12px 10px; font-weight: 700; font-size: 13px; cursor: pointer; writing-mode: vertical-rl; letter-spacing: .04em; box-shadow: -2px 2px 10px rgba(0,0,0,.25); }
    .painel { position: fixed; right: 0; top: 0; bottom: 0; width: 360px; max-width: 100vw; z-index: 2147483001; background: #fff; color: #0f172a;
              box-shadow: -4px 0 24px rgba(0,0,0,.25); display: flex; flex-direction: column; font-size: 13px; }
    .topo { display: flex; align-items: center; justify-content: space-between; padding: 12px 14px; background: #0f172a; color: #fff; }
    .topo b { font-size: 14px; } .topo button { background: none; border: 0; color: #fff; font-size: 18px; cursor: pointer; }
    .abas { display: flex; border-bottom: 1px solid #e4e7ec; }
    .abas button { flex: 1; padding: 10px 4px; border: 0; background: none; cursor: pointer; font-weight: 600; color: #5b6472; border-bottom: 2px solid transparent; }
    .abas button.on { color: #0f172a; border-bottom-color: #f97316; }
    .corpo { flex: 1; overflow-y: auto; padding: 14px; display: grid; gap: 10px; align-content: start; }
    .aviso { background: #fff7ed; color: #9a3412; padding: 10px; border-radius: 8px; }
    .erro { background: #fef2f2; color: #991b1b; padding: 10px; border-radius: 8px; }
    .ok { background: #f0fdf4; color: #166534; padding: 10px; border-radius: 8px; }
    .cartao { border: 1px solid #e4e7ec; border-radius: 10px; padding: 10px; display: grid; gap: 4px; }
    .cartao .tipo { font-size: 11px; font-weight: 700; text-transform: uppercase; color: #f97316; }
    .mudo { color: #5b6472; font-size: 12px; }
    label { font-weight: 600; font-size: 12px; display: grid; gap: 4px; }
    input, select, textarea { font: inherit; padding: 8px; border: 1px solid #cbd5e1; border-radius: 8px; width: 100%; }
    textarea { min-height: 150px; resize: vertical; }
    .linha { display: flex; gap: 8px; } .linha > * { flex: 1; }
    button.p { background: #0f172a; color: #fff; border: 0; border-radius: 8px; padding: 9px 12px; font-weight: 700; cursor: pointer; }
    button.s { background: #fff; color: #0f172a; border: 1px solid #cbd5e1; border-radius: 8px; padding: 9px 12px; font-weight: 600; cursor: pointer; }
    button:disabled { opacity: .5; cursor: default; }
    a { color: #c2410c; font-weight: 600; }
  `;

  function h(tag, attrs, ...filhos) {
    const el = document.createElement(tag);
    Object.entries(attrs || {}).forEach(([k, v]) => {
      if (v === undefined || v === null || v === false) return;
      if (k.startsWith('on')) el.addEventListener(k.slice(2), v);
      else if (k === 'class') el.className = v;
      else if (k === 'value') el.value = v;
      else el.setAttribute(k, v === true ? '' : v);
    });
    filhos.flat().forEach((f) => { if (f !== null && f !== undefined && f !== false) el.append(f.nodeType ? f : document.createTextNode(String(f))); });
    return el;
  }

  const moeda = (v) => (v == null ? '' : Number(v).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' }));

  function iniciar(ctx) {
    const host = h('div');
    const raiz = host.attachShadow({ mode: 'closed' });
    raiz.append(h('style', {}, CSS));
    const fab = h('button', { class: 'fab', onclick: abrir }, 'Oravel CRM');
    const painel = h('div', { class: 'painel', style: 'display:none' });
    raiz.append(fab, painel);
    document.documentElement.append(host);

    const estado = { aba: 'contato', chat: null, telefone: '', resultados: null, selecionado: null, oport: null, config: null, msg: null, rascunho: null };

    function abrir() { fab.style.display = 'none'; painel.style.display = 'flex'; lerChat(); }
    function fechar() { painel.style.display = 'none'; fab.style.display = ''; }

    function lerChat() {
      estado.chat = ctx.chatAberto();
      if (estado.chat.ok) { estado.telefone = estado.chat.telefone || ''; estado.resultados = null; estado.selecionado = null; estado.oport = null; estado.msg = null; estado.rascunho = null; if (estado.telefone) buscar(); }
      desenhar();
    }

    async function chamar(operacao, params) {
      try { return await ctx.api(operacao, params); }
      catch (e) { estado.msg = { tipo: 'erro', texto: e.message, campos: e.campos }; desenhar(); throw e; }
    }

    async function buscar() {
      const tel = L.normalizarTelefone(estado.telefone);
      if (!tel) { estado.msg = { tipo: 'erro', texto: 'Informe um telefone com DDD.' }; return desenhar(); }
      estado.msg = null; estado.resultados = null; desenhar();
      try { estado.resultados = await chamar('buscar', { telefone: tel }); } catch (e) { return; }
      if (estado.resultados.length === 1) await selecionar(estado.resultados[0]);
      desenhar();
    }

    async function selecionar(item) {
      estado.selecionado = item; estado.oport = null; desenhar();
      try { estado.oport = await chamar('oportunidades', { tipo: item.tipo, id: item.id }); } catch (e) { return; }
      desenhar();
    }

    async function carregarConfig() {
      if (estado.config) return estado.config;
      estado.config = await chamar('configuracao', {});
      return estado.config;
    }

    // ---------------------------------------------------------------- abas
    function abaContato() {
      const c = [];
      c.push(h('label', {}, 'Telefone do contato',
        h('div', { class: 'linha' },
          h('input', { value: estado.telefone, placeholder: '19 99999-0000', oninput: (e) => { estado.telefone = e.target.value; } }),
          h('button', { class: 'p', style: 'flex:0 0 auto', onclick: buscar }, 'Buscar'))));
      if (!estado.chat.telefone) c.push(h('p', { class: 'mudo' }, 'Não foi possível ler o telefone deste chat (contato salvo ou oculto). Digite o número para buscar.'));
      if (estado.resultados && !estado.resultados.length) c.push(h('div', { class: 'aviso' }, 'Ninguém com este telefone no CRM. Use a aba “Novo lead”.'));
      (estado.resultados || []).forEach((r) => {
        c.push(h('div', { class: 'cartao' },
          h('span', { class: 'tipo' }, r.tipo === 'conta' ? 'Cliente (conta)' : 'Lead'),
          h('b', {}, r.nome || '(sem nome)'),
          r.etapa ? h('span', { class: 'mudo' }, 'Etapa: ' + r.etapa.nome + (r.valor != null ? ' · ' + moeda(r.valor) : '')) : null,
          h('div', { class: 'linha' },
            h('button', { class: 's', onclick: () => selecionar(r) }, 'Ver oportunidades'),
            r.url ? h('a', { href: r.url, target: '_blank', rel: 'noopener' }, 'Abrir no Oravel') : null)));
      });
      if (estado.oport) {
        c.push(h('b', {}, 'Negócios em aberto'));
        if (!estado.oport.leads_abertos.length) c.push(h('p', { class: 'mudo' }, 'Nenhum.'));
        estado.oport.leads_abertos.forEach((l) => c.push(h('div', { class: 'cartao' },
          h('b', {}, l.nome), h('span', { class: 'mudo' }, (l.etapa ? l.etapa.nome : '') + (l.valor != null ? ' · ' + moeda(l.valor) : '')),
          h('div', { class: 'linha' },
            h('button', { class: 's', onclick: () => { estado.leadAlvo = l; estado.aba = 'conversa'; desenhar(); } }, 'Registrar conversa'),
            h('button', { class: 's', onclick: () => { estado.leadAlvo = l; estado.aba = 'etapa'; desenhar(); } }, 'Mover etapa'),
            l.url ? h('a', { href: l.url, target: '_blank', rel: 'noopener' }, 'Abrir') : null))));
        c.push(h('b', {}, 'Propostas em andamento'));
        if (!estado.oport.propostas_em_andamento.length) c.push(h('p', { class: 'mudo' }, 'Nenhuma.'));
        estado.oport.propostas_em_andamento.forEach((p) => c.push(h('div', { class: 'cartao' },
          h('b', {}, p.origem === 'crm' ? `#${p.numero} ${p.titulo || ''}` : 'Proposta comercial'),
          h('span', { class: 'mudo' }, p.status_rotulo + ' · ' + moeda(p.valor)),
          p.url ? h('a', { href: p.url, target: '_blank', rel: 'noopener' }, 'Abrir') : null)));
      }
      return c;
    }

    function abaNovoLead() {
      const d = estado.rascunhoLead || (estado.rascunhoLead = { nome: estado.chat.nome || '', empresa: '', telefone: estado.telefone, documento: '', observacao: '', forcar: false });
      const cfg = estado.config;
      if (!cfg) { carregarConfig().then(desenhar).catch(() => {}); return [h('p', { class: 'mudo' }, 'Carregando funis…')]; }
      const funil = cfg.funis.find((f) => f.id === d.funil_id) || cfg.funis[0];
      d.funil_id = funil ? funil.id : undefined;
      const campo = (rotulo, chave, ph) => h('label', {}, rotulo, h('input', { value: d[chave] || '', placeholder: ph || '', oninput: (e) => { d[chave] = e.target.value; } }));
      return [
        campo('Nome do contato', 'nome'), campo('Empresa', 'empresa'), campo('Telefone', 'telefone'),
        h('label', {}, 'CNPJ (opcional)', h('div', { class: 'linha' },
          h('input', { value: d.documento || '', oninput: (e) => { d.documento = e.target.value; } }),
          h('button', { class: 's', style: 'flex:0 0 auto', onclick: async () => {
            const r = await chamar('cnpj', { cnpj: d.documento }).catch(() => null);
            if (r && r.dados) { d.empresa = d.empresa || r.dados.company_name || ''; estado.msg = r.duplicidade ? { tipo: 'aviso', texto: r.duplicidade } : { tipo: 'ok', texto: 'Dados da Receita aplicados.' }; desenhar(); }
          } }, 'Receita'))),
        h('label', {}, 'Funil', h('select', { onchange: (e) => { d.funil_id = e.target.value; d.etapa_id = undefined; desenhar(); } },
          cfg.funis.map((f) => h('option', { value: f.id, selected: f.id === d.funil_id }, f.nome)))),
        h('label', {}, 'Etapa', h('select', { onchange: (e) => { d.etapa_id = e.target.value; } },
          (funil ? funil.etapas : []).filter((e) => e.tipo === 'aberta').map((e) => h('option', { value: e.id, selected: e.id === d.etapa_id }, e.nome)))),
        h('label', {}, 'Origem', h('select', { onchange: (e) => { d.origem = e.target.value; } },
          h('option', { value: 'WhatsApp' }, 'WhatsApp'), cfg.origens.filter((o) => o.nome !== 'WhatsApp').map((o) => h('option', { value: o.nome, selected: o.nome === d.origem }, o.nome)))),
        h('label', {}, 'Observação', h('textarea', { style: 'min-height:70px', oninput: (e) => { d.observacao = e.target.value; } }, d.observacao || '')),
        h('button', { class: 'p', onclick: criarLead }, d.forcar ? 'Criar mesmo assim' : 'Criar lead'),
      ];
    }

    async function criarLead() {
      const d = estado.rascunhoLead;
      const dados = { nome: d.nome || undefined, empresa: d.empresa || undefined, telefone: L.normalizarTelefone(d.telefone) || d.telefone || undefined,
        documento: d.documento || undefined, funil_id: d.funil_id, etapa_id: d.etapa_id || undefined, origem: d.origem || 'WhatsApp',
        observacao: d.observacao || undefined, preencher_cnpj: !!d.documento, forcar: d.forcar || undefined };
      estado.msg = null;
      try {
        const lead = await chamar('criarLead', { dados });
        estado.msg = { tipo: 'ok', texto: 'Lead criado: ' + (lead.nome || '') };
        estado.rascunhoLead = null; estado.aba = 'contato'; await buscar();
      } catch (e) {
        if (e.codigo === 'duplicado' || e.codigo === 'ja_e_conta') { d.forcar = true; estado.msg = { tipo: 'aviso', texto: e.message + ' Clique em “Criar mesmo assim” se for outro negócio.' }; desenhar(); }
      }
    }

    function abaConversa() {
      const lead = estado.leadAlvo;
      if (!lead) return [h('p', { class: 'mudo' }, 'Na aba “Contato”, escolha um negócio e clique em “Registrar conversa”.')];
      const r = estado.rascunho || (estado.rascunho = { texto: '', direcao: 'entrada', proximo_passo: '', proximo_contato: '', audio: null });
      return [
        h('b', {}, 'Negócio: ' + lead.nome),
        h('button', { class: 's', onclick: () => {
          const msgs = ctx.lerMensagensVisiveis();
          if (!msgs.length) { estado.msg = { tipo: 'aviso', texto: 'Nenhuma mensagem de texto visível. Role a conversa até o trecho desejado.' }; return desenhar(); }
          r.texto = L.formatarConversa(msgs, 'Eu'); r.direcao = L.direcaoSugerida(msgs); estado.msg = { tipo: 'ok', texto: msgs.length + ' mensagens lidas. Revise antes de salvar.' }; desenhar();
        } }, 'Ler mensagens visíveis na tela'),
        h('label', {}, 'Resumo / conversa (editável)', h('textarea', { oninput: (e) => { r.texto = e.target.value; } }, r.texto)),
        h('div', { class: 'linha' },
          h('label', {}, 'Direção', h('select', { onchange: (e) => { r.direcao = e.target.value; } },
            h('option', { value: 'entrada', selected: r.direcao === 'entrada' }, 'Recebida'), h('option', { value: 'saida', selected: r.direcao === 'saida' }, 'Enviada'))),
          h('label', {}, 'Próximo contato', h('input', { type: 'date', value: r.proximo_contato, oninput: (e) => { r.proximo_contato = e.target.value; } }))),
        h('label', {}, 'Próximo passo', h('input', { value: r.proximo_passo, oninput: (e) => { r.proximo_passo = e.target.value; } })),
        h('label', {}, 'Áudio (opcional, escolha o arquivo)', h('input', { type: 'file', accept: 'audio/*', onchange: (e) => { r.audio = e.target.files[0] || null; } })),
        h('button', { class: 'p', onclick: registrar }, 'Salvar no CRM'),
      ];
    }

    function lerArquivo(arquivo) {
      return new Promise((res, rej) => { const fr = new FileReader(); fr.onload = () => res({ dataUrl: fr.result, nome: arquivo.name }); fr.onerror = rej; fr.readAsDataURL(arquivo); });
    }

    async function registrar() {
      const r = estado.rascunho;
      if (!r.texto.trim() && !r.audio) { estado.msg = { tipo: 'erro', texto: 'Escreva o resumo ou escolha um áudio.' }; return desenhar(); }
      const dados = { canal: 'whatsapp', direcao: r.direcao, resumo: r.texto.trim() || undefined, proximo_passo: r.proximo_passo || undefined, proximo_contato: r.proximo_contato || undefined };
      try {
        const audio = r.audio ? await lerArquivo(r.audio) : null;
        await chamar('registrarInteracao', { id: estado.leadAlvo.id, dados, audio });
        estado.msg = { tipo: 'ok', texto: 'Conversa registrada no CRM.' }; estado.rascunho = null; estado.aba = 'contato'; desenhar();
      } catch (e) { /* mensagem já exibida por chamar() */ }
    }

    function abaEtapa() {
      const lead = estado.leadAlvo;
      if (!lead) return [h('p', { class: 'mudo' }, 'Na aba “Contato”, escolha um negócio e clique em “Mover etapa”.')];
      const cfg = estado.config;
      if (!cfg) { carregarConfig().then(desenhar).catch(() => {}); return [h('p', { class: 'mudo' }, 'Carregando funis…')]; }
      const m = estado.mover || (estado.mover = { etapa_id: '', motivo: '', detalhe: '' });
      const etapa = cfg.funis.flatMap((f) => f.etapas).find((e) => e.id === m.etapa_id);
      const perdida = etapa && etapa.tipo === 'perdido';
      const motivo = perdida ? cfg.motivos_perda.find((x) => x.slug === m.motivo) : null;
      return [
        h('b', {}, 'Negócio: ' + lead.nome + (lead.etapa ? ' (hoje: ' + lead.etapa.nome + ')' : '')),
        h('label', {}, 'Nova etapa', h('select', { onchange: (e) => { m.etapa_id = e.target.value; desenhar(); } },
          h('option', { value: '' }, 'Escolha…'),
          cfg.funis.map((f) => h('optgroup', { label: f.nome }, f.etapas.map((e) => h('option', { value: e.id, selected: e.id === m.etapa_id }, e.nome + (e.tipo === 'ganho' ? ' ✓' : e.tipo === 'perdido' ? ' ✗' : '')))))) ),
        perdida ? h('label', {}, 'Motivo da perda', h('select', { onchange: (e) => { m.motivo = e.target.value; desenhar(); } },
          h('option', { value: '' }, 'Escolha…'), cfg.motivos_perda.map((x) => h('option', { value: x.slug, selected: x.slug === m.motivo }, x.nome)))) : null,
        motivo && motivo.exige_detalhe ? h('label', {}, 'Detalhe', h('input', { value: m.detalhe, oninput: (e) => { m.detalhe = e.target.value; } })) : null,
        h('button', { class: 'p', onclick: async () => {
          if (!m.etapa_id) { estado.msg = { tipo: 'erro', texto: 'Escolha a etapa.' }; return desenhar(); }
          try {
            await chamar('moverEtapa', { id: lead.id, dados: { etapa_id: m.etapa_id, motivo: m.motivo || undefined, detalhe: m.detalhe || undefined } });
            estado.msg = { tipo: 'ok', texto: 'Etapa atualizada.' }; estado.mover = null; estado.aba = 'contato'; await buscar();
          } catch (e) { /* exibido */ }
        } }, 'Mover'),
      ];
    }

    // ---------------------------------------------------------------- desenho
    function desenhar() {
      painel.replaceChildren(
        h('div', { class: 'topo' }, h('b', {}, 'Oravel CRM' + (estado.chat && estado.chat.nome ? ' · ' + estado.chat.nome : '')), h('div', {},
          h('button', { title: 'Reler o chat aberto', onclick: lerChat }, '↻'), h('button', { title: 'Fechar', onclick: fechar }, '✕'))),
        h('div', { class: 'abas' }, [['contato', 'Contato'], ['novo', 'Novo lead'], ['conversa', 'Conversa'], ['etapa', 'Etapa']].map(([k, t]) =>
          h('button', { class: estado.aba === k ? 'on' : '', onclick: () => { estado.aba = k; estado.msg = null; desenhar(); } }, t))),
        h('div', { class: 'corpo' },
          estado.msg ? h('div', { class: estado.msg.tipo === 'ok' ? 'ok' : estado.msg.tipo === 'aviso' ? 'aviso' : 'erro' }, estado.msg.texto,
            estado.msg.campos ? h('ul', {}, Object.values(estado.msg.campos).flat().map((c) => h('li', {}, c))) : null) : null,
          !estado.chat || !estado.chat.ok ? h('div', { class: 'aviso' }, estado.chat ? estado.chat.motivo : 'Abra uma conversa.')
            : estado.aba === 'contato' ? abaContato() : estado.aba === 'novo' ? abaNovoLead() : estado.aba === 'conversa' ? abaConversa() : abaEtapa()));
    }
  }

  self.OravelPanel = { iniciar };
})();
