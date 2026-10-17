// Service worker: único ponto que fala com o servidor do CRM. O content script (dentro do WhatsApp Web)
// nunca vê o token: pede aqui e recebe só o resultado.
importScripts('lib/api.js');

const { criarCliente, ApiErro } = self.OravelLib;

async function config() {
  const c = await chrome.storage.local.get(['base', 'token']);
  return { base: c.base || 'https://app.oravel.com.br', token: c.token || '' };
}

function dataUrlParaBlob(dataUrl) {
  const [cab, b64] = dataUrl.split(',');
  const tipo = /data:([^;]+)/.exec(cab)?.[1] || 'application/octet-stream';
  const bin = atob(b64);
  const bytes = new Uint8Array(bin.length);
  for (let i = 0; i < bin.length; i++) bytes[i] = bin.charCodeAt(i);
  return new Blob([bytes], { type: tipo });
}

const OPERACOES = {
  eu: (api) => api.eu(),
  configuracao: (api) => api.configuracao(),
  buscar: (api, p) => api.buscar(p.telefone),
  oportunidades: (api, p) => api.oportunidades(p.tipo, p.id),
  cnpj: (api, p) => api.cnpj(p.cnpj),
  lead: (api, p) => api.lead(p.id),
  criarLead: (api, p) => api.criarLead(p.dados),
  moverEtapa: (api, p) => api.moverEtapa(p.id, p.dados),
  registrarInteracao: (api, p) => api.registrarInteracao(
    p.id, p.dados, p.audio ? { blob: dataUrlParaBlob(p.audio.dataUrl), nome: p.audio.nome } : null),
};

async function executar(msg) {
  const { base, token } = await config();
  if (!token) return { erro: { codigo: 'sem_token', mensagem: 'Configure o token nas opções da extensão.' } };
  const operacao = OPERACOES[msg.operacao];
  if (!operacao) return { erro: { codigo: 'operacao_invalida', mensagem: 'Operação desconhecida.' } };
  try {
    return { data: await operacao(criarCliente({ base, token }), msg.params || {}) };
  } catch (e) {
    if (e instanceof ApiErro) return { erro: { codigo: e.codigo, mensagem: e.message, campos: e.campos, status: e.status } };
    return { erro: { codigo: 'erro_interno', mensagem: String(e && e.message || e) } };
  }
}

chrome.runtime.onMessage.addListener((msg, sender, responder) => {
  // Só aceita mensagens da própria extensão (content script no WhatsApp Web ou página de opções).
  if (sender.id !== chrome.runtime.id || !msg || msg.tipo !== 'api') return false;
  executar(msg).then(responder);
  return true; // resposta assíncrona
});
