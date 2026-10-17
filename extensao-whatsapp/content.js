// Content script no WhatsApp Web. Lê SÓ o chat aberto e SÓ quando o vendedor abre o painel ou clica em
// "Ler mensagens visíveis". Não observa o DOM continuamente, não envia mensagens, não percorre conversas.
(function () {
  const { normalizarTelefone, pareceTelefone, telefoneDosIds, ehGrupo, montarMensagens } = self.OravelLib;
  const S = self.OravelSelectors;

  function chatAberto() {
    const chat = document.querySelector(S.chat);
    if (!chat) return { ok: false, motivo: 'Abra uma conversa para usar o Oravel.' };
    const titulo = document.querySelector(S.titulo);
    const nome = titulo ? (titulo.getAttribute('title') || titulo.textContent || '').trim() : '';
    if (!nome) return { ok: false, motivo: 'Layout do WhatsApp não reconhecido (cabeçalho). Avise o suporte Oravel.' };

    const ids = Array.from(document.querySelectorAll(S.linhas)).map((el) => el.getAttribute('data-id') || '');
    if (ehGrupo(ids)) return { ok: false, motivo: 'Este chat é um grupo. O Oravel funciona em conversas com uma pessoa.' };

    // Telefone: título quando é número (contato não salvo) ou id das mensagens; senão o vendedor digita.
    const telefone = pareceTelefone(nome) ? normalizarTelefone(nome) : telefoneDosIds(ids);
    return { ok: true, nome, telefone };
  }

  function lerMensagensVisiveis() {
    const linhas = Array.from(document.querySelectorAll(S.linhas)).map((el) => {
      const pre = el.querySelector(S.prefixo);
      const textos = Array.from(el.querySelectorAll(S.texto)).map((t) => t.textContent).join('\n');
      return { id: el.getAttribute('data-id') || '', pre: pre ? pre.getAttribute('data-pre-plain-text') : '', texto: textos };
    });
    return montarMensagens(linhas);
  }

  function api(operacao, params) {
    return chrome.runtime.sendMessage({ tipo: 'api', operacao, params }).then((r) => {
      if (r && r.erro) { const e = new Error(r.erro.mensagem); e.codigo = r.erro.codigo; e.campos = r.erro.campos; throw e; }
      return r ? r.data : null;
    });
  }

  self.OravelPanel.iniciar({ chatAberto, lerMensagensVisiveis, api });
})();
