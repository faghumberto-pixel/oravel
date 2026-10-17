// Interpretação dos dados que o content script extrai do WhatsApp Web. Funções puras (sem DOM).
(function (root, factory) {
  var api = factory(typeof require === 'function' ? require('./telefone.js') : root.OravelLib);
  if (typeof module === 'object' && module.exports) module.exports = api;
  else root.OravelLib = Object.assign(root.OravelLib || {}, api);
})(typeof self !== 'undefined' ? self : globalThis, function (tel) {
  /**
   * data-id das mensagens: "false_5519999990000@c.us_3EB0ABC" (recebida) / "true_..." (enviada).
   * Grupos usam "@g.us" e contatos ocultos "@lid": nesses casos não há telefone.
   */
  function interpretarId(id) {
    var m = /^(true|false)_([^_@]+)@([a-z.]+)(?:_|$)/.exec(String(id || ''));
    if (!m) return null;
    return { enviada: m[1] === 'true', origem: m[2], tipo: m[3] };
  }

  /** Telefone do chat aberto a partir dos ids das mensagens visíveis (só conversa 1:1 com @c.us). */
  function telefoneDosIds(ids) {
    for (var i = 0; i < ids.length; i++) {
      var p = interpretarId(ids[i]);
      if (p && p.tipo === 'c.us') {
        var t = tel.normalizarTelefone(p.origem);
        if (t) return t;
      }
    }
    return '';
  }

  /** O chat é um grupo? (qualquer id @g.us) */
  function ehGrupo(ids) {
    return ids.some(function (id) { var p = interpretarId(id); return p && p.tipo === 'g.us'; });
  }

  /** "[14:32, 16/10/2026] Maria: " -> { hora, data, autor } */
  function interpretarPrefixo(pre) {
    var m = /^\[([^,\]]+),\s*([^\]]+)\]\s*(.*?):\s*$/.exec(String(pre || ''));
    if (!m) return null;
    return { hora: m[1].trim(), data: m[2].trim(), autor: m[3].trim() };
  }

  /**
   * Linhas extraídas do DOM ({ id, pre, texto }) -> mensagens limpas. Ignora linhas sem texto
   * (figurinha, áudio, imagem sem legenda): a extensão não captura mídia.
   */
  function montarMensagens(linhas) {
    var out = [];
    (linhas || []).forEach(function (l) {
      var texto = String(l.texto || '').trim();
      if (!texto) return;
      var id = interpretarId(l.id);
      var pre = interpretarPrefixo(l.pre);
      out.push({
        enviada: id ? id.enviada : false,
        autor: pre ? pre.autor : '',
        data: pre ? pre.data : '',
        hora: pre ? pre.hora : '',
        texto: texto,
      });
    });
    return out;
  }

  /** Texto editável que o vendedor revisa antes de salvar no CRM. */
  function formatarConversa(mensagens, nomeVendedor) {
    return mensagens.map(function (m) {
      var quem = m.enviada ? (nomeVendedor || 'Eu') : (m.autor || 'Contato');
      var quando = [m.data, m.hora].filter(Boolean).join(' ');
      return (quando ? '[' + quando + '] ' : '') + quem + ': ' + m.texto;
    }).join('\n');
  }

  /** Direção sugerida do registro: a da última mensagem. */
  function direcaoSugerida(mensagens) {
    if (!mensagens.length) return 'entrada';
    return mensagens[mensagens.length - 1].enviada ? 'saida' : 'entrada';
  }

  return {
    interpretarId: interpretarId, telefoneDosIds: telefoneDosIds, ehGrupo: ehGrupo,
    interpretarPrefixo: interpretarPrefixo, montarMensagens: montarMensagens,
    formatarConversa: formatarConversa, direcaoSugerida: direcaoSugerida,
  };
});
