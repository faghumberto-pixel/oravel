// Cliente da API /api/crm/v1. Roda no service worker (único lugar que fala com o servidor) e no Node (testes).
(function (root, factory) {
  var api = factory();
  if (typeof module === 'object' && module.exports) module.exports = api;
  else root.OravelLib = Object.assign(root.OravelLib || {}, api);
})(typeof self !== 'undefined' ? self : globalThis, function () {
  function ApiErro(status, codigo, mensagem, campos) {
    this.name = 'ApiErro';
    this.status = status;
    this.codigo = codigo;
    this.message = mensagem;
    this.campos = campos || null;
  }
  ApiErro.prototype = Object.create(Error.prototype);

  /** https obrigatório, exceto localhost/127.0.0.1 (DEV). Devolve a origem sem barra final ou null. */
  function normalizarBase(url) {
    var u;
    try { u = new URL(String(url || '').trim()); } catch (e) { return null; }
    var local = u.hostname === 'localhost' || u.hostname === '127.0.0.1';
    if (u.protocol !== 'https:' && !(local && u.protocol === 'http:')) return null;
    return u.origin;
  }

  var MENSAGENS = {
    nao_autenticado: 'Token inválido, expirado ou revogado. Gere outro em Integração / API.',
    sem_habilidade: 'Este token não tem a permissão necessária.',
    modulo_desligado: 'O CRM não está liberado no contrato da empresa.',
    conta_bloqueada: 'A conta da empresa está bloqueada.',
    muitas_requisicoes: 'Muitas requisições. Aguarde um minuto e tente de novo.',
  };

  function criarCliente(opcoes) {
    var base = normalizarBase(opcoes.base);
    if (!base) throw new ApiErro(0, 'url_invalida', 'Endereço do sistema inválido (use https://).');
    var f = opcoes.fetchImpl || (typeof fetch === 'function' ? fetch.bind(globalThis) : null);

    async function chamar(metodo, caminho, corpo, formData) {
      var headers = { Accept: 'application/json', Authorization: 'Bearer ' + opcoes.token };
      var init = { method: metodo, headers: headers };
      if (formData) init.body = formData;
      else if (corpo !== undefined) { headers['Content-Type'] = 'application/json'; init.body = JSON.stringify(corpo); }

      var resp;
      try { resp = await f(base + '/api/crm/v1' + caminho, init); }
      catch (e) { throw new ApiErro(0, 'sem_conexao', 'Não foi possível falar com o sistema. Verifique a internet e o endereço.'); }

      var json = null;
      try { json = await resp.json(); } catch (e) { /* corpo vazio ou HTML */ }

      if (!resp.ok) {
        var e = (json && json.erro) || {};
        var codigo = e.codigo || (resp.status === 429 ? 'muitas_requisicoes' : 'erro_' + resp.status);
        throw new ApiErro(resp.status, codigo, e.mensagem || MENSAGENS[codigo] || 'Erro ' + resp.status, e.campos);
      }
      return json ? json.data : null;
    }

    var q = encodeURIComponent;
    return {
      eu: function () { return chamar('GET', '/eu'); },
      configuracao: function () { return chamar('GET', '/configuracao'); },
      buscar: function (telefone) { return chamar('GET', '/contatos/buscar?telefone=' + q(telefone)); },
      oportunidades: function (tipo, id) { return chamar('GET', '/contatos/' + q(tipo) + '/' + q(id) + '/oportunidades'); },
      cnpj: function (cnpj) { return chamar('GET', '/cnpj/' + q(String(cnpj).replace(/\D/g, ''))); },
      lead: function (id) { return chamar('GET', '/leads/' + q(id)); },
      criarLead: function (dados) { return chamar('POST', '/leads', dados); },
      moverEtapa: function (id, dados) { return chamar('PATCH', '/leads/' + q(id) + '/etapa', dados); },
      /** audio: { blob, nome } opcional -> multipart; sem áudio -> JSON. */
      registrarInteracao: function (id, dados, audio) {
        if (!audio) return chamar('POST', '/leads/' + q(id) + '/interacoes', dados);
        var fd = new FormData();
        Object.keys(dados).forEach(function (k) { if (dados[k] !== undefined && dados[k] !== null && dados[k] !== '') fd.append(k, dados[k]); });
        fd.append('audio', audio.blob, audio.nome || 'audio');
        return chamar('POST', '/leads/' + q(id) + '/interacoes', undefined, fd);
      },
    };
  }

  return { criarCliente: criarCliente, normalizarBase: normalizarBase, ApiErro: ApiErro };
});
