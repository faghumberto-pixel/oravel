const { normalizarBase } = self.OravelLib;
const $ = (id) => document.getElementById(id);

function status(tipo, texto) { const s = $('status'); s.className = tipo; s.textContent = texto; s.style.display = 'block'; }

chrome.storage.local.get(['base', 'token']).then((c) => { $('base').value = c.base || 'https://app.oravel.com.br'; $('token').value = c.token || ''; });

$('salvar').addEventListener('click', async () => {
  const base = normalizarBase($('base').value);
  if (!base) return status('erro', 'Endereço inválido. Use https:// (ou http://localhost para testes).');
  const token = $('token').value.trim();
  if (!token) return status('erro', 'Cole o token.');

  // Servidor diferente dos já liberados no manifesto: pede permissão ao usuário (precisa ser neste clique).
  if (base !== 'https://app.oravel.com.br') {
    const ok = await chrome.permissions.request({ origins: [base + '/*'] });
    if (!ok) return status('erro', 'Sem permissão para falar com ' + base + '.');
  }
  await chrome.storage.local.set({ base, token });

  const r = await chrome.runtime.sendMessage({ tipo: 'api', operacao: 'eu', params: {} });
  if (r && r.erro) return status('erro', r.erro.mensagem);
  status('ok', 'Conectado como ' + r.data.usuario.nome + ' (' + r.data.empresa.nome + ').');
});

$('limpar').addEventListener('click', async () => { await chrome.storage.local.remove('token'); $('token').value = ''; status('ok', 'Token removido deste navegador.'); });
